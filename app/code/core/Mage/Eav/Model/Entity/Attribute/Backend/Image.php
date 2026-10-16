<?php

/**
 * Backend of an attribute that holds the image of an image entity.
 *
 * An upload goes to the source folder of the entity on the media mount. The resized files are
 * created after the save commits, also for a file name that an import writes. A file that no
 * record uses any more is deleted with its resized files.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

abstract class Mage_Eav_Model_Entity_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * The image entity of the attribute, as a module declares it under global/images/entities.
     */
    abstract public function getImageEntity(): string;

    /**
     * The folder of the images on the media mount, such as catalog/category.
     */
    public function getSource(): string
    {
        return Mage::getSingleton('core/image_config')->getSource($this->getImageEntity());
    }

    /**
     * Every file that the attribute holds, in every store.
     *
     * @return list<string>
     */
    public function getImageFiles(): array
    {
        $adapter = $this->getAttribute()->getEntity()->getReadConnection();
        $files = $adapter->fetchCol($adapter->select()
            ->distinct()
            ->from($this->getTable(), ['value'])
            ->where('attribute_id = ?', (int) $this->getAttribute()->getId()));

        return array_values(array_filter(array_map(strval(...), $files), fn(string $file): bool => $file !== ''));
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(): array
    {
        return \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS;
    }

    /**
     * Save the uploaded file of the attribute, or delete the image when the form asks for it.
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        $name = $this->getAttribute()->getName();
        $value = $object->getData($name);

        if (is_array($value) && !empty($value['delete'])) {
            $oldValue = $object->getOrigData($name);
            $this->saveValue($object, '');
            if (is_string($oldValue) && $oldValue !== '') {
                $this->deleteUnusedFile($oldValue);
            }
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
        } elseif (is_string($value) && $value !== '' && $value !== $object->getOrigData($name)) {
            Mage::getSingleton('core/image_resizer')->createFilesAfterCommit($this->getImageEntity(), [$value]);
        }

        return $this;
    }

    /**
     * Save the file of the uploader in the source folder and set it as the image of $object. The
     * old file is deleted when no record uses it.
     *
     * @return string|null the name of the new file
     * @throws Exception when the file is not an allowed image
     */
    public function saveImage(\Maho\DataObject $object, Mage_Core_Model_File_Uploader $uploader): ?string
    {
        $oldValue = $object->getOrigData($this->getAttribute()->getName());

        $uploader->setAllowedExtensions($this->getAllowedExtensions());
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $uploader->addValidateCallback(Mage_Core_Model_File_Validator_Image::NAME, Mage::getModel('core/file_validator_image'), 'validate');
        $result = $uploader->saveToStorage(Mage::getStorage('media'), $this->getSource());
        $fileName = is_array($result) ? (string) ($result['file'] ?? '') : '';
        if ($fileName === '') {
            return null;
        }

        $this->saveValue($object, $fileName);
        if (is_string($oldValue) && $oldValue !== '' && $oldValue !== $fileName) {
            $this->deleteUnusedFile($oldValue);
        }

        Mage::getSingleton('core/image_resizer')->createFilesAfterCommit($this->getImageEntity(), [$fileName]);

        return $fileName;
    }

    /**
     * Set $value as the image of $object and write it to the database.
     */
    protected function saveValue(\Maho\DataObject $object, string $value): void
    {
        $object->setData($this->getAttribute()->getName(), $value);
        $this->getAttribute()->getEntity()->saveAttribute($object, $this->getAttribute()->getName());
    }

    /**
     * Delete the file $fileName and its resized files when no record uses it in any store. A
     * record with the id $exceptEntityId does not count.
     */
    public function deleteUnusedFile(string $fileName, ?int $exceptEntityId = null): void
    {
        $attribute = $this->getAttribute();
        $adapter = $attribute->getEntity()->getWriteConnection();
        $select = $adapter->select()
            ->from($this->getTable(), ['value_id'])
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->where('value = ?', $fileName)
            ->limit(1);
        if ($exceptEntityId !== null) {
            $select->where($attribute->getEntity()->getEntityIdField() . ' != ?', $exceptEntityId);
        }
        if ($adapter->fetchOne($select) === false) {
            $this->_deleteFile($fileName);
        }
    }

    /**
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function beforeDelete($object)
    {
        $fileName = $object->getData($this->getAttribute()->getName());
        if (is_string($fileName) && $fileName !== '') {
            $this->deleteUnusedFile($fileName, (int) $object->getId());
        }
        return $this;
    }

    /**
     * Delete the file $fileName of the source folder and its resized files.
     */
    protected function _deleteFile(string $fileName): void
    {
        try {
            $mount = Mage::getStorage('media');
            $path = \Maho\Io::getPathWithinMount($mount, $this->getSource(), $fileName);
            if ($path !== null && $mount->fileExists($path)) {
                $mount->delete($path);
            }
            Mage::getSingleton('core/image_resizer')->deleteFiles($this->getImageEntity(), $fileName);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
