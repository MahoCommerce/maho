<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

class Mage_Reports_Model_Resource_Helper_Pgsql extends Mage_Core_Model_Resource_Helper_Pgsql implements Mage_Reports_Model_Resource_Helper_Interface
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
     * PostgreSQL version using window functions instead of MySQL user variables
     *
     * @param string $type day|month|year
     * @param string $column
     * @param string $mainTable
     * @param string $aggregationTable
     * @return Mage_Reports_Model_Resource_Helper_Pgsql
     */
    #[\Override]
    public function updateReportRatingPos($type, $column, $mainTable, $aggregationTable)
    {
        $adapter = $this->_getWriteAdapter();

        // DATE_TRUNC keeps the expression date-typed; the aggregation table's
        // period column is a date, so a TO_CHAR (text) expression would fail.
        $periodCol = match ($type) {
            'year' => "DATE_TRUNC('year', t.period)::date",
            'month' => "DATE_TRUNC('month', t.period)::date",
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
            $columns['id'] = 't.id';
        }

        if ($column == 'qty_ordered') {
            $columns['product_type_id'] = 't.product_type_id';
        }

        // Build the aggregation subquery. PostgreSQL's strict GROUP BY only accepts
        // columns functionally dependent on a grouped PRIMARY KEY, and the PK (id)
        // is not in the GROUP BY, so every non-grouped column must be aggregated.
        $periodSubSelect = $adapter->select();
        $cols = [
            'period'        => $type == 'day' ? 't.period' : new Maho\Db\Expr('MAX(t.period)'),
            'store_id'      => 't.store_id',
            'product_id'    => 't.product_id',
            'product_name'  => new Maho\Db\Expr('MAX(t.product_name)'),
            'product_price' => new Maho\Db\Expr('MAX(t.product_price)'),
        ];

        if ($type == 'day') {
            $cols['id'] = new Maho\Db\Expr('MAX(t.id)');
        }

        if ($column == 'qty_ordered') {
            $cols['product_type_id'] = new Maho\Db\Expr('MAX(t.product_type_id)');
        }

        $cols['total_qty'] = new Maho\Db\Expr('SUM(t.' . $column . ')');

        $periodSubSelect->from(['t' => $mainTable], $cols)
            ->group(['t.store_id', new Maho\Db\Expr($periodCol), 't.product_id']);

        // Build the final select with window function for ranking
        $orderExpr = 'total_qty DESC';
        if ($column == 'qty_ordered') {
            $compositeTypes = $adapter->quote(Mage_Catalog_Model_Product_Type::getCompositeTypes());
            $orderExpr = "CASE WHEN t.product_type_id IN ($compositeTypes) THEN 1 ELSE 0 END, total_qty DESC";
        }

        // Use ROW_NUMBER() window function for PostgreSQL
        $ratingSelect = $adapter->select();
        $finalCols = $columns;
        $finalCols['period'] = new Maho\Db\Expr($periodCol);
        $finalCols[$column] = 't.total_qty';
        $finalCols['rating_pos'] = new Maho\Db\Expr(
            "ROW_NUMBER() OVER (PARTITION BY t.store_id, $periodCol ORDER BY $orderExpr)",
        );

        $ratingSelect->from(['t' => $periodSubSelect], $finalCols);

        $sql = $ratingSelect->insertFromSelect($aggregationTable, array_keys($finalCols));
        $adapter->query($sql);

        return $this;
    }
}
