<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Product_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    public function getAllowedExtensions(): array
    {
        return Varien_Io_File::ALLOWED_IMAGES_EXTENSIONS;
    }

    /**
     * Save uploaded file and set its name to product attribute
     * @param Varien_Object $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        $name  = $this->getAttribute()->getName();
        $value = $object->getData($name);

        if (is_array($value) && !empty($value['delete'])) {
            $object->setData($name, '');
            $this->getAttribute()->getEntity()->saveAttribute($object, $name);
            return $this;
        }

        if (!empty($_FILES[$name])) {
            try {
                $validator = Mage::getModel('core/file_validator_image');
                $uploader  = Mage::getModel('core/file_uploader', $name);
                $uploader->setAllowedExtensions($this->getAllowedExtensions());
                $uploader->setAllowRenameFiles(true);
                $uploader->setFilesDispersion(true);
                $uploader->addValidateCallback(Mage_Core_Model_File_Validator_Image::NAME, $validator, 'validate');
                $uploader->save(Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product');

                $fileName = $uploader->getUploadedFileName();
                if ($fileName) {
                    $object->setData($name, $fileName);
                    $this->getAttribute()->getEntity()->saveAttribute($object, $name);
                }
            } catch (Exception $e) {
                if ($e->getCode() != UPLOAD_ERR_NO_FILE) {
                    Mage::logException($e);
                }
            }
        }

        return $this;
    }
}
