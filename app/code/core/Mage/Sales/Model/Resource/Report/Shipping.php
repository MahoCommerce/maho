<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Resource_Report_Shipping extends Mage_Sales_Model_Resource_Report_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_setResource('sales');
    }

    /**
     * Aggregate Shipping data
     *
     * @param mixed $from
     * @param mixed $to
     * @return $this
     */
    public function aggregate($from = null, $to = null)
    {
        // convert input dates to UTC to be comparable with DATETIME fields in DB
        $from = $this->_dateToUtc($from);
        $to   = $this->_dateToUtc($to);

        $this->_checkDates($from, $to);
        $this->_aggregateByOrderCreatedAt($from, $to);
        $this->_aggregateByShippingCreatedAt($from, $to);
        $this->_setFlagData(Mage_Reports_Model_Flag::REPORT_SHIPPING_FLAG_CODE);
        return $this;
    }

    /**
     * Aggregate shipping report by order create_at as period
     *
     * @param mixed $from
     * @param mixed $to
     * @return $this
     */
    protected function _aggregateByOrderCreatedAt($from, $to)
    {
        $table       = $this->getTable('sales/shipping_aggregated_order');
        $sourceTable = $this->getTable('sales/order');
        $adapter     = $this->_getWriteAdapter();
        $adapter->beginTransaction();

        try {
            if ($from !== null || $to !== null) {
                $subSelect = $this->_getTableDateRangeSelect($sourceTable, 'created_at', 'updated_at', $from, $to);
            } else {
                $subSelect = null;
            }

            $this->_clearTableByDateRange($table, $from, $to, $subSelect);
            // convert dates from UTC to current admin timezone
            $periodExpr = $adapter->getDatePartSql(
                $this->getStoreTZOffsetQuery($sourceTable, 'created_at', $from, $to),
            );
            $ifnullBaseShippingCanceled = $adapter->getIfNullSql('base_shipping_canceled', 0);
            $ifnullBaseShippingRefunded = $adapter->getIfNullSql('base_shipping_refunded', 0);
            $columns = [
                'period'                => $periodExpr,
                'store_id'              => 'store_id',
                'order_status'          => 'status',
                'shipping_description'  => 'shipping_description',
                'orders_count'          => new Maho\Db\Expr('COUNT(entity_id)'),
                'total_shipping'        => new Maho\Db\Expr(
                    "SUM((base_shipping_amount - {$ifnullBaseShippingCanceled}) * base_to_global_rate)",
                ),
                'total_shipping_actual' => new Maho\Db\Expr(
                    "SUM((base_shipping_invoiced - {$ifnullBaseShippingRefunded}) * base_to_global_rate)",
                ),
            ];

            $select = $adapter->select();
            $select->from($sourceTable, $columns)
                 ->where('state NOT IN (?)', [
                     Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                     Mage_Sales_Model_Order::STATE_NEW,
                 ])
                ->where('is_virtual = 0');

            // Filter by date range directly on source column (WHERE is evaluated before GROUP BY,
            // so we can't use the 'period' alias here - it doesn't exist yet)
            if ($from !== null) {
                $select->where('created_at >= ?', $from);
            }
            if ($to !== null) {
                $select->where('created_at <= ?', $to);
            }

            $select->group([
                $periodExpr,
                'store_id',
                'status',
                'shipping_description',
            ]);

            $select->having('orders_count > 0');

            $helper        = Mage::getResourceHelper('core');
            $insertQuery   = $helper->getInsertFromSelectUsingAnalytic($select, $table, array_keys($columns));
            $adapter->query($insertQuery);

            $select->reset();

            $columns = [
                'period'                => 'period',
                'store_id'              => new Maho\Db\Expr((string) Mage_Core_Model_App::ADMIN_STORE_ID),
                'order_status'          => 'order_status',
                'shipping_description'  => 'shipping_description',
                'orders_count'          => new Maho\Db\Expr('SUM(orders_count)'),
                'total_shipping'        => new Maho\Db\Expr('SUM(total_shipping)'),
                'total_shipping_actual' => new Maho\Db\Expr('SUM(total_shipping_actual)'),
            ];

            $select
                ->from($table, $columns)
                ->where('store_id != ?', 0);

            if ($subSelect !== null) {
                $select->where($this->_makeConditionFromDateRangeSelect($subSelect, 'period'));
            }

            $select->group([
                'period',
                'order_status',
                'shipping_description',
            ]);

            $insertQuery = $helper->getInsertFromSelectUsingAnalytic($select, $table, array_keys($columns));
            $adapter->query($insertQuery);
            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            throw $e;
        }
        return $this;
    }

    /**
     * Aggregate shipping report by shipment create_at as period
     *
     * @param mixed $from
     * @param mixed $to
     * @return $this
     */
    protected function _aggregateByShippingCreatedAt($from, $to)
    {
        $table       = $this->getTable('sales/shipping_aggregated');
        $sourceTable = $this->getTable('sales/invoice');
        $orderTable  = $this->getTable('sales/order');
        $adapter     = $this->_getWriteAdapter();
        $adapter->beginTransaction();

        try {
            if ($from !== null || $to !== null) {
                $subSelect = $this->_getTableDateRangeRelatedSelect(
                    $sourceTable,
                    $orderTable,
                    ['order_id' => 'entity_id'],
                    'created_at',
                    'updated_at',
                    $from,
                    $to,
                );
            } else {
                $subSelect = null;
            }

            $this->_clearTableByDateRange($table, $from, $to, $subSelect);
            // convert dates from UTC to current admin timezone
            $periodExpr = $adapter->getDatePartSql(
                $this->getStoreTZOffsetQuery(
                    ['source_table' => $sourceTable],
                    'source_table.created_at',
                    $from,
                    $to,
                ),
            );
            $ifnullBaseShippingCanceled = $adapter->getIfNullSql('order_table.base_shipping_canceled', 0);
            $ifnullBaseShippingRefunded = $adapter->getIfNullSql('order_table.base_shipping_refunded', 0);
            $columns = [
                'period'                => $periodExpr,
                'store_id'              => 'order_table.store_id',
                'order_status'          => 'order_table.status',
                'shipping_description'  => 'order_table.shipping_description',
                'orders_count'          => new Maho\Db\Expr('COUNT(order_table.entity_id)'),
                'total_shipping'        => new Maho\Db\Expr('SUM((order_table.base_shipping_amount - '
                    . "{$ifnullBaseShippingCanceled}) * order_table.base_to_global_rate)"),
                'total_shipping_actual' => new Maho\Db\Expr('SUM((order_table.base_shipping_invoiced - '
                    . "{$ifnullBaseShippingRefunded}) * order_table.base_to_global_rate)"),
            ];

            $select = $adapter->select();
            $select->from(['source_table' => $sourceTable], $columns)
                ->joinInner(
                    ['order_table' => $orderTable],
                    $adapter->quoteInto(
                        'source_table.order_id = order_table.entity_id AND order_table.state != ?',
                        Mage_Sales_Model_Order::STATE_CANCELED,
                    ),
                    [],
                )
                ->useStraightJoin();

            $filterSubSelect = $adapter->select()
                ->from(['filter_source_table' => $sourceTable], 'MIN(filter_source_table.entity_id)')
                ->where('filter_source_table.order_id = source_table.order_id');

            // Filter by date range directly on source column (WHERE is evaluated before GROUP BY,
            // so we can't use the 'period' alias here - it doesn't exist yet)
            if ($from !== null) {
                $select->where('source_table.created_at >= ?', $from);
            }
            if ($to !== null) {
                $select->where('source_table.created_at <= ?', $to);
            }

            $select->where('source_table.entity_id = (?)', new Maho\Db\Expr($filterSubSelect));
            unset($filterSubSelect);

            $select->group([
                $periodExpr,
                'order_table.store_id',
                'order_table.status',
                'order_table.shipping_description',
            ]);

            $helper        = Mage::getResourceHelper('core');
            $insertQuery   = $helper->getInsertFromSelectUsingAnalytic($select, $table, array_keys($columns));
            $adapter->query($insertQuery);

            $select->reset();

            $columns = [
                'period'                => 'period',
                'store_id'              => new Maho\Db\Expr((string) Mage_Core_Model_App::ADMIN_STORE_ID),
                'order_status'          => 'order_status',
                'shipping_description'  => 'shipping_description',
                'orders_count'          => new Maho\Db\Expr('SUM(orders_count)'),
                'total_shipping'        => new Maho\Db\Expr('SUM(total_shipping)'),
                'total_shipping_actual' => new Maho\Db\Expr('SUM(total_shipping_actual)'),
            ];

            $select
                ->from($table, $columns)
                ->where('store_id != ?', 0);

            if ($subSelect !== null) {
                $select->where($this->_makeConditionFromDateRangeSelect($subSelect, 'period'));
            }

            $select->group([
                'period',
                'order_status',
                'shipping_description',
            ]);
            $insertQuery = $helper->getInsertFromSelectUsingAnalytic($select, $table, array_keys($columns));
            $adapter->query($insertQuery);
            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            throw $e;
        }
        return $this;
    }
}
