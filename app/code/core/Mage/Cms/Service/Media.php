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

        $baseName = $filename
            ? $this->getBaseName($filename)
            : pathinfo($result['file'], PATHINFO_FILENAME);

        return $this->writeImage($result['path'], $mount->read($uploadedPath), $baseName, $uploadedPath);
    }

    /**
     * Save the image in $bytes in the folder $folder, with the checks and in the image type of an upload.
     * $name is the name of the new file without its extension. A number follows a name that another file has.
     *
     * @return array{path: string, file: string, size: int, width: int, height: int}
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception when $bytes is not an image
     */
    public function saveImage(string $folder, string $bytes, string $name): array
    {
        $directory = $this->getFolder($folder);

        // The image validator reads and writes a file
        $tempFile = tempnam(sys_get_temp_dir(), 'cms_media_');
        if ($tempFile === false) {
            throw new RuntimeException('Cannot create a temporary file for the image.');
        }
        try {
            file_put_contents($tempFile, $bytes);
            Mage::getModel('core/file_validator_image')->validate($tempFile);
            $bytes = (string) file_get_contents($tempFile);
        } finally {
            @unlink($tempFile);
        }

        return $this->writeImage($directory, $bytes, $this->getBaseName($name));
    }

    private function getBaseName(string $name): string
    {
        return pathinfo(Mage_Core_Model_File_Uploader::getCorrectFileName($name . '.x'), PATHINFO_FILENAME);
    }

    /**
     * Write $bytes as $baseName in the folder $directory, in the image type and the quality of the configuration.
     * $replacePath is the file that held $bytes: the new file can take its name, and it is deleted.
     *
     * @return array{path: string, file: string, size: int, width: int, height: int}
     */
    private function writeImage(string $directory, string $bytes, string $baseName, ?string $replacePath = null): array
    {
        $mount = Mage::helper('cms/wysiwyg_images')->getMount();
        $targetType = Mage::getStoreConfigAsInt('system/media_storage_configuration/image_file_type') ?: IMAGETYPE_WEBP;
        $extension = image_type_to_extension($targetType, false);
        $targetFile = $baseName . '.' . $extension;
        for ($counter = 1; $directory . '/' . $targetFile !== $replacePath && $mount->fileExists($directory . '/' . $targetFile); $counter++) {
            $targetFile = $baseName . '_' . $counter . '.' . $extension;
        }
        $targetPath = $directory . '/' . $targetFile;

        $image = \Maho::getImageManager()->decodeBinary($bytes);
        $encoded = (string) $image->encodeUsingPath($targetPath, quality: Mage::getStoreConfigAsInt('system/media_storage_configuration/image_quality'));
        $mount->write($targetPath, $encoded);
        if ($replacePath !== null && $replacePath !== $targetPath) {
            $mount->delete($replacePath);
        }

        return ['path' => $targetPath, 'file' => $targetFile, 'size' => strlen($encoded), 'width' => $image->width(), 'height' => $image->height()];
    }
}
