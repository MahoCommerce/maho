<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Category_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /** Directory of category images on the media mount. */
    public const STORAGE_PATH = 'catalog/category';

    public function getAllowedExtensions(): array
    {
        return \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS;
    }

    /**
     * Save uploaded file and set its name to category attribute
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        $name  = $this->getAttribute()->getName();
        $value = $object->getData($name);
        $oldValue = $object->getOrigData($name);

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
                $uploader->setFilesDispersion(false);
                $uploader->addValidateCallback(Mage_Core_Model_File_Validator_Image::NAME, $validator, 'validate');
                $uploader->saveToStorage(Mage::getStorage('media'), self::STORAGE_PATH);

                $fileName = $uploader->getUploadedFileName();
                if ($fileName) {
                    // Delete old file if we're replacing it
                    if ($oldValue && $oldValue !== $fileName) {
                        $this->_deleteFile($oldValue);
                    }

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

    /**
     * Before delete - remove the physical file
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function beforeDelete($object)
    {
        $fileName = $object->getData($this->getAttribute()->getName());
        if ($fileName) {
            $this->_deleteFile($fileName);
        }
        return $this;
    }

    /**
     * Delete physical file from filesystem and its cached versions
     */
    protected function _deleteFile(string $fileName): void
    {
        try {
            $mount = Mage::getStorage('media');
            $filePath = \Maho\Io::getPathWithinMount($mount, self::STORAGE_PATH, $fileName);
            if ($filePath !== null && $mount->fileExists($filePath)) {
                $mount->delete($filePath);
            }

            // A theme can resize a category image through the product image cache
            Mage::getSingleton('catalog/product_image_variant')
                ->deleteCachedCopies('/catalog/category/' . ltrim($fileName, '/'));
        } catch (Exception $e) {
            // Silently fail - file deletion is not critical
            Mage::logException($e);
        }
    }
}
