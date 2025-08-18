<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
        $this->setAttributeOption([
            'lifetime_sales' => Mage::helper('customersegmentation')->__('Lifetime Sales Amount'),
            'lifetime_orders' => Mage::helper('customersegmentation')->__('Number of Orders'),
            'average_order_value' => Mage::helper('customersegmentation')->__('Average Order Value'),
            'lifetime_profit' => Mage::helper('customersegmentation')->__('Lifetime Profit (Sales - Refunds)'),
            'lifetime_refunds' => Mage::helper('customersegmentation')->__('Lifetime Refunds Amount'),
        ]);
        return $this;
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return 'text';
    }

    #[\Override]
    public function getAttributeElement()
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);
        return $element;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        return $this->getSubfilterSql('customer_entity.entity_id', true, $websiteId);
    }

    public function getSubfilterSql(string $fieldName, bool $requireValid, ?int $website): string
    {
        $attribute = $this->getAttribute();
        $operator = $this->getOperator();
        $value = $this->getValue();

        $adapter = $this->getResource()->getReadConnection();

        // Base query for sales data
        $salesTable = $this->getResource()->getTable('sales/order');
        $creditmemoTable = $this->getResource()->getTable('sales/creditmemo');

        switch ($attribute) {
            case 'lifetime_sales':
                $select = $adapter->select()
                    ->from(['o' => $salesTable], ['customer_id', 'total' => 'SUM(o.grand_total)'])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled', 'closed'])
                    ->group('o.customer_id');
                break;

            case 'lifetime_orders':
                $select = $adapter->select()
                    ->from(['o' => $salesTable], ['customer_id', 'total' => 'COUNT(*)'])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled', 'closed'])
                    ->group('o.customer_id');
                break;

            case 'average_order_value':
                $select = $adapter->select()
                    ->from(['o' => $salesTable], ['customer_id', 'total' => 'AVG(o.grand_total)'])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled', 'closed'])
                    ->group('o.customer_id');
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
                    ->from(['sales' => new Zend_Db_Expr("({$salesSelect})")], ['customer_id'])
                    ->joinLeft(
                        ['refunds' => new Zend_Db_Expr("({$refundsSelect})")],
                        'sales.customer_id = refunds.customer_id',
                        ['total' => 'COALESCE(sales.amount, 0) - COALESCE(refunds.amount, 0)'],
                    );
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

        if ($website) {
            if (isset($salesTable)) {
                $select->where('o.store_id IN (?)', $this->getStoreByWebsite($website));
            }
        }

        // Create the final condition
        $clvSelect = $adapter->select()
            ->from(['clv' => new Zend_Db_Expr("({$select})")], ['customer_id'])
            ->where($this->getResource()->createConditionSql('clv.total', $operator, $value));

        if ($requireValid) {
            return $adapter->quoteInto("{$fieldName} IN (?)", new Zend_Db_Expr($clvSelect));
        } else {
            return $adapter->quoteInto("{$fieldName} NOT IN (?) OR {$fieldName} IS NULL", new Zend_Db_Expr($clvSelect));
        }
    }

    #[\Override]
    public function asHtml(): string
    {
        return $this->getTypeElement()->getHtml()
            . Mage::helper('customersegmentation')->__(
                '%s %s %s',
                $this->getAttributeElement()->getHtml(),
                $this->getOperatorElement()->getHtml(),
                $this->getValueElement()->getHtml(),
            )
            . $this->getRemoveLinkHtml();
    }
}
