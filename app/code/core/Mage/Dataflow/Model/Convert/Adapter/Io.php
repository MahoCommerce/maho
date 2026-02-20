<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

            $isError = false;

            $ioConfig = $this->getVars();
            switch (strtolower($this->getVar('type', 'file'))) {
                case 'file':
                    $path = \Symfony\Component\Filesystem\Path::makeAbsolute(
                        $this->getVar('path'),
                        Mage::getBaseDir(),
                    );

                    // Validate path is within allowed directories (var/export or var/import)
                    $varDir = Mage::getBaseDir('var');
                    $isInExport = \Maho\Io::allowedPath($path, $varDir . DS . 'export');
                    $isInImport = \Maho\Io::allowedPath($path, $varDir . DS . 'import');
                    if (!$isInExport && !$isInImport) {
                        Mage::throwException(
                            Mage::helper('dataflow')->__('Path "%s" is not allowed. Files must be in var/export or var/import.', $ioConfig['path']),
                        );
                    }

                    $this->_resource->checkAndCreateFolder($path);

                    $realPath = realpath($path);

                    if (!$isError && $realPath === false) {
                        $message = Mage::helper('dataflow')->__('The destination folder "%s" does not exist or there is no access to create it.', $ioConfig['path']);
                        Mage::throwException($message);
                    } elseif (!$isError && !is_dir($realPath)) {
                        $message = Mage::helper('dataflow')->__('Destination folder "%s" is not a directory.', $realPath);
                        Mage::throwException($message);
                    } elseif (!$isError) {
                        if ($forWrite && !is_writable($realPath)) {
                            $message = Mage::helper('dataflow')->__('Destination folder "%s" is not writable.', $realPath);
                            Mage::throwException($message);
                        } else {
                            $ioConfig['path'] = rtrim($realPath, DS);
                        }
                    }
                    break;
                default:
                    $ioConfig['path'] = rtrim($this->getVar('path'), '/');
                    break;
            }

            if ($isError) {
                return false;
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
                $message .= Mage::helper('dataflow')->__('<a href="%s" target="_blank">Link</a>', $this->getVar('link'));
            }
            $this->addException($message);
        }
        return $this;
    }
}
