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
                $this->_deleteFile($oldValue);
            }
            $object->setData($name, '');
            $this->_updateAttributeValue($object, '');
            return $this;
        }

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

                    // Delete old file if replacing
                    if ($oldValue && $oldValue !== $fileName) {
                        $this->_deleteFile($oldValue);
                    }

                    $object->setData($name, $fileName);
                    $this->_updateAttributeValue($object, $fileName);
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
            'store_id' => $object->getStoreId(),
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
