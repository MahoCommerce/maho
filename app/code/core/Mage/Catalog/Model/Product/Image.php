<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog product link model
 *
 * @package    Mage_Catalog
 *
 * @method $this setImageOpacity(int $value)
 */
class Mage_Catalog_Model_Product_Image extends Mage_Core_Model_Abstract
{
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
     * Absolute path to and original (full resolution) image
     * @var string
     */
    protected $_baseFile;
    protected $_isBaseFilePlaceholder;

    /**
     * @var string Absolute path to scaled/transformed image
     */
    protected $_newFile;

    /** @var \Intervention\Image\Interfaces\ImageInterface */
    protected $image;

    /**
     * @var string e.g. "small_image"
     */
    protected $_destinationSubdir;
    protected $_angle;

    protected $_watermarkFile;
    protected $_watermarkPosition;
    protected $_watermarkWidth;
    protected $_watermarkHeigth;
    protected $_watermarkImageOpacity = 70;

    /**
     * @var string directory
     */
    protected static $_baseMediaPath;

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
     * Set filenames for base file and new file
     *
     * @param string $file
     * @return $this
     */
    public function setBaseFile($file)
    {
        $this->_isBaseFilePlaceholder = false;

        if (($file) && (!str_starts_with($file, '/'))) {
            $file = '/' . $file;
        }

        if (empty(self::$_baseMediaPath)) {
            self::$_baseMediaPath = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();
        }
        $baseDir = self::$_baseMediaPath;

        if ($file == '/no_selection') {
            $file = null;
        }
        if ($file) {
            if ((!$this->_fileExists($baseDir . $file))) {
                $file = null;
            }
        }
        if (!$file) {
            // check if placeholder defined in config
            $isConfigPlaceholder = Mage::getStoreConfig("catalog/placeholder/{$this->getDestinationSubdir()}_placeholder");
            $configPlaceholder   = '/placeholder/' . $isConfigPlaceholder;
            if ($isConfigPlaceholder && $this->_fileExists($baseDir . $configPlaceholder)) {
                $file = $configPlaceholder;
            } else {
                // replace file with skin or default skin placeholder
                $skinBaseDir     = Mage::getDesign()->getSkinBaseDir();
                $skinPlaceholder = '/images/catalog/product/placeholder.svg';
                $file = $skinPlaceholder;
                if (file_exists($skinBaseDir . $file)) {
                    $baseDir = $skinBaseDir;
                } else {
                    $baseDir = Mage::getDesign()->getSkinBaseDir(['_theme' => 'default']);
                    if (!file_exists($baseDir . $file)) {
                        $baseDir = Mage::getDesign()->getSkinBaseDir(['_theme' => 'default', '_package' => 'base']);
                    }
                }
            }
            $this->_isBaseFilePlaceholder = true;
        }

        $baseFile = $baseDir . $file;
        $this->_baseFile = $baseFile;

        // If the image is an SVG then we don't need to resize it
        if (str_ends_with($this->_baseFile, '.svg')) {
            $this->_newFile = str_replace(
                Mage::getBaseDir('skin') . '/',
                Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN),
                $this->_baseFile,
            );
            return $this;
        }

        // build new filename (most important params)
        $path = [
            self::$_baseMediaPath,
            'cache',
            Mage::app()->getStore()->getId(),
            $path[] = $this->getDestinationSubdir(),
        ];
        if ((!empty($this->_width)) || (!empty($this->_height))) {
            $path[] = "{$this->_width}x{$this->_height}";
        }

        // add misc params as a hash
        $miscParams = [
            ($this->_keepAspectRatio ? '' : 'non') . 'proportional',
            ($this->_keepFrame ? '' : 'no') . 'frame',
            ($this->_keepTransparency ? '' : 'no') . 'transparency',
            ($this->_constrainOnly ? 'do' : 'not') . 'constrainonly',
            $this->_backgroundColorStr,
            'angle' . $this->_angle,
            'quality' . $this->_quality,
        ];

        if ($this->getWatermarkFile()) {
            $miscParams[] = $this->getWatermarkFile();
            $miscParams[] = $this->getWatermarkImageOpacity();
            $miscParams[] = $this->getWatermarkPosition();
            $miscParams[] = $this->getWatermarkWidth();
            $miscParams[] = $this->getWatermarkHeigth();
        }

        $path[] = md5(implode('_', $miscParams));

        // replacing file extension based on target file type
        $targetFileExtension = image_type_to_extension(Mage::getStoreConfig('system/media_storage_configuration/image_file_type'));
        $file = preg_replace('/\.[^.]+$/', $targetFileExtension, $file);

        // append prepared filename
        $this->_newFile = implode('/', $path) . $file; // the $file contains heading slash

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseFile()
    {
        return $this->_baseFile;
    }

    /**
     * @return string
     */
    public function getNewFile()
    {
        return $this->_newFile;
    }

