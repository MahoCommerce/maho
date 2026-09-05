<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

class Mage_ImportExport_Model_Import_Uploader extends Mage_Core_Model_File_Uploader
{
    protected $_tmpDir  = '';
    protected $_destDir = '';
    private bool $_validated = false;
    protected $_allowedMimeTypes = [
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'png' => 'image/png',
    ];
    public const DEFAULT_FILE_TYPE = 'application/octet-stream';

    /**
     * Mage_ImportExport_Model_Import_Uploader constructor.
     * @param string|null $filePath
     */
    public function __construct($filePath = null)
    {
        if (!is_null($filePath)) {
            $this->_setUploadFile($filePath);
        }
    }

    /**
     * Initiate uploader defoult settings
     */
    public function init()
    {
        $this->setAllowRenameFiles(true);
        $this->setAllowCreateFolders(true);
        $this->setFilesDispersion(true);
        $this->setAllowedExtensions(array_keys($this->_allowedMimeTypes));
        $this->addValidateCallback(
            'catalog_product_image',
            Mage::helper('catalog/image'),
            'validateUploadFile',
        );
        $this->addValidateCallback(
            Mage_Core_Model_File_Validator_Image::NAME,
            Mage::getModel('core/file_validator_image'),
            'validate',
        );
        $this->_uploadType = self::SINGLE_STYLE;
    }

    /**
     * Proceed moving a file from TMP to destination folder
     *
     * @param string $fileName
     * @return array
     * @throws Exception
     */
    public function move($fileName)
    {
        $filePath = realpath($this->getTmpDir() . DS . $fileName);
        if ($filePath === false) {
            Mage::throwException("File '{$fileName}' was not found in " . $this->getTmpDir());
        }
        // The image validator re-samples the file it checks in place, so work on a copy and leave the source untouched
        $copy = Mage_ImportExport_Model_Import::getWorkingDir() . uniqid('upload-', true) . '-' . basename($filePath);
        if (!copy($filePath, $copy)) {
            Mage::throwException("File '{$fileName}' could not be copied to the working folder");
        }
        try {
            $this->_setUploadFile($copy);
            $this->_file['name'] = basename($filePath);
            $this->_validateFile();
            $this->_validated = true;
            // The validator re-samples the copy, so only the validated bytes can match a file stored by an earlier run
            $correctName = strtolower(self::getCorrectFileName($this->_file['name']));
            $existing = self::getDispretionPath($correctName) . DS . $correctName;
            $destination = $this->getDestDir() . $existing;
            if (is_file($destination) && md5_file($copy) === md5_file($destination)) {
                return ['path' => $this->getDestDir(), 'file' => str_replace(DS, '/', $existing), 'name' => $correctName];
            }
            $result = $this->save($this->getDestDir());
        } finally {
            $this->_validated = false;
            if (is_file($copy)) {
                unlink($copy);
            }
        }
        $result['name'] = self::getCorrectFileName($result['name']);
        return $result;
    }

    /**
     * Prepare information about the file for moving
     *
     * @param string $filePath
     */
    protected function _setUploadFile($filePath)
    {
        if (!is_readable($filePath)) {
            Mage::throwException("File '{$filePath}' was not found or has read restriction.");
        }
        $this->_file = $this->_readFileInfo($filePath);
    }

    /**
     * Reads file info
     *
     * @param string $filePath
     * @return array
     */
    protected function _readFileInfo($filePath)
    {
        $fileInfo = pathinfo($filePath);

        return [
            'name' => $fileInfo['basename'],
            'type' => $this->_getMimeTypeByExt($fileInfo['extension']),
            'tmp_name' => $filePath,
            'error' => 0,
            'size' => filesize($filePath),
        ];
    }

    /**
     * Validate uploaded file by type and etc.
     */
    #[\Override]
    protected function _validateFile()
    {
        if ($this->_validated) {
            return;
        }
        $filePath = $this->_file['tmp_name'];
        if (is_readable($filePath)) {
            $this->_fileExists = true;
        } else {
            $this->_fileExists = false;
        }

        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!$this->checkAllowedExtension($fileExtension)) {
            throw new Exception('Disallowed file type.');
        }
        //run validate callbacks
        foreach ($this->_validateCallbacks as $params) {
            if (is_object($params['object']) && method_exists($params['object'], $params['method'])) {
                $params['object']->{$params['method']}($filePath);
            }
        }
    }

    /**
     * Returns file MIME type by extension
     *
     * @param string $ext
     * @return string
     */
    protected function _getMimeTypeByExt($ext)
    {
        if (array_key_exists($ext, $this->_allowedMimeTypes)) {
            return $this->_allowedMimeTypes[$ext];
        }
        return '';
    }

    /**
     * Obtain TMP file path prefix
     *
     * @return string
     */
    public function getTmpDir()
    {
        return $this->_tmpDir;
    }

    /**
     * Set TMP file path prefix
     *
     * @param string $path
     * @return bool
     */
    public function setTmpDir($path)
    {
        if (is_string($path) && is_readable($path)) {
            $this->_tmpDir = $path;
            return true;
        }
        return false;
    }

    /**
     * Obtain destination file path prefix
     *
     * @return string
     */
    public function getDestDir()
    {
        return $this->_destDir;
    }

    /**
     * Set destination file path prefix
     *
     * @param string $path
     * @return bool
     */
    public function setDestDir($path)
    {
        if (is_string($path) && is_writable($path)) {
            $this->_destDir = $path;
            return true;
        }
        return false;
    }

    /**
     * Move files from TMP folder into destination folder
     *
     * @param string $tmpPath
     * @param string $destPath
     * @return bool
     */
    #[\Override]
    protected function _moveFile($tmpPath, $destPath)
    {
        $sourceFile = realpath($tmpPath);
        if ($sourceFile !== false) {
            return copy($sourceFile, $destPath);
        }
        return false;
    }
}
