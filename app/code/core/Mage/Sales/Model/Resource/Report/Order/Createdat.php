<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Resource_Report_Order_Createdat extends Mage_Sales_Model_Resource_Report_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_aggregated_created', 'id');
    }

    /**
     * Aggregate Orders data by order created at
     *
     * @param mixed $from
     * @param mixed $to
     * @return $this
     */
    public function aggregate($from = null, $to = null)
    {
        return $this->_aggregateByField('created_at', $from, $to);
    }

    /**
     * Aggregate Orders data by custom field
     *
     * @throws Exception
     * @param string $aggregationField
     * @param mixed $from
     * @param mixed $to
     * @return $this
     */
    protected function _aggregateByField($aggregationField, $from, $to)
    {
        // convert input dates to UTC to be comparable with DATETIME fields in DB
        $from = $this->_dateToUtc($from);
        $to   = $this->_dateToUtc($to);

        $this->_checkDates($from, $to);
        $adapter = $this->_getWriteAdapter();

        $adapter->beginTransaction();
        try {
            if ($from !== null || $to !== null) {
                $subSelect = $this->_getTableDateRangeSelect(
                    $this->getTable('sales/order'),
                    $aggregationField,
                    $aggregationField,
                    $from,
                    $to,
                );
            } else {
                $subSelect = null;
            }
            $this->_clearTableByDateRange($this->getMainTable(), $from, $to, $subSelect);

            $periodExpr = $adapter->getDatePartSql($this->getStoreTZOffsetQuery(
                ['o' => $this->getTable('sales/order')],
                'o.' . $aggregationField,
                $from,
                $to,
            ));
            // Columns list
            $columns = [
                // convert dates from UTC to current admin timezone
                'period'                         => $periodExpr,
                'store_id'                       => 'o.store_id',
                'order_status'                   => 'o.status',
                'orders_count'                   => new Zend_Db_Expr('COUNT(o.entity_id)'),
                'total_qty_ordered'              => new Zend_Db_Expr('SUM(oi.total_qty_ordered)'),
                'total_qty_invoiced'             => new Zend_Db_Expr('SUM(oi.total_qty_invoiced)'),
                'total_income_amount'            => new Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $adapter->getIfNullSql('o.base_grand_total', 0),
                        $adapter->getIfNullSql('o.base_total_canceled', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_revenue_amount'           => new Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s - %s - (%s - %s - %s)) * %s)',
                        $adapter->getIfNullSql('o.base_total_invoiced', 0),
                        $adapter->getIfNullSql('o.base_tax_invoiced', 0),
                        $adapter->getIfNullSql('o.base_shipping_invoiced', 0),
                        $adapter->getIfNullSql('o.base_total_refunded', 0),
                        $adapter->getIfNullSql('o.base_tax_refunded', 0),
                        $adapter->getIfNullSql('o.base_shipping_refunded', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_profit_amount'            => new Zend_Db_Expr(
                    sprintf(
                        'SUM(((%s - %s) - (%s - %s) - (%s - %s) - %s) * %s)',
                        $adapter->getIfNullSql('o.base_total_paid', 0),
                        $adapter->getIfNullSql('o.base_total_refunded', 0),
                        $adapter->getIfNullSql('o.base_tax_invoiced', 0),
                        $adapter->getIfNullSql('o.base_tax_refunded', 0),
                        $adapter->getIfNullSql('o.base_shipping_invoiced', 0),
                        $adapter->getIfNullSql('o.base_shipping_refunded', 0),
                        $adapter->getIfNullSql('o.base_total_invoiced_cost', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_invoiced_amount'          => new Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $adapter->getIfNullSql('o.base_total_invoiced', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_canceled_amount'          => new Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $adapter->getIfNullSql('o.base_total_canceled', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_paid_amount'              => new Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $adapter->getIfNullSql('o.base_total_paid', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_refunded_amount'          => new Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $adapter->getIfNullSql('o.base_total_refunded', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_tax_amount'               => new Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $adapter->getIfNullSql('o.base_tax_amount', 0),
                        $adapter->getIfNullSql('o.base_tax_canceled', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_tax_amount_actual'        => new Zend_Db_Expr(
                    sprintf(
                        'SUM((%s -%s) * %s)',
                        $adapter->getIfNullSql('o.base_tax_invoiced', 0),
                        $adapter->getIfNullSql('o.base_tax_refunded', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_shipping_amount'          => new Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $adapter->getIfNullSql('o.base_shipping_amount', 0),
                        $adapter->getIfNullSql('o.base_shipping_canceled', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_shipping_amount_actual'   => new Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $adapter->getIfNullSql('o.base_shipping_invoiced', 0),
                        $adapter->getIfNullSql('o.base_shipping_refunded', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_discount_amount'          => new Zend_Db_Expr(
                    sprintf(
                        'SUM((ABS(%s) - %s) * %s)',
                        $adapter->getIfNullSql('o.base_discount_amount', 0),
                        $adapter->getIfNullSql('o.base_discount_canceled', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
                'total_discount_amount_actual'   => new Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $adapter->getIfNullSql('o.base_discount_invoiced', 0),
                        $adapter->getIfNullSql('o.base_discount_refunded', 0),
                        $adapter->getIfNullSql('o.base_to_global_rate', 0),
                    ),
                ),
            ];

            $select          = $adapter->select();
            $selectOrderItem = $adapter->select();

            $qtyCanceledExpr = $adapter->getIfNullSql('qty_canceled', 0);
            $cols            = [
                'order_id'           => 'order_id',
                'total_qty_ordered'  => new Zend_Db_Expr("SUM(qty_ordered - {$qtyCanceledExpr})"),
                'total_qty_invoiced' => new Zend_Db_Expr('SUM(qty_invoiced)'),
            ];
            $selectOrderItem->from($this->getTable('sales/order_item'), $cols)
                ->where('parent_item_id IS NULL')
                ->group('order_id');

            $select->from(['o' => $this->getTable('sales/order')], $columns)
                ->join(['oi' => $selectOrderItem], 'oi.order_id = o.entity_id', [])
                ->where('o.state NOT IN (?)', [
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    Mage_Sales_Model_Order::STATE_NEW,
                ]);

            if ($subSelect !== null) {
                $select->having($this->_makeConditionFromDateRangeSelect($subSelect, 'period'));
            }

            $select->group([
                $periodExpr,
                'o.store_id',
                'o.status',
            ]);

            $adapter->query($select->insertFromSelect($this->getMainTable(), array_keys($columns)));

            // setup all columns to select SUM() except period, store_id and order_status
            foreach (array_keys($columns) as $k) {
                $columns[$k] = new Zend_Db_Expr('SUM(' . $k . ')');
            }
            $columns['period']         = 'period';
            $columns['store_id']       = new Zend_Db_Expr((string) Mage_Core_Model_App::ADMIN_STORE_ID);
            $columns['order_status']   = 'order_status';

            $select->reset();
            $select->from($this->getMainTable(), $columns)
                ->where('store_id <> 0');

            if ($subSelect !== null) {
                $select->where($this->_makeConditionFromDateRangeSelect($subSelect, 'period'));
            }

            $select->group([
                'period',
                'order_status',
            ]);
            $adapter->query($select->insertFromSelect($this->getMainTable(), array_keys($columns)));
            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            throw $e;
        }

        return $this;
    }
}
