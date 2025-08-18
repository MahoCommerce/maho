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

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Timebased extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    protected $_inputType = 'numeric';

    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_timebased');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return ['value' => $this->getType(), 'label' => Mage::helper('customersegmentation')->__('Customer Time-based')];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $this->setAttributeOption([
            'days_since_last_login' => Mage::helper('customersegmentation')->__('Days Since Last Login'),
            'days_since_last_order' => Mage::helper('customersegmentation')->__('Days Since Last Order'),
            'days_inactive' => Mage::helper('customersegmentation')->__('Days Inactive (No Login or Order)'),
            'days_since_first_order' => Mage::helper('customersegmentation')->__('Days Since First Order'),
            'order_frequency_days' => Mage::helper('customersegmentation')->__('Average Days Between Orders'),
            'days_without_purchase' => Mage::helper('customersegmentation')->__('Days Without Purchase'),
        ]);
        return $this;
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return 'text';
    }


    #[\Override]
    public function getInputType(): string
    {
        return 'numeric';
    }

    #[\Override]
    public function getValueElementHtml(): string
    {
        $html = parent::getValueElementHtml();
        $html .= ' ' . Mage::helper('customersegmentation')->__('days');
        return $html;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        return $this->getSubfilterSql('customer_entity.entity_id', true, $websiteId);
    }

    public function getSubfilterSql(string $fieldName, bool $requireValid, ?int $website): string
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = (int) $this->getValue();

        $adapter = $this->getResource()->getReadConnection();
        $now = Mage::app()->getLocale()->utcDate(null, 'now', false, Mage_Core_Model_Locale::DATETIME_FORMAT);

        switch ($attribute) {
            case 'days_since_last_login':
                $logTable = $this->getResource()->getTable('log/customer');
                $select = $adapter->select()
                    ->from(['l' => $logTable], ['customer_id', 'days' => "DATEDIFF('{$now}', MAX(l.login_at))"])
                    ->where('l.customer_id IS NOT NULL')
                    ->group('l.customer_id')
                    ->having($this->getResource()->createConditionSql('days', $operator, $value));
                break;

            case 'days_since_last_order':
                $orderTable = $this->getResource()->getTable('sales/order');
                $select = $adapter->select()
                    ->from(['o' => $orderTable], ['customer_id', 'days' => "DATEDIFF('{$now}', MAX(o.created_at))"])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id')
                    ->having($this->getResource()->createConditionSql('days', $operator, $value));

                if ($website) {
                    $select->where('o.store_id IN (?)', $this->getStoreByWebsite($website));
                }
                break;

            case 'days_inactive':
                $logTable = $this->getResource()->getTable('log/customer');
                $orderTable = $this->getResource()->getTable('sales/order');

                // Get the most recent activity (login or order)
                $select = $adapter->select()
                    ->from(['c' => $this->getResource()->getTable('customer/entity')], ['entity_id'])
                    ->joinLeft(
                        ['l' => $logTable],
                        'c.entity_id = l.customer_id',
                        ['last_login' => 'MAX(l.login_at)'],
                    )
                    ->joinLeft(
                        ['o' => $orderTable],
                        'c.entity_id = o.customer_id AND o.state NOT IN ("canceled")',
                        ['last_order' => 'MAX(o.created_at)'],
                    )
                    ->columns([
                        'customer_id' => 'c.entity_id',
                        'days' => "DATEDIFF('{$now}', GREATEST(COALESCE(MAX(l.login_at), '1970-01-01'), COALESCE(MAX(o.created_at), '1970-01-01')))",
                    ])
                    ->group('c.entity_id')
                    ->having($this->getResource()->createConditionSql('days', $operator, $value));

                if ($website) {
                    $select->where('o.store_id IN (?) OR o.store_id IS NULL', $this->getStoreByWebsite($website));
                }
                break;

            case 'days_since_first_order':
                $orderTable = $this->getResource()->getTable('sales/order');
                $select = $adapter->select()
                    ->from(['o' => $orderTable], ['customer_id', 'days' => "DATEDIFF('{$now}', MIN(o.created_at))"])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id')
                    ->having($this->getResource()->createConditionSql('days', $operator, $value));

                if ($website) {
                    $select->where('o.store_id IN (?)', $this->getStoreByWebsite($website));
                }
                break;

            case 'order_frequency_days':
                $orderTable = $this->getResource()->getTable('sales/order');
                // Calculate average days between orders
                $select = $adapter->select()
                    ->from(['o' => $orderTable], [
                        'customer_id',
                        'days' => 'DATEDIFF(MAX(o.created_at), MIN(o.created_at)) / GREATEST(COUNT(*) - 1, 1)',
                    ])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id')
                    ->having('COUNT(*) > 1')  // Need at least 2 orders to calculate frequency
                    ->having($this->getResource()->createConditionSql('days', $operator, $value));

                if ($website) {
                    $select->where('o.store_id IN (?)', $this->getStoreByWebsite($website));
                }
                break;

            case 'days_without_purchase':
                $orderTable = $this->getResource()->getTable('sales/order');

                // Get customers who haven't purchased in X days
                $lastOrderSelect = $adapter->select()
                    ->from(['o' => $orderTable], ['customer_id', 'last_order' => 'MAX(o.created_at)'])
                    ->where('o.customer_id IS NOT NULL')
                    ->where('o.state NOT IN (?)', ['canceled'])
                    ->group('o.customer_id');

                if ($website) {
                    $lastOrderSelect->where('o.store_id IN (?)', $this->getStoreByWebsite($website));
                }

                $select = $adapter->select()
                    ->from(['lo' => new Zend_Db_Expr("({$lastOrderSelect})")], ['customer_id'])
                    ->where($this->getResource()->createConditionSql("DATEDIFF('{$now}', lo.last_order)", $operator, $value));

                // Also include customers with no orders if operator allows
                if (in_array($operator, ['>=', '>'])) {
                    $noOrderSelect = $adapter->select()
                        ->from(['c' => $this->getResource()->getTable('customer/entity')], ['entity_id'])
                        ->joinLeft(
                            ['o' => $orderTable],
                            'c.entity_id = o.customer_id AND o.state NOT IN ("canceled")',
                            [],
                        )
                        ->where('o.entity_id IS NULL');

                    $unionSelect = $adapter->select()->union([$select, $noOrderSelect]);
                    $select = $adapter->select()
                        ->from(['u' => new Zend_Db_Expr("({$unionSelect})")], ['customer_id']);
                }
                break;

            default:
                return $requireValid ? 'FALSE' : 'TRUE';
        }

        // Build the final condition
        $customerIds = $adapter->select()
            ->from(['timedata' => new Zend_Db_Expr("({$select})")], ['customer_id']);

        if ($requireValid) {
            return $adapter->quoteInto("{$fieldName} IN (?)", new Zend_Db_Expr($customerIds));
        } else {
            return $adapter->quoteInto("{$fieldName} NOT IN (?) OR {$fieldName} IS NULL", new Zend_Db_Expr($customerIds));
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
