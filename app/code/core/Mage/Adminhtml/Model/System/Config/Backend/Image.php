<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Backend_Image extends Mage_Adminhtml_Model_System_Config_Backend_File
{
    #[\Override]
    protected function _getAllowedExtensions(): array
    {
        return \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS;
    }

    #[\Override]
    protected function addValidators(Mage_Core_Model_File_Uploader $uploader)
    {
        parent::addValidators($uploader);

        $allowedExtensions = $this->_getAllowedExtensions();

        // Set valid MIME types from allowed extensions
        $mimeTypes = Mage::helper('uploader/file')->getMimeTypeFromExtensionList($allowedExtensions);
        $uploader->setValidMimeTypes($mimeTypes);

        // Add image validator for raster images
        $validator = Mage::getModel('core/file_validator_image');
        $validator->setAllowedImageTypes($allowedExtensions);

        // Pass original filename to validator so it can check the extension
        // (temp files don't have extensions)
        $originalFileName = $_FILES['groups']['name'][$this->getGroupId()]['fields'][$this->getField()]['value'] ?? null;
        if ($originalFileName) {
            $validator->setOriginalFileName($originalFileName);
        }

        $uploader->addValidateCallback(Mage_Core_Model_File_Validator_Image::NAME, $validator, 'validate');

        // Add SVG validator if SVG is allowed
        if (in_array('svg', $allowedExtensions)) {
            $svgValidator = Mage::getModel('core/file_validator_svg');
            $uploader->addValidateCallback(Mage_Core_Model_File_Validator_Svg::NAME, $svgValidator, 'validate');
        }
    }
}
