<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Product_Attribute_Backend_File extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Get file extension validator
     */
    protected function getExtensionValidator(): Mage_Core_Model_File_Validator_Extension
    {
        /** @var Mage_Core_Model_File_Validator_Extension $validator */
        $validator = Mage::getModel('core/file_validator_extension');

        // Set attribute-specific allowed extensions if configured
        $attribute = $this->getAttribute();
        if ($attribute && $attribute->getData('file_extensions')) {
            $extensions = array_filter(array_map('trim', explode(',', $attribute->getData('file_extensions'))));
            if (!empty($extensions)) {
                $validator->setAllowedExtensions($extensions);
            }
        }

        return $validator;
    }

    /**
     * Validate file extension using validator
     *
     * @throws Mage_Core_Exception
     */
    protected function validateFileExtension(string $fileName): void
    {
        $validator = $this->getExtensionValidator();

        if (!$validator->isValid($fileName)) {
            $messages = $validator->getMessages();
            Mage::throwException(implode(' ', $messages));
        }
    }

    /**
     * Save uploaded file and set its name to product attribute
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
            // Delete the physical file
            if ($oldValue) {
                $this->_deleteFile($oldValue);
            }
            $object->setData($name);
            $this->getAttribute()->getEntity()->saveAttribute($object, $name);
            return $this;
        }

        // Handle both $_FILES[$name] and $_FILES['product'][$name] structures
        $fileData = null;
        if (!empty($_FILES[$name])) {
            $fileData = $name;
        } elseif (!empty($_FILES['product']['name'][$name])) {
            // File is in product array, need to restructure for uploader
            $_FILES[$name] = [
                'name' => $_FILES['product']['name'][$name],
                'type' => $_FILES['product']['type'][$name],
                'tmp_name' => $_FILES['product']['tmp_name'][$name],
                'error' => $_FILES['product']['error'][$name],
                'size' => $_FILES['product']['size'][$name],
            ];
            $fileData = $name;
        }

        if ($fileData) {
            try {
                // Validate file extension first
                $originalFileName = $_FILES[$name]['name'] ?? '';
                if ($originalFileName) {
                    $this->validateFileExtension($originalFileName);
                }

                $uploader  = Mage::getModel('core/file_uploader', $name);
                // Only set allowed extensions if specifically configured
                $validator = $this->getExtensionValidator();
                $allowedExtensions = $validator->getAllowedExtensions();
                if ($allowedExtensions !== null) {
                    $uploader->setAllowedExtensions($allowedExtensions);
                }
                $uploader->setAllowRenameFiles(true);
                $uploader->setFilesDispersion(true);
                $uploader->save(Mage::getBaseDir('media') . DS . 'catalog' . DS . 'files');

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
                    // Re-throw to show error to user
                    throw $e;
                }
            }
        }

        return $this;
    }

    /**
     * After load method - no special handling needed for files
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterLoad($object)
    {
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
     * Delete physical file from filesystem
     */
    protected function _deleteFile(string $fileName): void
    {
        try {
            $baseDir = Mage::getBaseDir('media') . DS . 'catalog' . DS . 'files';
            $filePath = $baseDir . DS . $fileName;

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            // Silently fail - file deletion is not critical
            Mage::logException($e);
        }
    }
}
