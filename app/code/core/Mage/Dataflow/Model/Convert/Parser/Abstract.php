<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Dataflow_Model_Convert_Parser_Abstract extends Mage_Dataflow_Model_Convert_Container_Abstract implements Mage_Dataflow_Model_Convert_Parser_Interface
{
    /**
     * Dataflow batch model
     *
     * @var Mage_Dataflow_Model_Batch|null
     */
    protected $_batch;

    /**
     * Dataflow batch export model
     *
     * @var Mage_Dataflow_Model_Batch_Export|string|false|null
     */
    protected $_batchExport;

    /**
     * Dataflow batch import model
     *
     * @var Mage_Dataflow_Model_Batch_Import|string|false|null
     */
    protected $_batchImport;

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
        if (is_null($this->_batch)) {
            $this->_batch = Mage::getSingleton('dataflow/batch');
        }
        return $this->_batch;
    }

    /**
     * Retrieve Batch export model
     *
     * @return Mage_Dataflow_Model_Batch_Export
     */
    public function getBatchExportModel()
    {
        if (is_null($this->_batchExport)) {
            $object = Mage::getModel('dataflow/batch_export');
            $this->_batchExport = \Maho\DataObject\Cache::singleton()->save($object);
        }
        return \Maho\DataObject\Cache::singleton()->load($this->_batchExport);
    }

    /**
     * Retrieve Batch import model
     *
     * @return Mage_Dataflow_Model_Batch_Import
     */
    public function getBatchImportModel()
    {
        if (is_null($this->_batchImport)) {
            $object = Mage::getModel('dataflow/batch_import');
            $this->_batchImport = \Maho\DataObject\Cache::singleton()->save($object);
        }
        return \Maho\DataObject\Cache::singleton()->load($this->_batchImport);
    }

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
