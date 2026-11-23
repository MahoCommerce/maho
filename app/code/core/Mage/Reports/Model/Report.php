<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

/**
 * @method int getPageSize()
 * @method $this setPageSize(int $value)
 * @method array getStoreIds()
 * @method $this setStoreIds($value)
 */
class Mage_Reports_Model_Report extends Mage_Core_Model_Abstract
{
    /**
     * @var Maho\Data\Collection&Mage_Reports_Model_Report_CollectionInterface
     */
    protected $_reportModel;

    /**
     * @throws \UnexpectedValueException when $modelClass names a class that is not a report collection
     */
    public function initCollection(string $modelClass): static
    {
        $collection = Mage::getResourceModel($modelClass);
        if (!$collection instanceof Maho\Data\Collection || !$collection instanceof Mage_Reports_Model_Report_CollectionInterface) {
            throw new \UnexpectedValueException("The report collection {$modelClass} must be a Maho\\Data\\Collection that implements Mage_Reports_Model_Report_CollectionInterface.");
        }
        $this->_reportModel = $collection;

        return $this;
    }

    public function getReportFull(string $from, string $to): Maho\Data\Collection
    {
        $this->_reportModel->setDateRange($from, $to)->setStoreIds($this->getStoreIds());
        return $this->_reportModel->setPageSize(null);
    }

    public function getReport(string $from, string $to): Maho\Data\Collection
    {
        $this->_reportModel->setDateRange($from, $to)->setStoreIds($this->getStoreIds());
        return $this->_reportModel->setPageSize($this->getPageSize());
    }
}
