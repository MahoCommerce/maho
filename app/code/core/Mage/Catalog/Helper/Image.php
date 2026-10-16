<?php

/**
 * Resized product image URLs for templates.
 *
 * A product image has a fixed list of widths, which the theme declares. resize() picks the
 * smallest width that is the requested size or more, so a template can ask for any size
 * without a new file. The ratio, the fit, the quality and the watermark come from the theme
 * and the store config, see Mage_Core_Model_Image_Config.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Helper_Image extends Mage_Core_Helper_Abstract implements \Stringable
{
    /** @deprecated since 26.11 the slots of the theme set the widths */
    public const XML_NODE_PRODUCT_BASE_IMAGE_WIDTH = 'catalog/product_image/base_width';
    /** @deprecated since 26.11 the listing slot of the theme sets the width */
    public const XML_NODE_PRODUCT_SMALL_IMAGE_WIDTH = 'catalog/product_image/small_width';
    public const XML_NODE_PRODUCT_MAX_DIMENSION = 'catalog/product_image/max_dimension';

    /** The size of the original when it cannot be read. */
    public const FALLBACK_SIZE = 1800;

    /**
     * @deprecated since 26.11 image URLs no longer depend on a cache file, so they are always
     *             safe to keep in the block cache. The value stays false.
     */
    public bool $hasDeferredImages = false;

    #[\Override]
    protected $_moduleName = 'Mage_Catalog';

    protected ?Mage_Catalog_Model_Product $product = null;

    protected string $attributeName = 'image';

    protected ?Mage_Core_Model_Image_Set $set = null;

    /** The requested size. 0 asks for the largest width. */
    protected int $size = 0;

    /** Skin path of the placeholder when the product has no image and the config names none. */
    protected ?string $placeholder = null;

    /** The image attributes that a product had before 26.11. They now give the image attribute. */
    public const DEPRECATED_ROLES = ['small_image', 'thumbnail'];

    /** The product attributes that 26.11 removed, and the attribute that replaces each one. */
    public const DEPRECATED_ATTRIBUTES = [
        'small_image' => 'image',
        'thumbnail' => 'image',
        'small_image_label' => 'image_label',
        'thumbnail_label' => 'image_label',
    ];

    /**
     * The attribute that replaces $code when 26.11 removed it, or $code itself.
     */
    public static function mapDeprecatedAttribute(mixed $code): mixed
    {
        return is_string($code) ? self::DEPRECATED_ATTRIBUTES[$code] ?? $code : $code;
    }

    /**
     * Replace each deprecated role or attribute in $types with the attribute that replaces it.
     *
     * @param array<mixed> $types
     * @return list<mixed>
     */
    public static function mapDeprecatedRoles(array $types): array
    {
        return array_values(array_unique(array_map(self::mapDeprecatedAttribute(...), $types)));
    }

    /**
     * Start an image of the product: the file of $attributeName, or $imageFile when it is given.
     * A product without the image gets the placeholder of $attributeName.
     *
     * @param string $attributeName image, or another media image attribute. small_image and
     *                              thumbnail are deprecated and give image.
     * @param string|null $imageFile path below catalog/product, such as /a/b/shirt.jpg
     * @return $this
     */
    public function init(Mage_Catalog_Model_Product $product, $attributeName, $imageFile = null)
    {
        $this->product = $product;
        $this->attributeName = (string) self::mapDeprecatedAttribute($attributeName);
        $this->size = 0;
        $this->placeholder = null;

        $helper = Mage::helper('core/image');
        $file = $imageFile ?: $product->getData($this->attributeName);
        $this->set = $helper->init(Mage_Catalog_Model_Product_Image_Provider::ENTITY, is_string($file) ? $file : null);
        if ($this->set === null) {
            $placeholder = (string) Mage::getStoreConfig("catalog/placeholder/{$this->attributeName}_placeholder");
            if ($placeholder !== '') {
                $this->set = $helper->init(
                    Mage_Catalog_Model_Product_Image_Provider::ENTITY,
                    '/' . Mage_Core_Model_Image_Resizer::PLACEHOLDER_FOLDER . '/' . $placeholder,
                );
            }
        }
        return $this;
    }

    /**
     * Ask for an image of this size. The URL is the one of the smallest width that is the
     * larger of $width and $height or more.
     *
     * @param int|string|null $width
     * @param int|string|null $height
     * @return $this
     */
    public function resize($width, $height = null)
    {
        $this->size = max((int) $width, (int) $height);
        return $this;
    }

    /**
     * The URL of the image for the requested size, or of the skin placeholder. It reads no file
     * and resizes nothing.
     */
    #[\Override]
    public function __toString(): string
    {
        return $this->set?->getUrl($this->size) ?? Mage::getDesign()->getSkinUrl($this->getPlaceholder());
    }

    /**
     * The value of a srcset attribute with every width of the image. Empty for the skin
     * placeholder and for an SVG.
     */
    public function getSrcset(): string
    {
        return $this->set?->getSrcset() ?? '';
    }

    /**
     * The src, srcset and sizes attributes of an img element for the image, see
     * Mage_Core_Model_Image_Set::getSrcAttributes(). The skin placeholder gets only a src.
     *
     * @param string $slot a slot of the theme, such as "thumbnail" or "listing"
     */
    public function getSrcAttributes(string $slot = 'listing'): string
    {
        return $this->set?->getSrcAttributes($slot)
            ?? 'src="' . htmlspecialchars(Mage::getDesign()->getSkinUrl($this->getPlaceholder()), ENT_QUOTES | ENT_SUBSTITUTE) . '"';
    }

    /**
     * The attributes of getSrcAttributes() with a width and a height, see
     * Mage_Core_Model_Image_Set::getImgAttributes(). The skin placeholder is square.
     *
     * @param string $slot a slot of the theme, such as "thumbnail" or "listing"
     */
    public function getImgAttributes(string $slot = 'listing'): string
    {
        if ($this->set !== null) {
            return $this->set->getImgAttributes($slot);
        }
        $size = Mage::getSingleton('core/image_config')->getSlotWidth($slot, (int) Mage::app()->getStore()->getId(), Mage_Catalog_Model_Product_Image_Provider::ENTITY) ?? self::FALLBACK_SIZE;
        return $this->getSrcAttributes($slot) . " width=\"{$size}\" height=\"{$size}\"";
    }

    /**
     * The CSS aspect-ratio of the image in $slot, such as "600/300". It follows the ratio of the
     * theme, or the original when the theme keeps it, so a box has the shape of its resized
     * files. The skin placeholder, and an image whose size is not known yet, are square.
     *
     * @param string $slot a slot of the theme, such as "gallery"
     */
    public function getAspectRatio(string $slot = 'listing'): string
    {
        if ($this->set === null) {
            return '1/1';
        }
        $width = $this->set->getDisplayWidth($slot);
        $height = $this->set->getHeightFor($width);
        return $height !== null && $height > 0 ? "{$width}/{$height}" : '1/1';
    }

    /**
     * The resized files of the image, or null for the skin placeholder.
     */
    public function getImageSet(): ?Mage_Core_Model_Image_Set
    {
        return $this->set;
    }

    /**
     * Set the skin path of the placeholder for a product with no image.
     *
     * @param string $fileName
     */
    public function placeholder($fileName)
    {
        $this->placeholder = $fileName;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder ??= 'images/catalog/product/placeholder.svg';
    }

    public function getOriginalWidth(): int
    {
        return $this->set?->getOriginalSize()[0] ?? self::FALLBACK_SIZE;
    }

    public function getOriginalHeight(): int
    {
        return $this->set?->getOriginalSize()[1] ?? self::FALLBACK_SIZE;
    }

    /**
     * @return array{0: int, 1: int} the width and the height of the original
     */
    public function getOriginalSizeArray(): array
    {
        return [$this->getOriginalWidth(), $this->getOriginalHeight()];
    }

    /**
     * @deprecated since 26.11 the store config sets the quality of all images
     * @param int $quality
     * @return $this
     */
    public function setQuality($quality)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 the ratio of the theme applies to all images
     * @param bool $flag
     * @return $this
     */
    public function keepAspectRatio($flag)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 the fit of the theme applies to all images
     * @param bool $flag
     * @param array $position
     * @return $this
     */
    public function keepFrame($flag, $position = ['center', 'middle'])
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 the output format decides whether transparency stays
     * @param bool $flag
     * @param int $alphaOpacity
     * @return $this
     */
    public function keepTransparency($flag, $alphaOpacity = null)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 an image is never scaled up
     * @param bool $flag
     * @return $this
     */
    public function constrainOnly($flag)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 the store config sets the background color of all images
     * @param array $colorRGB
     * @return $this
     */
    public function backgroundColor($colorRGB)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 a rotation is not supported, rotate the original instead
     * @param int $angle
     * @return $this
     */
    public function rotate($angle)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 the store config sets the watermark of all product images
     * @param string $fileName
     * @param string $position
     * @param string $size
     * @param int $imageOpacity
     * @return $this
     */
    public function watermark($fileName, $position, $size = null, $imageOpacity = null)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 the store config sets the watermark of all product images
     * @param string $size
     * @return $this
     */
    public function setWatermarkSize($size)
    {
        return $this;
    }

    /**
     * @deprecated since 26.11 the store config sets the watermark of all product images
     * @param int $imageOpacity
     * @return $this
     */
    public function setWatermarkImageOpacity($imageOpacity)
    {
        return $this;
    }

    /**
     * Check - is this file an image
     *
     * @param string $filePath
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function validateUploadFile($filePath)
    {
        $maxDimension = Mage::getStoreConfig(self::XML_NODE_PRODUCT_MAX_DIMENSION);
        $imageInfo = \Maho\Io::getImageSize($filePath);
        if (!$imageInfo) {
            Mage::throwException($this->__('Disallowed file type.'));
        }

        if ($imageInfo[0] > $maxDimension || $imageInfo[1] > $maxDimension) {
            Mage::throwException($this->__('Disallowed file format.'));
        }

        return $imageInfo['mime'] != null;
    }
}
