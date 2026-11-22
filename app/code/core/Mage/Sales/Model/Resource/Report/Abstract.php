<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

abstract class Mage_Sales_Model_Resource_Report_Abstract extends Mage_Reports_Model_Resource_Report_Abstract
{
    /**
     * Replace the rows of a rolling table with the rows of $select (one row for each product and store).
     * Then add the rows of store 0, which hold the sum of $sumColumns over all stores.
     *
     * @param list<string> $sumColumns
     */
    protected function _replaceRollingRows(string $table, Maho\Db\Select $select, array $sumColumns): static
    {
        $adapter = $this->_getWriteAdapter();
        $columns = ['product_id', 'store_id', ...$sumColumns];

        $totals = ['product_id', 'store_id' => new Maho\Db\Expr((string) Mage_Core_Model_App::ADMIN_STORE_ID)];
        foreach ($sumColumns as $column) {
            $totals[$column] = new Maho\Db\Expr("SUM({$column})");
        }
        $allStores = $adapter->select()
            ->from($table, $totals)
            ->where('store_id != ?', Mage_Core_Model_App::ADMIN_STORE_ID)
            ->group('product_id');

        $adapter->beginTransaction();
        try {
            $adapter->delete($table);
            $adapter->query($adapter->insertFromSelect($select, $table, $columns));
            $adapter->query($adapter->insertFromSelect($allStores, $table, $columns));
            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            throw $e;
        }

        return $this;
    }
}
