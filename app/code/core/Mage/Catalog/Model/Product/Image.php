<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * A resized product image. The source and the resize cache live on the media mount.
 */
class Mage_Catalog_Model_Product_Image extends Mage_Core_Model_Abstract
{
    /** Cache tag of the stored sizes of the source images on a remote mount. */
    public const CACHE_TAG = 'catalog_product_image';

    public const CACHE_DIRECTORY = 'catalog/product/cache';

    /**
     * Requested width for the scaled image
     * @var int
     */
    protected $_width;

    /**
     * Requested height for the scaled image
     * @var int
     */
    protected $_height;

    protected $_quality = 90;

    /**
     * @var bool
     */
    protected $_keepAspectRatio  = true;
    protected $_keepFrame        = true;

    /**
     * If set to true and image format supports transparency (e.g. PNG),
     * transparency will be kept in scaled images. Otherwise transparent areas will be changed to $_backgroundColor
     * @var bool
     */
    protected $_keepTransparency = true;

    /**
     *  If true, images will not be scaled up (when original image is smaller then requested size)
     * @var bool
     */
    protected $_constrainOnly    = false;

    /**
     * Array with RGB values for background color e.g. [255, 255, 255]
     * used e.g. when filling transparent color in scaled images
     *
     * @var array
     */
    protected $_backgroundColor  = [255, 255, 255];
    protected $_backgroundColorStr = 'ffffff';

    /**
     * Absolute path of the original image on a local disk: the skin placeholder, or the source
     * on a local media mount. Null for a source on a remote media mount.
     * @var string|null
     */
    protected $_baseFile;
    protected $_isBaseFilePlaceholder;

    /**
     * @var string|null Absolute path of the resized image on a local mount, its mount path on a
     *                  remote mount, or the URL of an SVG that is not resized
     */
    protected $_newFile;

    /** Mount path of the resized image. Null for an SVG, which is not resized. */
    protected ?string $cacheKey = null;

    protected ?string $sourceBinary = null;

    protected ?string $cacheBinary = null;

    /** @var \Intervention\Image\Interfaces\ImageInterface|null */
    protected $image;

    protected ?array $imageInfo = null;

    /**
     * @var string e.g. "small_image"
     */
    protected $_destinationSubdir;
    protected float $_angle = 0;

    protected $_watermarkFile;
    protected $_watermarkPosition;
    protected $_watermarkWidth;
    protected $_watermarkHeigth;
    protected $_watermarkImageOpacity = 70;

    /**
     * Relative file path (e.g. /c/a/image.jpg) as originally passed to setBaseFile().
     * Stored separately from _baseFile (which is absolute) to avoid exposing
     * server filesystem paths in signed URL tokens.
     */
    protected ?string $_sourceFile = null;

