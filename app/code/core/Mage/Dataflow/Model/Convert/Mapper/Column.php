<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Dataflow_Model_Convert_Mapper_Column extends Mage_Dataflow_Model_Convert_Mapper_Abstract
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
            $this->_batchExport = Varien_Object_Cache::singleton()->save($object);
        }
        return Varien_Object_Cache::singleton()->load($this->_batchExport);
    }

    /**
     * Retrieve Batch import model
     *
     * @return Mage_Dataflow_Model_Import_Export
     */
    public function getBatchImportModel()
    {
        if (is_null($this->_batchImport)) {
            $object = Mage::getModel('dataflow/batch_import');
            $this->_batchImport = Varien_Object_Cache::singleton()->save($object);
        }
        return Varien_Object_Cache::singleton()->load($this->_batchImport);
    }

    #[\Override]
    public function map()
    {
        $batchModel  = $this->getBatchModel();
        $batchExport = $this->getBatchExportModel();

        $batchExportIds = $batchExport
            ->setBatchId($this->getBatchModel()->getId())
            ->getIdCollection();

        $onlySpecified = (bool) $this->getVar('_only_specified') === true;

        if (!$onlySpecified) {
            foreach ($batchExportIds as $batchExportId) {
                $batchExport->load($batchExportId);
                $batchModel->parseFieldList($batchExport->getBatchData());
            }

            return $this;
        }

        if ($this->getVar('map') && is_array($this->getVar('map'))) {
            $attributesToSelect = $this->getVar('map');
        } else {
            $attributesToSelect = [];
        }

        if (!$attributesToSelect) {
            $this->getBatchExportModel()
                ->setBatchId($this->getBatchModel()->getId())
                ->deleteCollection();

            throw new Exception(Mage::helper('dataflow')->__('Error in field mapping: field list for mapping is not defined.'));
        }

        foreach ($batchExportIds as $batchExportId) {
            $batchExport = $this->getBatchExportModel()->load($batchExportId);
            $row = $batchExport->getBatchData();

            $newRow = [];
            foreach ($attributesToSelect as $field => $mapField) {
                $newRow[$mapField] = $row[$field] ?? null;
            }

            $batchExport->setBatchData($newRow)
                ->setStatus(2)
                ->save();
            $this->getBatchModel()->parseFieldList($batchExport->getBatchData());
        }

        return $this;
    }
}