    public function getImage(): \Intervention\Image\Interfaces\ImageInterface
    {
        if (!$this->image) {
             $imageManager = \Intervention\Image\ImageManager::gd(
                autoOrientation: false,
                blendingColor: $this->_backgroundColorStr,
                strip: true
            );
            $this->image = $imageManager->read($this->getBaseFile());
            if ($this->_backgroundColor) {
                $this->image->blendTransparency($this->_backgroundColorStr);
            }
        }

        return $this->image;
    }

    public function resize(): self
    {
        if (is_null($this->getWidth()) && is_null($this->getHeight())) {
            return $this;
        }

        if ($this->_width && $this->_height) {
            $this->getImage()->pad($this->_width, $this->_height);
        } else {
            $this->getImage()->scaleDown($this->_width, $this->_height);
        }

        return $this;
    }

    public function rotate(int $angle): self
    {
        $angle = (int) $angle;
        $this->getImage()->rotate($angle);
        return $this;
    }

    public function setAngle(int $angle): self
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

        $filePath = $this->_getWatermarkFilePath();
        if ($filePath) {
            $this->getImage()
                ->setWatermarkPosition($this->getWatermarkPosition())
                ->setWatermarkImageOpacity($this->getWatermarkImageOpacity())
                ->setWatermarkWidth($this->getWatermarkWidth())
                ->setWatermarkHeigth($this->getWatermarkHeigth())
                ->watermark($filePath);
        }

        return $this;
    }

    public function saveFile(): self
    {
        match (Mage::getStoreConfig('system/media_storage_configuration/image_file_type')) {
            IMAGETYPE_AVIF => $this->getImage()->toAvif($this->getQuality()),
            IMAGETYPE_GIF => $this->getImage()->toGif(),
            IMAGETYPE_JPEG => $this->getImage()->toJpeg($this->getQuality()),
            IMAGETYPE_PNG  => $this->getImage()->toPng(),
            default => $this->getImage()->toWebp($this->getQuality()),
        };

        $filename = $this->getNewFile();
        @mkdir(dirname($filename), recursive: true);
        $this->getImage()->save($filename);
        Mage::helper('core/file_storage_database')->saveFile($filename);
        return $this;
    }

    public function getUrl(): string
    {
        $baseDir = Mage::getBaseDir('media');
        $path = str_replace($baseDir . DS, '', $this->_newFile);
        return Mage::getBaseUrl('media') . str_replace(DS, '/', $path);
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
        return $this->_fileExists($this->_newFile);
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
     * Get relative watermark file path
     * or false if file not found
     */
    protected function _getWatermarkFilePath(): string|false
    {
        $filePath = false;

        if (!$file = $this->getWatermarkFile()) {
            return $filePath;
        }

        $baseDir = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();

        if ($this->_fileExists($baseDir . '/watermark/stores/' . Mage::app()->getStore()->getId() . $file)) {
            $filePath = $baseDir . '/watermark/stores/' . Mage::app()->getStore()->getId() . $file;
        } elseif ($this->_fileExists($baseDir . '/watermark/websites/' . Mage::app()->getWebsite()->getId() . $file)) {
            $filePath = $baseDir . '/watermark/websites/' . Mage::app()->getWebsite()->getId() . $file;
        } elseif ($this->_fileExists($baseDir . '/watermark/default/' . $file)) {
            $filePath = $baseDir . '/watermark/default/' . $file;
        } elseif ($this->_fileExists($baseDir . '/watermark/' . $file)) {
            $filePath = $baseDir . '/watermark/' . $file;
        } else {
            $baseDir = Mage::getDesign()->getSkinBaseDir();
            if ($this->_fileExists($baseDir . $file)) {
                $filePath = $baseDir . $file;
            }
        }

        return $filePath;
    }

    public function setWatermarkPosition(string $position): self
    {
        $this->_watermarkPosition = $position;
        return $this;
    }

    public function getWatermarkPosition(): string
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
        if (is_array($size)) {
            $this->setWatermarkWidth($size['width'])
                ->setWatermarkHeigth($size['heigth']);
        }
        return $this;
    }

    public function setWatermarkWidth(int $width): self
    {
        $this->_watermarkWidth = $width;
        return $this;
    }

    public function getWatermarkWidth(): int
    {
        return $this->_watermarkWidth;
    }

    public function setWatermarkHeigth(int $heigth): self
    {
        $this->_watermarkHeigth = $heigth;
        return $this;
    }

    public function getWatermarkHeigth(): int
    {
        return $this->_watermarkHeigth;
    }

    public function clearCache(): void
    {
        $directory = Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product' . DS . 'cache' . DS;
        $io = new Varien_Io_File();
        $io->rmdir($directory, true);

        Mage::helper('core/file_storage_database')->deleteFolder($directory);
    }

    /**
     * First check this file on FS
     * If it doesn't exist - try to download it from DB
     */
    protected function _fileExists(string $filename): bool
    {
        if (file_exists($filename)) {
            return true;
        } else {
            return Mage::helper('core/file_storage_database')->saveFileToFilesystem($filename);
        }
    }
}
