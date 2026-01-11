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
                $result = $uploader->save(Mage::getBaseDir('media') . DS . 'blog');

                if ($result && isset($result['file'])) {
                    $fileName = $result['file'];

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
     * Delete physical file from filesystem
     */
    protected function _deleteFile(string $fileName): void
    {
        try {
            $baseDir = Mage::getBaseDir('media') . DS . 'blog';
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
