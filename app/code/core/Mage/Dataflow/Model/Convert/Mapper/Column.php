<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

class Mage_Dataflow_Model_Convert_Mapper_Column extends Mage_Dataflow_Model_Convert_Mapper_Abstract
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

    #[\Override]
    public function map(): static
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
