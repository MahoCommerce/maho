<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Model_Post_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    public function getAllowedExtensions(): array
    {
        return \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS;
    }

    /**
     * Save uploaded file and set its name to post
     *
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
            if ($oldValue) {
                $this->deleteUnusedFile($object, $oldValue);
            }
            $object->setData($name, '');
            $this->_updateAttributeValue($object, '');
            return $this;
        }

        if (!empty($_FILES[$name]['name'])) {
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
     * Save the file of the uploader under media/blog and set it as the image of the post.
     * The old image file is deleted when the new file has a different name.
     *
     * @return string|null the path of the new file, relative to media/blog
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
        $result = $uploader->save(Mage::getBaseDir('media') . '/blog');

        if (!$result || !isset($result['file'])) {
            return null;
        }

        $fileName = $result['file'];

        // Delete old file if replacing
        if ($oldValue && $oldValue !== $fileName) {
            $this->deleteUnusedFile($object, $oldValue);
        }

        $object->setData($name, $fileName);
        $this->_updateAttributeValue($object, $fileName);

        return $fileName;
    }

    /**
     * Update attribute value in database
     *
     * @param \Maho\DataObject $object
     */
    protected function _updateAttributeValue($object, string $value): void
    {
        $attribute = $this->getAttribute();
        $table = $attribute->getBackend()->getTable();
        $entityIdField = $attribute->getEntity()->getEntityIdField();
        $adapter = $this->_getWriteAdapter();

        $data = [
            'entity_type_id' => $attribute->getEntityTypeId(),
            'attribute_id' => $attribute->getId(),
            // The store_id attribute is the store where the post was created, but the values are global
            'store_id' => Mage_Core_Model_App::ADMIN_STORE_ID,
            $entityIdField => $object->getId(),
            'value' => $value,
        ];

        $adapter->insertOnDuplicate($table, $data, ['value']);
    }

    /**
     * Get write adapter
     *
     * @return Maho\Db\Adapter\AdapterInterface
     */
    protected function _getWriteAdapter()
    {
        return $this->getAttribute()->getEntity()->getWriteConnection();
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
            $this->deleteUnusedFile($object, $fileName);
        }
        return $this;
    }

    /**
     * Delete an image file when no other post uses it
     */
    protected function deleteUnusedFile(\Maho\DataObject $object, string $fileName): void
    {
        $attribute = $this->getAttribute();
        $entityIdField = $attribute->getEntity()->getEntityIdField();
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()
            ->from($attribute->getBackend()->getTable(), ['value_id'])
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->where('value = ?', $fileName)
            ->where("{$entityIdField} != ?", (int) $object->getId())
            ->limit(1);
        if ($adapter->fetchOne($select) === false) {
            $this->_deleteFile($fileName);
        }
    }

    /**
     * Delete physical file from filesystem
     */
    protected function _deleteFile(string $fileName): void
    {
        try {
            $baseDir = Mage::getBaseDir('media') . '/blog';
            $filePath = \Maho\Io::getPathWithinDir($baseDir, $fileName);

            if ($filePath !== false && is_file($filePath)) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
