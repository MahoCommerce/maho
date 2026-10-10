<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Clv extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    #[\Override]
    protected $_inputType = 'numeric';

    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_clv');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return ['value' => $this->getType(), 'label' => Mage::helper('customersegmentation')->__('Customer Lifetime Value')];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'lifetime_sales' => Mage::helper('customersegmentation')->__('Lifetime Sales Amount'),
            'number_of_orders' => Mage::helper('customersegmentation')->__('Number of Orders'),
            'average_order_value' => Mage::helper('customersegmentation')->__('Average Order Value'),
            'lifetime_profit' => Mage::helper('customersegmentation')->__('Lifetime Profit (Sales - Refunds)'),
            'lifetime_refunds' => Mage::helper('customersegmentation')->__('Lifetime Refunds Amount'),
        ];

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return 'text';
    }


    #[\Override]
    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        return $this->getSubfilterSql('e.entity_id', true, $websiteId);
    }

    public function getSubfilterSql(string $fieldName, bool $requireValid, ?int $website): string
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        // Base query for sales data
        $salesTable = $resource->getTableName('sales/order');
        $creditmemoTable = $resource->getTableName('sales/creditmemo');

        switch ($attribute) {
            case 'lifetime_sales':
                $joinConditions = 'c.entity_id = o.customer_id AND o.state NOT IN (\'canceled\', \'closed\')';
                if ($website) {
                    $joinConditions .= $adapter->quoteInto(' AND o.store_id IN (?)', $this->getCurrencyStoreIds($website));
                }
                $select = $adapter->select()
                    ->from(['c' => $resource->getTableName('customer/entity')], ['customer_id' => 'c.entity_id'])
                    ->joinLeft(['o' => $salesTable], $joinConditions, ['total' => 'COALESCE(SUM(o.base_grand_total), 0)'])
                    ->group('c.entity_id');
                break;

            case 'number_of_orders':
                $joinConditions = 'c.entity_id = o.customer_id AND o.state NOT IN (\'canceled\', \'closed\')';
                if ($website) {
                    $joinConditions .= $adapter->quoteInto(' AND o.store_id IN (?)', $this->getCurrencyStoreIds($website));
                }
                $select = $adapter->select()
                    ->from(['c' => $resource->getTableName('customer/entity')], ['customer_id' => 'c.entity_id'])
                    ->joinLeft(['o' => $salesTable], $joinConditions, ['total' => 'COUNT(o.entity_id)'])
                    ->group('c.entity_id');
                break;

            case 'average_order_value':
                $joinConditions = 'c.entity_id = o.customer_id AND o.state NOT IN (\'canceled\', \'closed\')';
                if ($website) {
                    $joinConditions .= $adapter->quoteInto(' AND o.store_id IN (?)', $this->getCurrencyStoreIds($website));
                }
                $select = $adapter->select()
                    ->from(['c' => $resource->getTableName('customer/entity')], ['customer_id' => 'c.entity_id'])
                    ->joinLeft(['o' => $salesTable], $joinConditions, ['total' => 'COALESCE(AVG(o.base_grand_total), 0)'])
                    ->group('c.entity_id');
                break;

            case 'lifetime_profit':
                $salesSelect = $adapter->select()
                    ->from(['o' => $salesTable], ['customer_id', 'amount' => 'SUM(o.base_grand_total)'])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled', 'closed'])
                    ->group('o.customer_id');

                $refundsSelect = $adapter->select()
                    ->from(['c' => $creditmemoTable], ['customer_id' => 'o.customer_id', 'amount' => 'SUM(c.base_grand_total)'])
                    ->join(['o' => $salesTable], 'c.order_id = o.entity_id', [])
                    ->where('o.customer_id IS NOT NULL')
                    ->group('o.customer_id');

                if ($website) {
                    $storeIds = $this->getCurrencyStoreIds($website);
                    $salesSelect->where('o.store_id IN (?)', $storeIds);
                    $refundsSelect->where('o.store_id IN (?)', $storeIds);
                }

                $select = $adapter->select()
                    ->from(['sales' => new Maho\Db\Expr("({$salesSelect})")], ['customer_id'])
                    ->joinLeft(
                        ['refunds' => new Maho\Db\Expr("({$refundsSelect})")],
                        'sales.customer_id = refunds.customer_id',
                        [],
                    )
                    ->columns(['total' => new Maho\Db\Expr('COALESCE(sales.amount, 0) - COALESCE(refunds.amount, 0)')]);
                break;

            case 'lifetime_refunds':
                $select = $adapter->select()
                    ->from(['c' => $creditmemoTable], ['customer_id' => 'o.customer_id', 'total' => 'SUM(c.base_grand_total)'])
                    ->join(['o' => $salesTable], 'c.order_id = o.entity_id', [])
                    ->where('o.customer_id IS NOT NULL')
                    ->group('o.customer_id');
                if ($website) {
                    $select->where('o.store_id IN (?)', $this->getCurrencyStoreIds($website));
                }
                break;

            default:
                return $requireValid ? 'FALSE' : 'TRUE';
        }

        // Standard condition building
        $clvSelect = $adapter->select()
            ->from(['clv' => new Maho\Db\Expr("({$select})")], ['customer_id'])
            ->where($this->buildSqlCondition($adapter, 'clv.total', $operator, $this->prepareNumericValue($value)));

        if ($requireValid) {
            return $adapter->quoteInto("{$fieldName} IN (?)", new Maho\Db\Expr((string) $clvSelect));
        }
        return $adapter->quoteInto("{$fieldName} NOT IN (?) OR {$fieldName} IS NULL", new Maho\Db\Expr((string) $clvSelect));
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Customer Lifetime Value') . ':' . ' ' . $attributeName;
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $this->loadAttributeOptions();
        $attributeOptions = $this->getAttributeOption();
        $attributeLabel = is_array($attributeOptions) && isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;

        $operatorName = $this->getOperatorName();
        $valueName = in_array($attribute, ['lifetime_sales', 'average_order_value', 'lifetime_profit', 'lifetime_refunds'], true)
            ? $this->getAmountValueName()
            : $this->getValueName();
        return Mage::helper('customersegmentation')->__('Order') . ':' . ' ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }

}
