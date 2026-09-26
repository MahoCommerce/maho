<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

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

        if (is_array($value) && !empty($value['delete'])) {
            $object->setData($name, '');
            $this->getAttribute()->getEntity()->saveAttribute($object, $name);
            return $this;
        }

        if (!empty($_FILES[$name])) {
            try {
                $this->saveImage($object, Mage::getModel('core/file_uploader', $name));
            } catch (Exception $e) {
                if ($e->getCode() != UPLOAD_ERR_NO_FILE) {
                    Mage::logException($e);
                }
            }
        }

        return $this;
    }

    /**
     * Save the file of the uploader under media/catalog/category and set it as the image
     * of the category, in the store of the category. The old image file is deleted
     * when no category uses it in any store.
     *
     * @return string|null the name of the new file
     * @throws Exception when the file is not an allowed image
     */
    public function saveImage(\Maho\DataObject $object, Mage_Core_Model_File_Uploader $uploader): ?string
    {
        $name = $this->getAttribute()->getName();
        $oldValue = $object->getOrigData($name);

        $validator = Mage::getModel('core/file_validator_image');
        $uploader->setAllowedExtensions($this->getAllowedExtensions());
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $uploader->addValidateCallback(Mage_Core_Model_File_Validator_Image::NAME, $validator, 'validate');
        $uploader->save(Mage::getBaseDir('media') . DS . 'catalog' . DS . 'category');

        $fileName = $uploader->getUploadedFileName();
        if (!$fileName) {
            return null;
        }

        $object->setData($name, $fileName);
        $this->getAttribute()->getEntity()->saveAttribute($object, $name);

        // Delete old file if we're replacing it
        if ($oldValue && $oldValue !== $fileName) {
            $this->deleteUnusedFile((string) $oldValue);
        }

        return $fileName;
    }

    /**
     * Delete an image file when no category uses it as its value in any store
     */
    public function deleteUnusedFile(string $fileName): void
    {
        $attribute = $this->getAttribute();
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $adapter->select()
            ->from($this->getTable(), ['value_id'])
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->where('value = ?', $fileName)
            ->limit(1);
        if ($adapter->fetchOne($select) === false) {
            $this->_deleteFile($fileName);
        }
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
                $pattern = $cacheDir . '/*/image/*/catalog/category/' . ltrim($fileName, '/') . Maho::getConfiguredImageExtension();
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
