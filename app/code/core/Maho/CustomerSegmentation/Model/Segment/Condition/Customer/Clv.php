<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Clv extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
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
                    $websiteStores = Mage::app()->getWebsite($website)->getStoreIds();
                    $joinConditions .= ' AND o.store_id IN (' . implode(',', $websiteStores) . ')';
                }
                $select = $adapter->select()
                    ->from(['c' => $resource->getTableName('customer/entity')], ['customer_id' => 'c.entity_id'])
                    ->joinLeft(['o' => $salesTable], $joinConditions, ['total' => 'COALESCE(SUM(o.grand_total), 0)'])
                    ->group('c.entity_id');
                break;

            case 'number_of_orders':
                $joinConditions = 'c.entity_id = o.customer_id AND o.state NOT IN (\'canceled\', \'closed\')';
                if ($website) {
                    $websiteStores = Mage::app()->getWebsite($website)->getStoreIds();
                    $joinConditions .= ' AND o.store_id IN (' . implode(',', $websiteStores) . ')';
                }
                $select = $adapter->select()
                    ->from(['c' => $resource->getTableName('customer/entity')], ['customer_id' => 'c.entity_id'])
                    ->joinLeft(['o' => $salesTable], $joinConditions, ['total' => 'COUNT(o.entity_id)'])
                    ->group('c.entity_id');
                break;

            case 'average_order_value':
                $joinConditions = 'c.entity_id = o.customer_id AND o.state NOT IN (\'canceled\', \'closed\')';
                if ($website) {
                    $websiteStores = Mage::app()->getWebsite($website)->getStoreIds();
                    $joinConditions .= ' AND o.store_id IN (' . implode(',', $websiteStores) . ')';
                }
                $select = $adapter->select()
                    ->from(['c' => $resource->getTableName('customer/entity')], ['customer_id' => 'c.entity_id'])
                    ->joinLeft(['o' => $salesTable], $joinConditions, ['total' => 'COALESCE(AVG(o.grand_total), 0)'])
                    ->group('c.entity_id');
                break;

            case 'lifetime_profit':
                $salesSelect = $adapter->select()
                    ->from(['o' => $salesTable], ['customer_id', 'amount' => 'SUM(o.grand_total)'])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled', 'closed'])
                    ->group('o.customer_id');

                $refundsSelect = $adapter->select()
                    ->from(['c' => $creditmemoTable], ['customer_id' => 'o.customer_id', 'amount' => 'SUM(c.grand_total)'])
                    ->join(['o' => $salesTable], 'c.order_id = o.entity_id', [])
                    ->where('o.customer_id IS NOT NULL')
                    ->group('o.customer_id');

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
                    ->from(['c' => $creditmemoTable], ['customer_id' => 'o.customer_id', 'total' => 'SUM(c.grand_total)'])
                    ->join(['o' => $salesTable], 'c.order_id = o.entity_id', [])
                    ->where('o.customer_id IS NOT NULL')
                    ->group('o.customer_id');
                break;

            default:
                return $requireValid ? 'FALSE' : 'TRUE';
        }

        if ($website && !in_array($attribute, ['lifetime_sales', 'lifetime_orders', 'number_of_orders', 'average_order_value'])) {
            // Store filter is already handled in JOIN conditions for the main attributes
            $websiteStores = Mage::app()->getWebsite($website)->getStoreIds();
            if (isset($salesTable)) {
                $select->where('o.store_id IN (?)', $websiteStores);
            }
        }

        // For LEFT JOIN queries, filter by customer website
        if ($website && in_array($attribute, ['lifetime_sales', 'lifetime_orders', 'average_order_value'])) {
            $select->where('c.website_id = ?', $website);
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
        $valueName = $this->getValueName();
        return Mage::helper('customersegmentation')->__('Order') . ':' . ' ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }

}
