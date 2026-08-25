<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

class Mage_Reports_Model_Resource_Helper_Mysql extends Mage_Core_Model_Resource_Helper_Mysql implements Mage_Reports_Model_Resource_Helper_Interface
{
    /**
     * Merge Index data
     *
     * @param string $mainTable
     * @param array $data
     * @param mixed $matchFields
     * @return int
     */
    #[\Override]
    public function mergeVisitorProductIndex($mainTable, $data, $matchFields)
    {
        return $this->_getWriteAdapter()->insertOnDuplicate($mainTable, $data, array_keys($data));
    }

    /**
     * Update rating position
     *
     * @param string $type day|month|year
     * @param string $column
     * @param string $mainTable
     * @param string $aggregationTable
     * @return Mage_Core_Model_Resource_Helper_Mysql
     */
    #[\Override]
    public function updateReportRatingPos($type, $column, $mainTable, $aggregationTable)
    {
        $adapter = $this->_getWriteAdapter();

        $periodCol = match ($type) {
            'year' => $adapter->getDateFormatSql('t.period', '%Y-01-01'),
            'month' => $adapter->getDateFormatSql('t.period', '%Y-%m-01'),
            default => 't.period',
        };

        $columns = [
            'period'          => 't.period',
            'store_id'        => 't.store_id',
            'product_id'      => 't.product_id',
            'product_name'    => 't.product_name',
            'product_price'   => 't.product_price',
        ];

        if ($type == 'day') {
            $columns['id'] = 't.id';  // to speed-up insert on duplicate key update
        }

        if ($column == 'qty_ordered') {
            $columns['product_type_id'] = 't.product_type_id';
        }

        // Wrap columns not in the GROUP BY in MAX() to satisfy ONLY_FULL_GROUP_BY;
        // they are constant within each (store_id, period, product_id) group
        $cols = [];
        foreach ($columns as $alias => $expr) {
            $cols[$alias] = in_array($alias, ['store_id', 'product_id'])
                ? $expr
                : new Maho\Db\Expr("MAX({$expr})");
        }
        $cols['total_qty'] = new Maho\Db\Expr('SUM(t.' . $column . ')');

        $periodSubSelect = $adapter->select();
        $periodSubSelect->from(['t' => $mainTable], $cols)
            ->group(['t.store_id', $periodCol, 't.product_id']);

        $orderExpr = 'total_qty DESC';
        if ($column == 'qty_ordered') {
            $compositeTypes = $adapter->quote(Mage_Catalog_Model_Product_Type::getCompositeTypes());
            $orderExpr = "CASE WHEN t.product_type_id IN ($compositeTypes) THEN 1 ELSE 0 END, total_qty DESC";
        }

        // ROW_NUMBER() instead of MySQL user variables: the old sentinel compared a DATE
        // against '0000-00-00', which strict mode rejects inside an INSERT ... SELECT, and
        // the ranking relied on an ORDER BY in a derived table, which is not guaranteed.
        $finalCols               = $columns;
        $finalCols['period']     = new Maho\Db\Expr($periodCol);
        $finalCols[$column]      = 't.total_qty';
        $finalCols['rating_pos'] = new Maho\Db\Expr(
            "ROW_NUMBER() OVER (PARTITION BY t.store_id, {$periodCol} ORDER BY {$orderExpr})",
        );

        $ratingSelect = $adapter->select();
        $ratingSelect->from(['t' => $periodSubSelect], $finalCols);

        $adapter->query($ratingSelect->insertFromSelect($aggregationTable, array_keys($finalCols)));

        return $this;
    }
}
