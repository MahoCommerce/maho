<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

abstract class Mage_Dataflow_Model_Convert_Parser_Abstract extends Mage_Dataflow_Model_Convert_Container_Abstract implements Mage_Dataflow_Model_Convert_Parser_Interface
{
    /**
     * Dataflow batch model
     *
     * @var Mage_Dataflow_Model_Batch|null
     */
    protected $_batch;

    protected ?Mage_Dataflow_Model_Batch_Export $_batchExport = null;

    protected ?Mage_Dataflow_Model_Batch_Import $_batchImport = null;

    /**
     * Count parse rows
     *
     * @var int
     */
    protected $_countRows = 0;

    /**
     * Retrieve Batch model singleton
     *
     * @return Mage_Dataflow_Model_Batch
     */
    public function getBatchModel()
    {
        $this->_batch ??= Mage::getSingleton('dataflow/batch');
        return $this->_batch;
    }

    public function getBatchExportModel(): Mage_Dataflow_Model_Batch_Export
    {
        return $this->_batchExport ??= Mage::getModel('dataflow/batch_export');
    }

    public function getBatchImportModel(): Mage_Dataflow_Model_Batch_Import
    {
        return $this->_batchImport ??= Mage::getModel('dataflow/batch_import');
    }

    #[\Deprecated(message: 'the uploaded files are on the imports mount, use Mage_Dataflow_Helper_Data::copyToBatchFile()', since: '26.11')]
    protected function _copy($file)
    {
        $ioAdapter = new \Maho\Io\File();
        if (!$ioAdapter->fileExists($file)) {
            Mage::throwException(Mage::helper('dataflow')->__('File "%s" does not exist.', $file));
        }

        $ioAdapter->setAllowCreateFolders(true);
        $ioAdapter->createDestinationDir($this->getBatchModel()->getIoAdapter()->getPath());

        return $ioAdapter->cp($file, $this->getBatchModel()->getIoAdapter()->getFile(true));
    }
}
