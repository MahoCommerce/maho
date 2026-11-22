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
 * @method $this setDateRange(string $from, string $to)
 */
class Mage_Reports_Model_Report extends Mage_Core_Model_Abstract
{
    protected (\Maho\Data\Collection\Db&Mage_Reports_Model_Resource_DateRangeInterface)|null $_reportModel = null;

    /**
     * @param string $modelClass
     * @return $this
     */
    public function initCollection($modelClass)
    {
        $collection = Mage::getResourceModel($modelClass);
        if (!$collection instanceof \Maho\Data\Collection\Db
            || !$collection instanceof Mage_Reports_Model_Resource_DateRangeInterface
        ) {
            throw new \UnexpectedValueException("Invalid report collection: {$modelClass}");
        }
        $this->_reportModel = $collection;

        return $this;
    }

    /**
     * @param string $from
     * @param string $to
     * @return Maho\Data\Collection
     */
    public function getReportFull($from, $to)
    {
        return $this->_reportModel
            ->setDateRange($from, $to)
            ->setPageSize(null)
            ->setStoreIds($this->getStoreIds());
    }

    /**
     * @param string $from
     * @param string $to
     * @return Maho\Data\Collection
     */
    public function getReport($from, $to)
    {
        return $this->_reportModel
            ->setDateRange($from, $to)
            ->setPageSize($this->getPageSize())
            ->setStoreIds($this->getStoreIds());
    }

}
