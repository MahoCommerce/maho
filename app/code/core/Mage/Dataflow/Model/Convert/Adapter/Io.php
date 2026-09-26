<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

class Mage_Dataflow_Model_Convert_Adapter_Io extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    /**
     * @return \Maho\Io\IoInterface|false
     */
    #[\Override]
    public function getResource($forWrite = false)
    {
        if (!$this->_resource) {
            $type = $this->getVar('type', 'file');
            $className = '\Maho\Io\\' . ucwords($type);
            $this->_resource = new $className();

            $ioConfig = $this->getVars();
            switch (strtolower($this->getVar('type', 'file'))) {
                case 'file':
                    $path = \Symfony\Component\Filesystem\Path::makeAbsolute(
                        $this->getVar('path'),
                        Mage::getBaseDir(),
                    );

                    // Validate path is within allowed directories (var/export or var/import)
                    $varDir = Mage::getBaseDir('var');
                    $isInExport = \Maho\Io::getPathWithinDir($varDir . DS . 'export', $path) !== null;
                    $isInImport = \Maho\Io::getPathWithinDir($varDir . DS . 'import', $path) !== null;
                    if (!$isInExport && !$isInImport) {
                        Mage::throwException(
                            Mage::helper('dataflow')->__('Path "%s" is not allowed. Files must be in var/export or var/import.', $ioConfig['path']),
                        );
                    }

                    $this->_resource->checkAndCreateFolder($path);

                    $realPath = realpath($path);

                    if ($realPath === false) {
                        $message = Mage::helper('dataflow')->__('The destination folder "%s" does not exist or there is no access to create it.', $ioConfig['path']);
                        Mage::throwException($message);
                    } elseif (!is_dir($realPath)) {
                        $message = Mage::helper('dataflow')->__('Destination folder "%s" is not a directory.', $realPath);
                        Mage::throwException($message);
                    } elseif ($forWrite && !is_writable($realPath)) {
                        $message = Mage::helper('dataflow')->__('Destination folder "%s" is not writable.', $realPath);
                        Mage::throwException($message);
                    } else {
                        $ioConfig['path'] = rtrim($realPath, DS);
                    }
                    break;
                default:
                    $ioConfig['path'] = rtrim($this->getVar('path'), '/');
                    break;
            }

            try {
                $this->_resource->open($ioConfig);
            } catch (Exception $e) {
                $message = Mage::helper('dataflow')->__('An error occurred while opening file: "%s".', $e->getMessage());
                Mage::throwException($message);
            }
        }
        return $this->_resource;
    }

    /**
     * Load data
     *
     * @return $this
     */
    #[\Override]
    public function load()
    {
        if ($this->isStorageType()) {
            $this->loadFromStorage();
            return $this;
        }
        if (!$this->getResource()) {
            return $this;
        }

        $batchModel = Mage::getSingleton('dataflow/batch');
        $destFile = $batchModel->getIoAdapter()->getFile(true);

        $result = $this->getResource()->read($this->getVar('filename'), $destFile);
        $filename = $this->getResource()->pwd() . '/' . $this->getVar('filename');
        if ($result === false) {
            $message = Mage::helper('dataflow')->__('Could not load file: "%s".', $filename);
            Mage::throwException($message);
        } else {
            $message = Mage::helper('dataflow')->__('Loaded successfully: "%s".', $filename);
            $this->addException($message);
        }

        $this->setData($result);
        return $this;
    }

    /**
     * Save result to destination file from temporary
     *
     * @return $this
     */
    #[\Override]
    public function save()
    {
        if ($this->isStorageType()) {
            $this->saveToStorage();
            return $this;
        }
        if (!$this->getResource(true)) {
            return $this;
        }

        $batchModel = Mage::getSingleton('dataflow/batch');

        $dataFile = $batchModel->getIoAdapter()->getFile(true);

        $filename = $this->getVar('filename');

        $result   = $this->getResource()->write($filename, $dataFile, 0777);

        if ($result === false) {
            $message = Mage::helper('dataflow')->__('Could not save file: %s.', $filename);
            Mage::throwException($message);
        } else {
            $message = Mage::helper('dataflow')->__('Saved successfully: "%s" [%d byte(s)].', $filename, $batchModel->getIoAdapter()->getFileSize());
            if ($this->getVar('link')) {
                $message .= ' ' . Mage::helper('dataflow')->__('Link: %s', $this->getVar('link'));
            }
            $this->addException($message);
        }
        return $this;
    }

    /**
     * A file type reads and writes the exports or the imports mount. FTP and SFTP stay on Maho\Io.
     */
    protected function isStorageType(): bool
    {
        return strtolower((string) $this->getVar('type', 'file')) === 'file';
    }

    /**
     * The mount and the path on it of the file of this action
     *
     * @return array{0: \Maho\Storage\Mount, 1: string}
     */
    protected function getStorageFile(): array
    {
        $location = Mage::helper('dataflow')->getStorageLocation((string) $this->getVar('path'));
        $path = $location === null ? null : \Maho\Io::getPathWithinMount($location[0], $location[1], (string) $this->getVar('filename'));
        if ($location === null || $path === null) {
            Mage::throwException(
                Mage::helper('dataflow')->__('Path "%s" is not allowed. Files must be in var/export or var/import.', $this->getVar('path')),
            );
        }
        return [$location[0], $path];
    }

    protected function loadFromStorage(): void
    {
        [$mount, $path] = $this->getStorageFile();
        $displayName = rtrim((string) $this->getVar('path'), '/') . '/' . $this->getVar('filename');
        Mage::helper('dataflow')->copyToBatchFile($mount, $path, $displayName);

        $this->addException(Mage::helper('dataflow')->__('Loaded successfully: "%s".', $displayName));
        $this->setData(true);
    }

    /**
     * Put the export on the mount in one step, so nobody downloads half a file
     */
    protected function saveToStorage(): void
    {
        [$mount, $path] = $this->getStorageFile();
        $batchIo = Mage::getSingleton('dataflow/batch')->getIoAdapter();
        $filename = (string) $this->getVar('filename');

        $source = fopen($batchIo->getFile(true), 'rb');
        if ($source === false) {
            Mage::throwException(Mage::helper('dataflow')->__('Could not save file: %s.', $filename));
        }
        try {
            $mount->moveAtomic($path, $source);
        } catch (\League\Flysystem\FilesystemException) {
            Mage::throwException(Mage::helper('dataflow')->__('Could not save file: %s.', $filename));
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        $message = Mage::helper('dataflow')->__('Saved successfully: "%s" [%d byte(s)].', $filename, $batchIo->getFileSize());
        if ($this->getVar('link')) {
            $message .= ' ' . Mage::helper('dataflow')->__('Link: %s', $this->getVar('link'));
        }
        $this->addException($message);
    }
}