    /**
     * @param int $width
     * @return $this
     */
    public function setWidth($width)
    {
        $this->_width = $width;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getWidth()
    {
        return $this->_width;
    }

    /**
     * @param int $height
     * @return $this
     */
    public function setHeight($height)
    {
        $this->_height = $height;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getHeight()
    {
        return $this->_height;
    }

    public function getImageInfo(): array
    {
        if ($this->imageInfo === null) {
            $info = $this->_baseFile !== null
                ? @\Maho\Io::getImageSize($this->_baseFile)
                : $this->getRemoteImageInfo();
            if ($info === false) {
                throw new RuntimeException('Failed to read image at ' . ($this->_baseFile ?? $this->getSourceKey()));
            }
            $this->imageInfo = $info;
        }
        return $this->imageInfo;
    }

    /**
     * The size of a source on a remote mount, kept in the cache. A template that shows the
     * original size then downloads each image once, not on every render.
     */
    protected function getRemoteImageInfo(): array|false
    {
        $cacheId = self::CACHE_TAG . '_info_' . md5((string) $this->getSourceKey());
        $cached = Mage::app()->loadCache($cacheId);
        if (is_string($cached) && $cached !== '') {
            $info = json_decode($cached, true);
            if (is_array($info)) {
                return $info;
            }
        }

        $info = @getimagesizefromstring($this->getSourceBinary());
        if ($info !== false) {
            Mage::app()->saveCache((string) json_encode($info), $cacheId, [self::CACHE_TAG]);
        }
        return $info;
    }

    public function getOriginalWidth(): int
    {
        if ($this->isSvg()) {
            return (int) Mage::getStoreConfig('catalog/product_image/base_width') ?: 1800;
        }
        return $this->getImageInfo()[0];
    }

    public function getOriginalHeight(): int
    {
        if ($this->isSvg()) {
            return (int) Mage::getStoreConfig('catalog/product_image/base_width') ?: 1800;
        }
        return $this->getImageInfo()[1];
    }

    /**
     * Set image quality, values in percentage from 0 to 100
     *
     * @param int $quality
     * @return $this
     */
    public function setQuality($quality)
    {
        $this->_quality = $quality;
        return $this;
    }

    /**
     * Get image quality
     *
     * @return int
     */
    public function getQuality()
    {
        return $this->_quality;
    }

    /**
     * @param bool $keep
     * @return $this
     */
    public function setKeepAspectRatio($keep)
    {
        $this->_keepAspectRatio = (bool) $keep;
        return $this;
    }

    /**
     * @param bool $keep
     * @return $this
     */
    public function setKeepFrame($keep)
    {
        $this->_keepFrame = (bool) $keep;
        return $this;
    }

    /**
     * @param bool $keep
     * @return $this
     */
    public function setKeepTransparency($keep)
    {
        $this->_keepTransparency = (bool) $keep;
        return $this;
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function setConstrainOnly($flag)
    {
        $this->_constrainOnly = (bool) $flag;
        return $this;
    }

    /**
     * @return $this
     */
    public function setBackgroundColor(array $rgbArray)
    {
        $this->_backgroundColor = $rgbArray;
        $this->_backgroundColorStr = $this->_rgbToString($rgbArray);
        return $this;
    }

    /**
     * @param string $size
     * @return $this
     */
    public function setSize($size)
    {
        // determine width and height from string
        [$width, $height] = explode('x', strtolower($size), 2);
        foreach (['width', 'height'] as $wh) {
            ${$wh}  = (int) ${$wh};
            if (empty(${$wh})) {
                ${$wh} = null;
            }
        }

        // set sizes
        $this->setWidth($width)->setHeight($height);

        return $this;
    }

    /**
     * Convert array of 3 items (decimal r, g, b) to string of their hex values
     *
     * @param array $rgbArray
     * @return string
     */
    protected function _rgbToString($rgbArray)
    {
        $result = [];
        foreach ($rgbArray as $value) {
            if ($value === null) {
                $result[] = 'null';
            } else {
                $result[] = sprintf('%02s', dechex($value));
            }
        }
        return implode('', $result);
    }

    /**
     * Set the source image and compute the cache path from the transform params.
     *
     * No file is read: the URL depends on the params only, and the image route resizes a
     * missing cache file on the first request. An empty $file takes the placeholder that the
     * config names for the destination subdir, or the skin placeholder.
     *
     * @param string|null $file path below catalog/product, such as /i/m/image.jpg
     * @return $this
     */
    public function setBaseFile($file)
    {
        $this->_isBaseFilePlaceholder = false;
        $this->_sourceFile = null;
        $this->_baseFile = null;
        $this->cacheKey = null;
        $this->sourceBinary = null;
        $this->cacheBinary = null;
        $this->imageInfo = null;
        $this->image = null;

        if ($file && !str_starts_with($file, '/')) {
            $file = '/' . $file;
        }
        if ($file == '/no_selection') {
            $file = null;
        }

        if (!$file) {
            $configPlaceholder = Mage::getStoreConfig("catalog/placeholder/{$this->getDestinationSubdir()}_placeholder");
            if (!$configPlaceholder) {
                return $this->useSkinPlaceholder();
            }
            $file = '/placeholder/' . $configPlaceholder;
        }

        $this->_sourceFile = $file;
        $this->_isBaseFilePlaceholder = str_starts_with($file, '/placeholder/');
        $root = $this->getMount()->localRoot();
        if ($root !== null) {
            $this->_baseFile = $root . '/' . $this->getSourceKey();
        }

        if ($this->isSvg()) {
            $this->_newFile = $this->getMount()->publicUrl((string) $this->getSourceKey());
            return $this;
        }

        $this->cacheKey = Maho::buildImageResizeCachePath(
            $this->getTransformParams(),
            Mage::getSingleton('catalog/product_media_config')->getBaseMediaStoragePath(),
            $file,
        );
        $this->_newFile = $root !== null ? $root . '/' . $this->cacheKey : $this->cacheKey;

        return $this;
    }

    /**
     * Use the SVG placeholder of the current skin. It is served as it is, with no resize.
     *
     * @return $this
     */
    public function useSkinPlaceholder(): static
    {
        $this->_isBaseFilePlaceholder = true;
        $this->_sourceFile = null;
        $this->cacheKey = null;
        $this->sourceBinary = null;
        $this->cacheBinary = null;
        $this->imageInfo = null;

        $file = '/images/catalog/product/placeholder.svg';
        $baseDir = Mage::getDesign()->getSkinBaseDir();
        if (!file_exists($baseDir . $file)) {
            $baseDir = Mage::getDesign()->getSkinBaseDir(['_theme' => 'default']);
            if (!file_exists($baseDir . $file)) {
                $baseDir = Mage::getDesign()->getSkinBaseDir(['_theme' => 'default', '_package' => 'base']);
            }
        }

        $this->_baseFile = $baseDir . $file;
        $this->_newFile = str_replace(
            Mage::getBaseDir('skin') . '/',
            Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN),
            $this->_baseFile,
        );

        return $this;
    }

    public function getMount(): \Maho\Storage\Mount
    {
        return Mage::getStorage('media');
    }

    /** Path of the source below catalog/product, such as /i/m/image.jpg. Null for the skin placeholder. */
    public function getSourceFile(): ?string
    {
        return $this->_sourceFile;
    }

    /** Mount path of the source image. Null for the skin placeholder. */
    public function getSourceKey(): ?string
    {
        if ($this->_sourceFile === null) {
            return null;
        }
        return Mage::getSingleton('catalog/product_media_config')->getMediaStoragePath($this->_sourceFile);
    }

    /** Mount path of the resized image. Null when there is no resize, as for an SVG. */
    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }

    public function isSvg(): bool
    {
        return str_ends_with((string) ($this->_sourceFile ?? $this->_baseFile), '.svg');
    }

    public function sourceExists(): bool
    {
        $key = $this->getSourceKey();
        return $key !== null ? $this->getMount()->fileExists($key) : is_file((string) $this->_baseFile);
    }

    protected function getSourceBinary(): string
    {
        if ($this->sourceBinary === null) {
            $key = $this->getSourceKey();
            $this->sourceBinary = $key !== null
                ? $this->getMount()->read($key)
                : (string) file_get_contents((string) $this->_baseFile);
        }
        return $this->sourceBinary;
    }

    /**
     * Allowlist of properties that define the image transformation.
     * Used by both getTransformParams() and setTransformParams().
     */
    private const TRANSFORM_PARAMS = [
        '_width',
        '_height',
        '_quality',
        '_keepAspectRatio',
        '_keepFrame',
        '_keepTransparency',
        '_constrainOnly',
        '_backgroundColorStr',
        '_sourceFile',
        '_destinationSubdir',
        '_angle',
        '_watermarkFile',
        '_watermarkPosition',
        '_watermarkWidth',
        '_watermarkHeigth',
        '_watermarkImageOpacity',
    ];

    /**
     * Hydrate the model from a transform params array (inverse of getTransformParams).
     */
    public function setTransformParams(array $params): self
    {
        foreach (self::TRANSFORM_PARAMS as $prop) {
            if (array_key_exists($prop, $params)) {
                $this->$prop = $params[$prop];
            }
        }
        return $this;
    }

    /**
     * Return all transformation parameters that define the output image.
     * Used both for building cache path hashes and for signed URL token payloads.
     */
    public function getTransformParams(): array
    {
        $params = [];
        foreach (self::TRANSFORM_PARAMS as $prop) {
            $params[$prop] = $this->$prop;
        }
        return $params;
    }

    /**
     * @return string|null
     */
    public function getBaseFile()
    {
        return $this->_baseFile;
    }

    /**
     * @deprecated since 26.11 use getCacheKey() and the media mount
     * @return string|null
     */
    public function getNewFile()
    {
        return $this->_newFile;
    }

    public function getImage(): \Intervention\Image\Interfaces\ImageInterface
    {
        if (!$this->image) {
            $imageManager = Maho::getImageManager(['blendingColor' => $this->_backgroundColorStr]);
            $this->image = $this->_baseFile !== null
                ? $imageManager->decodePath($this->_baseFile)
                : $imageManager->decodeBinary($this->getSourceBinary());
            if ($this->_backgroundColor && !$this->canPreserveTransparency()) {
                $this->image->fillTransparentAreas($this->_backgroundColorStr);
            }
        }

        return $this->image;
    }

    /**
     * Transparency survives the resize only when requested AND the configured output
     * format can carry an alpha channel; the cache file takes that format, not the
     * source extension, so a JPEG source is padded like a PNG one.
     */
    protected function canPreserveTransparency(): bool
    {
        return $this->_keepTransparency
            && in_array(Maho::getConfiguredImageType(), [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_AVIF, IMAGETYPE_GIF], true);
    }

    public function resize(): self
    {
        if (is_null($this->getWidth()) && is_null($this->getHeight())) {
            return $this;
        }

        $background = $this->canPreserveTransparency() ? '#ffffff00' : $this->_backgroundColorStr;

        if ($this->_width && $this->_height) {
            $this->getImage()->containDown($this->_width, $this->_height, $background);
        } elseif ($this->_keepFrame) {
            if ($this->_width) {
                $this->setHeight($this->_width);
            } else {
                $this->setWidth($this->_height);
            }
            $this->getImage()->containDown($this->_width, $this->_height, $background);
        } else {
            $this->getImage()->scaleDown($this->_width, $this->_height);
        }

        return $this;
    }

    public function rotate(float $angle): self
    {
        $angle = (int) $angle;
        if ($angle % 360 === 0) {
            return $this;
        }
        $this->getImage()->rotate($angle, $this->_backgroundColorStr);
        return $this;
    }

    public function setAngle(float $angle): self
    {
        $this->_angle = $angle;
        return $this;
    }

    /**
     * Add watermark to image
     * size param in format 100x200
     *
     * @param string $file
     * @param string $position
     * @param string $size
     * @param int $width
     * @param int $heigth
     * @param int $imageOpacity
     * @return $this
     */
    public function setWatermark($file, $position = null, $size = null, $width = null, $heigth = null, $imageOpacity = null)
    {
        if ($this->_isBaseFilePlaceholder) {
            return $this;
        }

        if ($file) {
            $this->setWatermarkFile($file);
        } else {
            return $this;
        }

        if ($position) {
            $this->setWatermarkPosition($position);
        }
        if ($size) {
            $this->setWatermarkSize($size);
        }
        if ($width) {
            $this->setWatermarkWidth($width);
        }
        if ($heigth) {
            $this->setWatermarkHeigth($heigth);
        }
        if ($imageOpacity) {
            $this->setImageOpacity($imageOpacity);
        }

        $watermark = $this->getWatermarkImage();
        if ($watermark) {
            $position = $this->getWatermarkPosition();

            if ($position === 'stretch') {
                $element = $watermark->resize($this->getOriginalWidth(), $this->getOriginalHeight());
                $position = 'top-left';
            } elseif ($position === 'tile') {
                $tile = $watermark;
                $element = Maho::getImageManager()
                    ->createImage($this->getOriginalWidth(), $this->getOriginalHeight());
                for ($x = 0; $x < ceil($element->width() / $tile->width()); $x++) {
                    for ($y = 0; $y < ceil($element->height() / $tile->height()); $y++) {
                        $element->insert($tile, $x * $tile->width(), $y * $tile->height(), 'top-left');
                    }
                }
                $position = 'top-left';
            } else {
                $element = $watermark;
            }

            $this->getImage()->insert(
                $element,
                alignment: $position,
                transparency: $this->getWatermarkImageOpacity() / 100,
            );
        }

        return $this;
    }

    public function saveFile(): self
    {
        \Maho\Profiler::start('image.process', [
            'image.width' => (string) $this->getWidth(),
            'image.height' => (string) $this->getHeight(),
            'image.destination' => (string) $this->cacheKey,
        ]);

        try {
            $this->rotate($this->_angle);
            $this->resize();
            $this->setWatermark($this->_watermarkFile);

            $this->cacheBinary = Maho::encodeImage($this->getImage(), $this->getQuality())->toString();
            $this->getMount()->moveAtomic((string) $this->cacheKey, $this->cacheBinary);
        } finally {
            \Maho\Profiler::stop('image.process');
        }

        return $this;
    }

    /**
     * The bytes of the resized image. The resize runs first when the cache file is missing.
     */
    public function getCacheBinary(): string
    {
        if ($this->cacheBinary !== null) {
            return $this->cacheBinary;
        }
        try {
            return $this->cacheBinary = $this->getMount()->read((string) $this->cacheKey);
        } catch (\League\Flysystem\UnableToReadFile) {
            $this->saveFile();
            return (string) $this->cacheBinary;
        }
    }

    public function getUrl(): string
    {
        if ($this->cacheKey === null) {
            return (string) $this->_newFile;
        }
        return $this->getMount()->publicUrl($this->cacheKey);
    }

    /**
     * The URL to show when the source image is missing: the placeholder that the config names
     * for this variant, or the skin placeholder when that one is missing too.
     */
    public function getPlaceholderUrl(): string
    {
        /** @var Mage_Catalog_Model_Product_Image $placeholder */
        $placeholder = Mage::getModel('catalog/product_image');
        $placeholder->setTransformParams($this->getTransformParams())->setBaseFile(null);

        if ($placeholder->getCacheKey() !== null
            && ($placeholder->getSourceFile() === $this->_sourceFile || !$placeholder->sourceExists())
        ) {
            $placeholder->useSkinPlaceholder();
        }

        return $placeholder->getUrl();
    }

    public function setDestinationSubdir(string $dir): self
    {
        $this->_destinationSubdir = $dir;
        return $this;
    }

    public function getDestinationSubdir(): string
    {
        return $this->_destinationSubdir;
    }

    public function isCached(): bool
    {
        return $this->cacheKey !== null && $this->getMount()->fileExists($this->cacheKey);
    }

    public function setWatermarkFile(string $file): self
    {
        $this->_watermarkFile = $file;
        return $this;
    }

    public function getWatermarkFile(): ?string
    {
        return $this->_watermarkFile;
    }

    /**
     * Decode the watermark file. The store folder comes first, then the website folder, the
     * default folder, the watermark folder on the media mount, and last the skin.
     */
    protected function getWatermarkImage(): ?\Intervention\Image\Interfaces\ImageInterface
    {
        $file = $this->getWatermarkFile();
        if (!$file) {
            return null;
        }

        $mount = $this->getMount();
        $baseDir = Mage::getSingleton('catalog/product_media_config')->getBaseMediaStoragePath() . '/watermark';
        $candidates = [
            $baseDir . '/stores/' . Mage::app()->getStore()->getId() . $file,
            $baseDir . '/websites/' . Mage::app()->getWebsite()->getId() . $file,
            $baseDir . '/default/' . $file,
            $baseDir . '/' . $file,
        ];
        foreach ($candidates as $candidate) {
            $key = \Maho\Io::getPathWithinMount($mount, $baseDir, substr($candidate, strlen($baseDir)));
            if ($key !== null && $mount->fileExists($key)) {
                return Maho::getImageManager()->decodeBinary($mount->read($key));
            }
        }

        $skinFile = Mage::getDesign()->getSkinBaseDir() . $file;
        if (is_file($skinFile)) {
            return Maho::getImageManager()->decodePath($skinFile);
        }

        return null;
    }

    public function setWatermarkPosition(string $position): self
    {
        $this->_watermarkPosition = $position;
        return $this;
    }

    public function getWatermarkPosition(): ?string
    {
        return $this->_watermarkPosition;
    }

    public function setWatermarkImageOpacity(int $imageOpacity): self
    {
        $this->_watermarkImageOpacity = $imageOpacity;
        return $this;
    }

    public function getWatermarkImageOpacity(): int
    {
        return $this->_watermarkImageOpacity;
    }

    public function setWatermarkSize(array $size): self
    {
        $this->setWatermarkWidth($size['width']);
        $this->setWatermarkHeigth($size['heigth']);
        return $this;
    }

    public function setWatermarkWidth(int $width): self
    {
        $this->_watermarkWidth = $width;
        return $this;
    }

    public function getWatermarkWidth(): ?int
    {
        return $this->_watermarkWidth;
    }

    public function setWatermarkHeigth(int $heigth): self
    {
        $this->_watermarkHeigth = $heigth;
        return $this;
    }

    public function getWatermarkHeigth(): ?int
    {
        return $this->_watermarkHeigth;
    }

    /**
     * Delete every resized image. On a remote mount this lists and deletes each object.
     */
    public function clearCache(): void
    {
        $this->getMount()->deleteDirectory(self::CACHE_DIRECTORY);
        Mage::app()->cleanCache([self::CACHE_TAG]);
    }

    public function setImageOpacity(?int $value): static
    {
        return $this->setData('image_opacity', $value);
    }

}
