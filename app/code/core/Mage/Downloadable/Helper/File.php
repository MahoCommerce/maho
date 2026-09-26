<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_Helper_File extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_Downloadable';

    /**
     * Checking file for moving and move it
     *
     * @param string $baseTmpPath Temporary directory on the media mount
     * @param string $basePath Final directory on the media mount
     * @param array $file
     * @return string
     */
    public function moveFileFromTmp($baseTmpPath, $basePath, $file)
    {
        if (isset($file[0])) {
            $fileName = $file[0]['file'];
            if ($file[0]['status'] == 'new') {
                try {
                    $fileName = $this->_moveFileFromTmp(
                        $baseTmpPath,
                        $basePath,
                        $file[0]['file'],
                    );
                } catch (Exception) {
                    Mage::throwException(Mage::helper('downloadable')->__('An error occurred while saving the file(s).'));
                }
            }
            return $fileName;
        }
        return '';
    }

    /**
     * Move file from tmp path to base path, both on the media mount
     *
     * @param string $baseTmpPath
     * @param string $basePath
     * @param string $file
     * @return string
     */
    protected function _moveFileFromTmp($baseTmpPath, $basePath, $file)
    {
        if (strrpos($file, '.tmp') == strlen($file) - 4) {
            $file = substr($file, 0, -4);
        }
        $file = str_replace('\\', '/', $file);
        $mount = Mage::getStorage('media');
        $sourcePath = \Maho\Io::getPathWithinMount($mount, $baseTmpPath, $file);
        $destPath = \Maho\Io::getPathWithinMount($mount, $basePath, $file);
        if ($sourcePath === null || $destPath === null || !$mount->fileExists($sourcePath)) {
            throw new Exception('Detected malicious path or filename input.');
        }

        $destFile = dirname($file) . '/' . Mage_Core_Model_File_Uploader::getNewFileNameOnMount($mount, $destPath);
        $mount->move($sourcePath, \Maho\File\Uploader::joinPath($basePath, $destFile));

        return $destFile;
    }

    /**
     * Size of a stored file below $directory on the media mount. Null when the name is empty,
     * leaves $directory, or names no file.
     */
    public function getStoredFileSize(string $directory, ?string $file): ?int
    {
        if ($file === null || $file === '') {
            return null;
        }
        $mount = Mage::getStorage('media');
        $path = \Maho\Io::getPathWithinMount($mount, $directory, $file);
        if ($path === null) {
            return null;
        }
        try {
            return $mount->fileSize($path);
        } catch (\League\Flysystem\FilesystemException) {
            return null;
        }
    }

    /**
     * Return full path to file
     *
     * @param string $path
     * @param string|null $file
     * @return string
     */
    public function getFilePath($path, $file)
    {
        if ($file === null || $file === '') {
            return $path . DS;
        }

        $file = $this->_prepareFileForPath($file);
        $contained = \Maho\Io::getPathWithinDir($path, ltrim($file, DS));

        // A name that leaves the base directory yields the bare directory, which is never a file
        return $contained ?? $path . DS;
    }

    /**
     * Replace slashes with directory separator
     *
     * @param string $file
     * @return string
     */
    protected function _prepareFileForPath($file)
    {
        return str_replace('/', DS, $file);
    }

    /**
     * Return file name form file path
     *
     * @param string $pathFile
     * @return string
     */
    public function getFileFromPathFile($pathFile)
    {
        $file = '';

        $file = substr($pathFile, strrpos($this->_prepareFileForPath($pathFile), DS) + 1);

        return $file;
    }

    /**
     * Read the MIME type from the extension of $filePath.
     * Mage_Downloadable_Helper_Download::getContentType() calls this only when
     * mime_content_type() reads no type from the file itself.
     */
    public function getFileType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return Mage::helper('core')->getMimeTypes([$extension])[0] ?? 'application/octet-stream';
    }
}
