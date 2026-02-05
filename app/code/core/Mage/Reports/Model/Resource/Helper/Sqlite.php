<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Helper_Sqlite extends Mage_Core_Model_Resource_Helper_Sqlite implements Mage_Reports_Model_Resource_Helper_Interface
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
     * SQLite doesn't support user-defined variables like MySQL, so we use window functions
     *
     * @param string $type day|month|year
     * @param string $column
     * @param string $mainTable
     * @param string $aggregationTable
     * @return Mage_Reports_Model_Resource_Helper_Sqlite
     */
    #[\Override]
    public function updateReportRatingPos($type, $column, $mainTable, $aggregationTable)
    {
        $adapter = $this->_getWriteAdapter();

        // Period expression for inner query (with table alias)
        $periodColInner = match ($type) {
            'year' => "strftime('%Y-01-01', t.period)",
            'month' => "strftime('%Y-%m-01', t.period)",
            default => 't.period',
        };

        // Period expression for outer query (using aliased column from subquery)
        $periodColOuter = match ($type) {
            'year' => "strftime('%Y-01-01', period)",
            'month' => "strftime('%Y-%m-01', period)",
            default => 'period',
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

        // Build the columns for aggregation
        $selectCols = [];
        foreach ($columns as $alias => $col) {
            $selectCols[] = "$col AS $alias";
        }
        $selectCols[] = "SUM(t.$column) AS total_qty";

        // SQLite supports window functions for ranking
        $groupCols = "t.store_id, $periodColInner, t.product_id";

        // First, aggregate the data
        $aggregateSql = sprintf(
            'SELECT %s FROM %s AS t GROUP BY %s',
            implode(', ', $selectCols),
            $mainTable,
            $groupCols,
        );

        // Now add ranking using ROW_NUMBER() window function
        // Note: In the outer query, we use column names from the subquery (no 't.' prefix)
        $rankCols = [];
        foreach (array_keys($columns) as $alias) {
            $rankCols[] = $alias;
        }
        $rankCols[] = "total_qty AS $column";
        $rankCols[] = "ROW_NUMBER() OVER (PARTITION BY store_id, $periodColOuter ORDER BY total_qty DESC) AS rating_pos";

        $finalCols = $rankCols;
        // Remove unnecessary columns for final insert
        $insertCols = array_keys($columns);
        $insertCols[] = $column;
        $insertCols[] = 'rating_pos';

        $sql = sprintf(
            'INSERT OR REPLACE INTO %s (%s) SELECT %s FROM (%s)',
            $aggregationTable,
            implode(', ', $insertCols),
            implode(', ', $rankCols),
            $aggregateSql,
        );

        $adapter->query($sql);

        return $this;
    }
}
