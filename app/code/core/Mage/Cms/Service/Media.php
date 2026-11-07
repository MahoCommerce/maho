<?php

/**
 * Find, upload and delete the files of the media library, for the admin media library and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Service_Media
{
    /**
     * Return the mount path of the folder $folder of the media library. A folder outside the root of the library does not exist.
     *
     * @throws Mage_Core_Exception_Input
     */
    public function getFolder(string $folder): string
    {
        return Mage::helper('cms/wysiwyg_images')->resolveFolder($folder)
            ?? throw new Mage_Core_Exception_Input()->addError('folder', Mage::helper('cms')->__('Invalid folder path'));
    }

    /**
     * Return the mount path of the file $name in the folder $directory. A name with a directory part names no file.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getFile(string $directory, string $name): string
    {
        $mount = Mage::helper('cms/wysiwyg_images')->getMount();
        $path = $name === '' || basename(str_replace('\\', '/', $name)) !== $name
            ? null
            : \Maho\Io::getPathWithinMount($mount, $directory, $name);
        if ($path === null || !$mount->fileExists($path)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('cms')->__('File not found.'));
        }
        return $path;
    }

    /**
     * Delete the file $name of the folder $directory, with its thumbnail and its resized copies.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function delete(string $directory, string $name): void
    {
        Mage::helper('cms/wysiwyg_images')->getStorage()->deleteFile($this->getFile($directory, $name));
    }

    /**
     * Save the image of the upload field $field in the folder $folder, in the image type of the configuration.
     * $filename is the name of the new file without its extension. A number follows a name that another file has.
     *
     * @return array{path: string, file: string, size: int, width: int, height: int}
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception
     */
    public function upload(string $folder, string $field, ?string $filename = null): array
    {
        $helper = Mage::helper('cms/wysiwyg_images');
        $mount = $helper->getMount();
        $directory = $this->getFolder($folder);

        $result = $helper->getStorage()->uploadFile($directory, 'image', $field);
        $uploadedPath = $result['path'] . '/' . $result['file'];

        $targetType = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_file_type') ?: IMAGETYPE_WEBP;
        $extension = image_type_to_extension($targetType, false);
        $baseName = $filename
            ? pathinfo(Mage_Core_Model_File_Uploader::getCorrectFileName($filename . '.' . $extension), PATHINFO_FILENAME)
            : pathinfo($result['file'], PATHINFO_FILENAME);
        $targetFile = $baseName . '.' . $extension;
        for ($counter = 1; $result['path'] . '/' . $targetFile !== $uploadedPath && $mount->fileExists($result['path'] . '/' . $targetFile); $counter++) {
            $targetFile = $baseName . '_' . $counter . '.' . $extension;
        }
        $targetPath = $result['path'] . '/' . $targetFile;

        $image = \Maho::getImageManager()->decodeBinary($mount->read($uploadedPath));
        $encoded = (string) $image->encodeUsingPath($targetPath, quality: Mage::getStoreConfigAsInt('system/media_storage_configuration/image_quality'));
        $mount->write($targetPath, $encoded);
        if ($uploadedPath !== $targetPath) {
            $mount->delete($uploadedPath);
        }

        return ['path' => $targetPath, 'file' => $targetFile, 'size' => strlen($encoded), 'width' => $image->width(), 'height' => $image->height()];
    }
}
