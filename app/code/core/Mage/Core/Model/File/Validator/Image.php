<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_File_Validator_Image
{
    public const NAME = 'isImage';

    protected ?string $_originalFileName = null;

    protected $_allowedImageTypes = [
        IMAGETYPE_WEBP,
        IMAGETYPE_AVIF,
        IMAGETYPE_JPEG,
        IMAGETYPE_GIF,
        IMAGETYPE_JPEG2000,
        IMAGETYPE_PNG,
        IMAGETYPE_ICO,
        IMAGETYPE_TIFF_II,
        IMAGETYPE_TIFF_MM,
    ];

    /**
     * Setter for original filename
     *
     * @param string $filename
     * @return $this
     */
    public function setOriginalFileName($filename)
    {
        $this->_originalFileName = $filename;
        return $this;
    }

    /**
     * Setter for allowed image types
     *
     * @return $this
     */
    public function setAllowedImageTypes(array $imageFileExtensions = [])
    {
        $map = [
            'webp' => [IMAGETYPE_WEBP],
            'avif' => [IMAGETYPE_AVIF],
            'tif' => [IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM],
            'tiff' => [IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM],
            'jpg' => [IMAGETYPE_JPEG, IMAGETYPE_JPEG2000],
            'jpe' => [IMAGETYPE_JPEG, IMAGETYPE_JPEG2000],
            'jpeg' => [IMAGETYPE_JPEG, IMAGETYPE_JPEG2000],
            'gif' => [IMAGETYPE_GIF],
            'png' => [IMAGETYPE_PNG],
            'ico' => [IMAGETYPE_ICO],
            'apng' => [IMAGETYPE_PNG],
            'svg' => [], // SVG is XML-based, not a raster format - handled by separate validator
        ];

        $this->_allowedImageTypes = [];

        foreach ($imageFileExtensions as $extension) {
            if (isset($map[$extension])) {
                foreach ($map[$extension] as $imageType) {
                    $this->_allowedImageTypes[$imageType] = $imageType;
                }
            }
        }

        return $this;
    }

    /**
     * Validation callback for checking if file is image
     * Destroy malicious code in image by reprocessing
     *
     * @param  string $filePath Path to temporary uploaded file
     * @throws Mage_Core_Exception
     */
    public function validate($filePath)
    {
        // Skip SVG files - they are handled by Mage_Core_Model_File_Validator_Svg
        // Use original filename if available (temp files don't have extensions)
        $filenameToCheck = $this->_originalFileName ?: $filePath;
        $extension = strtolower(pathinfo($filenameToCheck, PATHINFO_EXTENSION));

        if ($extension === 'svg') {
            return null;
        }

        [$imageWidth, $imageHeight, $fileType] = \Maho\Io::getImageSize($filePath);
        if ($fileType) {
            if ($fileType === IMAGETYPE_ICO) {
                return null;
            }
            if ($this->isImageType($fileType)) {
                $imageQuality = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_quality');
                //replace tmp image with re-sampled copy to exclude images with malicious data
                $image = imagecreatefromstring(file_get_contents($filePath));
                if ($image !== false) {
                    $img = imagecreatetruecolor($imageWidth, $imageHeight);
                    imagealphablending($img, false);
                    imagecopyresampled($img, $image, 0, 0, 0, 0, $imageWidth, $imageHeight, $imageWidth, $imageHeight);
                    imagesavealpha($img, true);

                    switch ($fileType) {
                        case IMAGETYPE_GIF:
                            $transparencyIndex = imagecolortransparent($image);
                            if ($transparencyIndex >= 0) {
                                imagecolortransparent($img, $transparencyIndex);
                                for ($y = 0; $y < $imageHeight; ++$y) {
                                    for ($x = 0; $x < $imageWidth; ++$x) {
                                        if (((imagecolorat($img, $x, $y) >> 24) & 0x7F)) {
                                            imagesetpixel($img, $x, $y, $transparencyIndex);
                                        }
                                    }
                                }
                            }
                            if (!imageistruecolor($image)) {
                                imagetruecolortopalette($img, false, imagecolorstotal($image));
                            }
                            imagegif($img, $filePath);
                            break;
                        case IMAGETYPE_JPEG:
                            imagejpeg($img, $filePath, $imageQuality);
                            break;
                        case IMAGETYPE_WEBP:
                            imagewebp($img, $filePath, $imageQuality);
                            break;
                        case IMAGETYPE_AVIF:
                            imageavif($img, $filePath, $imageQuality);
                            break;
                        case IMAGETYPE_PNG:
                            imagepng($img, $filePath);
                            break;
                        default:
                            break;
                    }

                    return null;
                }
                throw Mage::exception('Mage_Core', Mage::helper('core')->__('Invalid image.'));
            }
        }
        throw Mage::exception('Mage_Core', Mage::helper('core')->__('Invalid MIME type.'));
    }

    /**
     * Returns is image by image type
     * @param int $nImageType
     * @return bool
     */
    protected function isImageType($nImageType)
    {
        return in_array($nImageType, $this->_allowedImageTypes);
    }
}
