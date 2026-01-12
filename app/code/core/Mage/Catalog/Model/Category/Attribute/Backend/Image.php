<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Category_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
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
                $uploader->save(Mage::getBaseDir('media') . DS . 'catalog' . DS . 'category');

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
            $baseDir = Mage::getBaseDir('media') . '/catalog/category';
            $filePath = $baseDir . '/' . $fileName;

            // Delete original file
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete all cached versions - search for all cache files matching this dispersed path
            $cacheDir = Mage::getBaseDir('media') . '/catalog/product/cache';
            if (is_dir($cacheDir)) {
                // Category images can also be cached in product cache
                // Cache structure: /cache/*/image/*/{dispersed_path}
                $pattern = $cacheDir . '/*/image/*/catalog/category/' . ltrim($fileName, '/');
                $cachedFiles = glob($pattern);
                if ($cachedFiles) {
                    foreach ($cachedFiles as $cachedFile) {
                        if (file_exists($cachedFile)) {
                            unlink($cachedFile);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Silently fail - file deletion is not critical
            Mage::logException($e);
        }
    }
}
