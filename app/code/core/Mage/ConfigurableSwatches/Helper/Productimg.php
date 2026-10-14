<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ConfigurableSwatches
 */

class Mage_ConfigurableSwatches_Helper_Productimg extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_ConfigurableSwatches';

    /**
     * This array stores product images and separates them:
     * One group keyed by labels that match attribute values, another for all other images
     *
     * @var array
     */
    protected $_productImagesByLabel = [];

    /**
     * This array stores all possible labels and swatch labels used for associating gallery
     * images with swatches and main image swaps. It's use is for filtering the image gallery.
     *
     * @var array
     */
    protected $_productImageFilters = [];

    /** @var array<string, string>|null the color of each swatch, by the file name without its extension */
    protected ?array $swatchColors = null;

    public const SWATCH_LABEL_SUFFIX = '-swatch';
    public const SWATCH_FALLBACK_MEDIA_DIR = 'wysiwyg/swatches';
    public const SWATCH_CACHE_DIR = 'catalog/swatches';

    /** Cache tag of the file checks on a remote media mount. */
    public const CACHE_TAG = 'configurableswatches_image';

    /** Seconds that a remote file check which found no file stays in the cache. */
    public const MISSING_FILE_CACHE_LIFETIME = 3600;

    #[\Deprecated(message: 'since 26.3 — use {@see getSwatchFileExt()} instead')]
    public const SWATCH_FILE_EXT = '.png';

    /**
     * Get the configured swatch file extension based on system image type setting
     */
    public static function getSwatchFileExt(): string
    {
        return Maho::getConfiguredImageExtension();
    }

    public const MEDIA_IMAGE_TYPE_BASE = 'base_image';
    public const MEDIA_IMAGE_TYPE_SMALL = 'small_image';

    public const SWATCH_DEFAULT_WIDTH = 21;
    public const SWATCH_DEFAULT_HEIGHT = 21;

    /**
     * Determine if the passed text matches the label of any of the passed product's images
     *
     * @param string $text
     * @param Mage_Catalog_Model_Product $product
     * @param string $type
     * @return \Maho\DataObject|null
     */
    public function getProductImgByLabel($text, $product, $type = null)
    {
        $this->indexProductImages($product);

        //Get the product's image array and prepare the text
        $images = $this->_productImagesByLabel[$product->getId()];
        $text = Mage_ConfigurableSwatches_Helper_Data::normalizeKey($text);

        $resultImages = [
            'standard' => $images[$text] ?? null,
            'swatch' => $images[$text . self::SWATCH_LABEL_SUFFIX] ?? null,
        ];

        if (!is_null($type) && array_key_exists($type, $resultImages)) {
            $image = $resultImages[$type];
        } else {
            $image = $resultImages['swatch'] ?? $resultImages['standard'];
        }

        return $image;
    }

    /**
     * Create the separated index of product images
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array|null $preValues
     */
    public function indexProductImages($product, $preValues = null)
    {
        if ($product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            return; // we only index images on configurable products
        }

        if (!isset($this->_productImagesByLabel[$product->getId()])) {
            $images = [];
            $searchValues = [];

            if (!is_null($preValues) && is_array($preValues)) { // If a pre-defined list of valid values was passed
                $preValues = array_map(Mage_ConfigurableSwatches_Helper_Data::normalizeKey(...), $preValues);
                foreach ($preValues as $value) {
                    $searchValues[] = $value;
                }
            } else { // we get them from all config attributes if no pre-defined list is passed in
                /** @var Mage_Catalog_Model_Product_Type_Configurable $productType */
                $productType = $product->getTypeInstance(true);
                $attributes = $productType->getConfigurableAttributes($product);

                // Collect valid values of image type attributes
                foreach ($attributes as $attribute) {
                    if (Mage::helper('configurableswatches')->attrIsSwatchType($attribute->getAttributeId())) {
                        foreach ($attribute->getPrices() as $option) { // getPrices returns info on individual options
                            $searchValues[] = Mage_ConfigurableSwatches_Helper_Data::normalizeKey($option['label']);
                        }
                    }
                }
            }

            $mapping = $product->getChildAttributeLabelMapping();
            $mediaGallery = $product->getMediaGallery();
            $mediaGalleryImages = $product->getMediaGalleryImages();

            if (empty($mediaGallery['images']) || empty($mediaGalleryImages)) {
                $this->_productImagesByLabel[$product->getId()] = [];
                return; //nothing to do here
            }

            $imageHaystack = array_map(fn($value) => Mage_ConfigurableSwatches_Helper_Data::normalizeKey($value['label']), $mediaGallery['images']);

            foreach ($searchValues as $label) {
                $imageKeys = [];
                $swatchLabel = $label . self::SWATCH_LABEL_SUFFIX;

                $imageKeys[$label] = array_search($label, $imageHaystack);
                if ($imageKeys[$label] === false && isset($mapping[$label]['default_label'])) {
                    $imageKeys[$label] = array_search($mapping[$label]['default_label'], $imageHaystack);
                }

                $imageKeys[$swatchLabel] = array_search($swatchLabel, $imageHaystack);
                if ($imageKeys[$swatchLabel] === false && isset($mapping[$label]['default_label'])) {
                    $imageKeys[$swatchLabel] = array_search(
                        $mapping[$label]['default_label'] . self::SWATCH_LABEL_SUFFIX,
                        $imageHaystack,
                    );
                }

                foreach ($imageKeys as $imageLabel => $imageKey) {
                    if ($imageKey !== false) {
                        $imageId = $mediaGallery['images'][$imageKey]['value_id'];
                        $images[$imageLabel] = $mediaGalleryImages->getItemById($imageId);
                    }
                }
            }
            $this->_productImagesByLabel[$product->getId()] = $images;
        }
    }

    /**
     * Return the appropriate swatch URL for the given value (matches against product's image labels)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $value
     * @param int $width
     * @param int $height
     * @param string $swatchType
     * @param string $fallbackFileExt
     * @return string
     */
    public function getSwatchUrl(
        $product,
        $value,
        $width,
        $height,
        &$swatchType,
        $fallbackFileExt = null,
    ) {
        $url = '';
        $swatchType = 'none';

        // Get the (potential) swatch image that matches the value
        $image = $this->getProductImgByLabel($value, $product, 'swatch');

        // Check in swatch directory if $image is null
        if (is_null($image)) {
            // Check if file exists in fallback directory
            $fallbackUrl = $this->getGlobalSwatchUrl($product, $value, $width, $height, $fallbackFileExt);
            if (!empty($fallbackUrl)) {
                $url = $fallbackUrl;
                $swatchType = 'media';
            }
        }

        // If we still don't have a URL or matching product image, look for one that matches just
        // the label (not specifically the swatch suffix)
        if (empty($url) && is_null($image)) {
            $image = $this->getProductImgByLabel($value, $product, 'standard');
        }

        if (!is_null($image)) {
            $set = Mage::helper('core/image')->init(Mage_Catalog_Model_Product_Image_Provider::ENTITY, (string) $image->getFile());
            $swatchType = 'product';
            $url = $set?->getUrl($this->getSwatchFileWidth((int) $width, (int) $height)) ?? '';
        }

        return $url;
    }

    /**
     * The width of the file of a swatch: 2x the width of the swatch slot of the theme, or 2x the
     * larger side of the box when it is larger. The theme sets the size that a swatch shows at,
     * so the box of the config can be smaller.
     */
    protected function getSwatchFileWidth(int $width, int $height): int
    {
        $slotWidth = Mage::getSingleton('core/image_config')->getSlotWidth(
            Mage_ConfigurableSwatches_Model_Swatch_Image::SLOT,
            (int) Mage::app()->getStore()->getId(),
            Mage_ConfigurableSwatches_Model_Swatch_Image::ENTITY,
        ) ?? 0;
        return 2 * max($slotWidth, $width, $height);
    }

    /**
     * The URL of the resized file of a swatch image in wysiwyg/swatches, or null when the image
     * does not exist.
     */
    protected function getSwatchImageUrl(string $filename, int $width, int $height): ?string
    {
        $mount = Mage::getStorage('media');
        $sourcePath = \Maho\Io::getPathWithinMount($mount, self::SWATCH_FALLBACK_MEDIA_DIR, $filename);
        if ($sourcePath === null || !$this->fileExists($mount, $sourcePath)) {
            return null;
        }
        return Mage::helper('core/image')
            ->init(Mage_ConfigurableSwatches_Model_Swatch_Image::ENTITY, '/' . ltrim($filename, '/'))
            ?->getUrl($this->getSwatchFileWidth($width, $height));
    }

    /**
     * Return URL for a matching swatch image from the global directory
     *
     * @param Mage_Catalog_Model_Product|Mage_Catalog_Model_Layer_Filter_Item $object
     * @param string $value
     * @param int $width
     * @param int $height
     * @param string $fileExt
     * @throws Mage_Core_Exception
     * @return string
     */
    public function getGlobalSwatchUrl(
        $object,
        $value,
        $width = self::SWATCH_DEFAULT_WIDTH,
        $height = self::SWATCH_DEFAULT_HEIGHT,
        $fileExt = null,
    ) {
        $fileExt ??= self::getSwatchFileExt();

        // normalize to all lower case so that value can be used as array key below
        $value = Mage_ConfigurableSwatches_Helper_Data::normalizeKey($value);
        $defaultValue = $value; // default to no fallback value
        if ($object instanceof Mage_Catalog_Model_Layer_Filter_Item) { // fallback for swatches loaded for nav filters
            $source = $object->getFilter()->getAttributeModel()->getFrontend()->getAttribute()->getSource();
            foreach ($source->getAllOptions(false, true) as $option) {
                if ($option['value'] == $object->getValue()) {
                    $defaultValue = Mage_ConfigurableSwatches_Helper_Data::normalizeKey($option['label']);
                    break;
                }
            }
        } elseif ($object instanceof Mage_Catalog_Model_Product) {  // fallback for swatches loaded for product view
            $mapping = $object->getChildAttributeLabelMapping();
            if (isset($mapping[$value]['default_label'])) {
                $defaultValue = $mapping[$value]['default_label'];
            }
        }

        do {
            $filename = Mage::helper('configurableswatches')->getHyphenatedString($value) . $fileExt;
            $url = $this->getSwatchImageUrl($filename, (int) $width, (int) $height);
            $url ??= $this->getColorSwatchUrl((string) $value, (int) $width, (int) $height);
            if ($url === null && $defaultValue == $value) {
                return '';  // no image found and no further fallback
            } elseif ($url === null) {
                $value = $defaultValue; // fallback to default value
            } else {
                break;  // we found an image
            }
        } while (true);

        return $url;
    }

    /**
     * The color of the swatch of $value as an SVG data URL, or null when the option has no color.
     */
    public function getColorSwatchUrl(string $value, int $width, int $height): ?string
    {
        if ($this->swatchColors === null) {
            $resource = Mage::getSingleton('core/resource');
            $adapter = $resource->getConnection('core_read');
            $this->swatchColors = [];
            foreach ($adapter->fetchAll($adapter->select()
                ->from($resource->getTableName('eav/attribute_option_swatch'), ['filename', 'value'])
                ->where('value IS NOT NULL')) as $row) {
                $this->swatchColors[pathinfo((string) $row['filename'], PATHINFO_FILENAME)] ??= (string) $row['value'];
            }
        }

        $color = $this->swatchColors[Mage::helper('configurableswatches')->getHyphenatedString($value)] ?? '';
        if (!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color, $match)) {
            return null;
        }
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$width}' height='{$height}'><rect width='100%' height='100%' fill='#{$match[1]}'/></svg>";
        return 'data:image/svg+xml,' . rawurlencode($svg);
    }

    /**
     * Create a swatch image for the given filename
     *
     * @deprecated since 26.11 a color swatch is an SVG data URL, see getColorSwatchUrl()
     * @param string $value
     * @param int $width
     * @param int $height
     * @return string|false $destPath
     * @throws Mage_Core_Exception
     */
    public function createSwatchImage($value, $width, $height)
    {
        $filename = Mage::helper('configurableswatches')->getHyphenatedString($value) . self::getSwatchFileExt();
        $mount = Mage::getStorage('media');
        $destPath = $this->getSwatchCachePath($mount, 'media', $filename, (int) $width, (int) $height);
        if ($destPath === null) {
            return false;
        }
        if ($this->fileExists($mount, $destPath)) {
            return $destPath;
        }

        $optionSwatch = Mage::getModel('eav/entity_attribute_option_swatch')
            ->load($filename, 'filename');
        if (!$optionSwatch->getValue()) {
            return false;
        }

        $image = Maho::getImageManager()->createImage($width, $height)->fill($optionSwatch->getValue());
        $mount->moveAtomic($destPath, Maho::encodeImage($image)->toString());
        $this->rememberFileExists($mount, $destPath, true);

        return $destPath;
    }

    /**
     * Performs the resize operation on the given swatch image file and returns a
     * relative path to the resulting image file
     *
     * @deprecated since 26.11 a swatch image has the resized files of its image entity, product or swatch
     * @param string $filename
     * @param string $tag
     * @param int $width
     * @param int $height
     * @return false|string
     */
    protected function _resizeSwatchImage($filename, $tag, $width, $height)
    {
        $mount = Mage::getStorage('media');
        $destPath = $this->getSwatchCachePath($mount, $tag, (string) $filename, (int) $width, (int) $height);
        if ($destPath === null) {
            return false;
        }
        if ($this->fileExists($mount, $destPath)) {
            return $destPath;
        }

        $sourcePath = $tag == 'product'
            ? \Maho\Io::getPathWithinMount($mount, Mage::getSingleton('catalog/product_media_config')->getBaseMediaStoragePath(), (string) $filename)
            : \Maho\Io::getPathWithinMount($mount, self::SWATCH_FALLBACK_MEDIA_DIR, (string) $filename);
        if ($sourcePath === null || !$this->fileExists($mount, $sourcePath)) {
            return false;
        }

        $image = Maho::getImageManager()->decodeBinary($mount->read($sourcePath));
        $image->resize($width, $height);
        $mount->moveAtomic($destPath, $image->encodeUsingPath($destPath)->toString());
        $this->rememberFileExists($mount, $destPath, true);

        return $destPath;
    }

    /**
     * Mount path of a cached swatch: "catalog/swatches/{store}/{width}x{height}/{tag}/{filename}".
     * Null when $filename leaves that directory.
     */
    protected function getSwatchCachePath(\Maho\Storage\Mount $mount, string $tag, string $filename, int $width, int $height): ?string
    {
        $directory = implode('/', [self::SWATCH_CACHE_DIR, Mage::app()->getStore()->getId(), $width . 'x' . $height, $tag]);
        return \Maho\Io::getPathWithinMount($mount, $directory, $filename);
    }

    /**
     * Check a file during render. A remote mount keeps the answer in the cache, so a page with
     * many swatches sends no request per swatch to the bucket. A missing file is checked again
     * after MISSING_FILE_CACHE_LIFETIME, so a new fallback swatch shows up without a flush.
     */
    protected function fileExists(\Maho\Storage\Mount $mount, string $path): bool
    {
        if ($mount->isLocal()) {
            return $mount->fileExists($path);
        }

        $cached = Mage::app()->loadCache($this->getFileCacheId($path));
        if ($cached === '1' || $cached === '0') {
            return $cached === '1';
        }

        $exists = $mount->fileExists($path);
        $this->rememberFileExists($mount, $path, $exists);
        return $exists;
    }

    protected function rememberFileExists(\Maho\Storage\Mount $mount, string $path, bool $exists): void
    {
        if ($mount->isLocal()) {
            return;
        }
        Mage::app()->saveCache(
            $exists ? '1' : '0',
            $this->getFileCacheId($path),
            [self::CACHE_TAG],
            $exists ? null : self::MISSING_FILE_CACHE_LIFETIME,
        );
    }

    protected function getFileCacheId(string $path): string
    {
        return self::CACHE_TAG . '_' . md5($path);
    }

    /**
     * Cleans out the swatch image cache dir
     */
    public function clearSwatchesCache()
    {
        Mage::getStorage('media')->deleteDirectory(self::SWATCH_CACHE_DIR);
        Mage::app()->cleanCache([self::CACHE_TAG]);
    }

    /**
     * Determine whether to show an image in the product media gallery
     *
     * @param Mage_Catalog_Model_Product $product
     * @param \Maho\DataObject $image
     * @return bool
     */
    public function filterImageInGallery($product, $image)
    {
        if (!Mage::helper('configurableswatches')->isEnabled()) {
            return true;
        }

        if (!isset($this->_productImageFilters[$product->getId()])) {
            $mapping = array_merge_recursive(...array_values($product->getChildAttributeLabelMapping()));
            $filters = array_unique($mapping['labels'] ?? []);
            foreach ($filters as $label) {
                $filters[] = $label . Mage_ConfigurableSwatches_Helper_Productimg::SWATCH_LABEL_SUFFIX;
            }
            $this->_productImageFilters[$product->getId()] = $filters;
        }

        return !in_array(
            Mage_ConfigurableSwatches_Helper_Data::normalizeKey($image->getLabel()),
            $this->_productImageFilters[$product->getId()],
        );
    }
}
