<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Post_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    public function getAllowedExtensions(): array
    {
        return \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS;
    }

    #[\Override]
    public function afterSave($object)
    {
        $name  = $this->getAttribute()->getName();
        $value = $object->getData($name);
        $oldValue = $object->getOrigData($name);

        // Handle delete
        if (is_array($value) && !empty($value['delete'])) {
            if ($oldValue) {
                $this->_deleteFile($oldValue);
            }
            $this->_saveAttributeValue($object, '');
            return $this;
        }

        // Handle file upload
        if (!empty($_FILES[$name]['name'])) {
            try {
                $validator = Mage::getModel('core/file_validator_image');
                $uploader  = Mage::getModel('core/file_uploader', $name);
                $uploader->setAllowedExtensions($this->getAllowedExtensions());
                $uploader->setAllowRenameFiles(true);
                $uploader->setFilesDispersion(false);
                $uploader->addValidateCallback(Mage_Core_Model_File_Validator_Image::NAME, $validator, 'validate');
                $result = $uploader->save(Mage::getBaseDir('media') . '/blog');

                if ($result && isset($result['file'])) {
                    $fileName = $result['file'];

                    if ($oldValue && $oldValue !== $fileName) {
                        $this->_deleteFile($oldValue);
                    }

                    $this->_saveAttributeValue($object, $fileName);
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
     * Save attribute value to database
     * Uses direct SQL to avoid UNIQUE constraint violations from double-save
     */
    protected function _saveAttributeValue($object, string $value): void
    {
        $adapter = $object->getResource()->getWriteConnection();
        $table = $this->getAttribute()->getBackend()->getTable();
        $storeId = $object->getStoreId();
        $entityId = $object->getId();
        $attributeId = $this->getAttribute()->getId();

        // Find actual store_id in database (could be 0 or current store)
        $existingStoreId = $adapter->fetchOne(
            $adapter->select()
                ->from($table, 'store_id')
                ->where('entity_id = ?', $entityId)
                ->where('attribute_id = ?', $attributeId)
                ->where('store_id IN (?)', [$storeId, 0])
                ->order('store_id DESC')
        );

        if ($existingStoreId !== false) {
            // Row exists, UPDATE it
            $adapter->update(
                $table,
                ['value' => $value],
                [
                    'entity_id = ?' => $entityId,
                    'attribute_id = ?' => $attributeId,
                    'store_id = ?' => $existingStoreId
                ]
            );
        } else {
            // No row exists, INSERT it
            $adapter->insert(
                $table,
                [
                    'entity_type_id' => $object->getEntityTypeId(),
                    'attribute_id' => $attributeId,
                    'store_id' => 0,
                    'entity_id' => $entityId,
                    'value' => $value
                ]
            );
        }

        $object->setData($this->getAttribute()->getName(), $value);
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
     * Delete physical file from filesystem
     */
    protected function _deleteFile(string $fileName): void
    {
        try {
            $baseDir = Mage::getBaseDir('media') . '/blog';
            $filePath = $baseDir . '/' . $fileName;

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
