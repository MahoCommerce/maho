<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Invoiced_Collection extends Mage_Sales_Model_Entity_Order_Collection
{
    /**
     * Set date range
     *
     * @param string $from
     * @param string $to
     * @return $this
     */
    public function setDateRange($from, $to)
    {
        $orderInvoicedExpr = $this->getConnection()->getCheckSql('{{base_total_invoiced}} > 0', '1', '0');
        $this->_reset()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('created_at', ['from' => $from, 'to' => $to])
            ->addExpressionAttributeToSelect(
                'orders',
                'COUNT({{base_total_invoiced}})',
                ['base_total_invoiced'],
            )
            ->addExpressionAttributeToSelect(
                'orders_invoiced',
                "SUM({$orderInvoicedExpr})",
                ['base_total_invoiced'],
            )
            ->addAttributeToFilter('state', ['neq' => Mage_Sales_Model_Order::STATE_CANCELED])
            ->getSelect()
            ->group('entity_id')
            ->having('orders > ?', 0);
        /*
         * Allow Analytic Functions Usage
         */
        $this->_useAnalyticFunction = true;

        return $this;
    }

    /**
     * Set store filter collection
     *
     * @param array $storeIds
     * @return $this
     */
    public function setStoreIds($storeIds)
    {
        if ($storeIds) {
            $this->addAttributeToFilter('store_id', ['in' => (array) $storeIds])
            ->addExpressionAttributeToSelect(
                'invoiced',
                'SUM({{base_total_invoiced}})',
                ['base_total_invoiced'],
            )
            ->addExpressionAttributeToSelect(
                'invoiced_captured',
                'SUM({{base_total_paid}})',
                ['base_total_paid'],
            )
            ->addExpressionAttributeToSelect(
                'invoiced_not_captured',
                'SUM({{base_total_invoiced}}-{{base_total_paid}})',
                ['base_total_invoiced', 'base_total_paid'],
            );
        } else {
            $this->addExpressionAttributeToSelect(
                'invoiced',
                'SUM({{base_total_invoiced}}*{{base_to_global_rate}})',
                ['base_total_invoiced', 'base_to_global_rate'],
            )
            ->addExpressionAttributeToSelect(
                'invoiced_captured',
                'SUM({{base_total_paid}}*{{base_to_global_rate}})',
                ['base_total_paid', 'base_to_global_rate'],
            )
            ->addExpressionAttributeToSelect(
                'invoiced_not_captured',
                'SUM(({{base_total_invoiced}}-{{base_total_paid}})*{{base_to_global_rate}})',
                ['base_total_invoiced', 'base_to_global_rate', 'base_total_paid'],
            );
        }

        return $this;
    }

    /**
     * Get select count sql
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getSelectCountSql()
    {
        $countSelect = clone $this->getSelect();
        $countSelect->reset(Maho\Db\Select::ORDER);
        $countSelect->reset(Maho\Db\Select::LIMIT_COUNT);
        $countSelect->reset(Maho\Db\Select::LIMIT_OFFSET);
        $countSelect->reset(Maho\Db\Select::COLUMNS);
        $countSelect->reset(Maho\Db\Select::GROUP);
        $countSelect->reset(Maho\Db\Select::HAVING);
        $countSelect->columns('COUNT(*)');

        return $countSelect;
    }
}
