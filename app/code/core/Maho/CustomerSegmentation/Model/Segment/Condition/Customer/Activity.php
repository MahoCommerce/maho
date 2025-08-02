<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Activity extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_activity');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Customer Activity'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'last_login_at' => Mage::helper('customersegmentation')->__('Last Login Date'),
            'days_since_last_login' => Mage::helper('customersegmentation')->__('Days Since Last Login'),
            'login_count' => Mage::helper('customersegmentation')->__('Total Login Count'),
            'login_frequency' => Mage::helper('customersegmentation')->__('Login Frequency (per month)'),
            'page_views_count' => Mage::helper('customersegmentation')->__('Page Views Count'),
            'last_activity_at' => Mage::helper('customersegmentation')->__('Last Activity Date'),
            'days_inactive' => Mage::helper('customersegmentation')->__('Days Inactive'),
            'is_online' => Mage::helper('customersegmentation')->__('Currently Online'),
            'abandoned_cart_count' => Mage::helper('customersegmentation')->__('Abandoned Cart Count'),
            'password_reset_count' => Mage::helper('customersegmentation')->__('Password Reset Count'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'last_login_at', 'last_activity_at' => 'date',
            'days_since_last_login', 'login_count', 'login_frequency', 'page_views_count', 'days_inactive', 'abandoned_cart_count', 'password_reset_count' => 'numeric',
            'is_online' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'last_login_at', 'last_activity_at' => 'date',
            'is_online' => 'select',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'is_online':
                $options = [
                    ['value' => '1', 'label' => Mage::helper('customersegmentation')->__('Yes')],
                    ['value' => '0', 'label' => Mage::helper('customersegmentation')->__('No')],
                ];
                break;
        }
        return $options;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'last_login_at' => $this->buildLastLoginCondition($adapter, $operator, $value),
            'days_since_last_login' => $this->buildDaysSinceLoginCondition($adapter, $operator, $value),
            'login_count' => $this->buildLoginCountCondition($adapter, $operator, $value),
            'login_frequency' => $this->buildLoginFrequencyCondition($adapter, $operator, $value),
            'page_views_count' => $this->buildPageViewsCondition($adapter, $operator, $value),
            'last_activity_at' => $this->buildLastActivityCondition($adapter, $operator, $value),
            'days_inactive' => $this->buildDaysInactiveCondition($adapter, $operator, $value),
            'is_online' => $this->buildIsOnlineCondition($adapter, $operator, $value),
            'abandoned_cart_count' => $this->buildAbandonedCartCountCondition($adapter, $operator, $value),
            'password_reset_count' => $this->buildPasswordResetCountCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildLastLoginCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $logTable = $this->getLogCustomerTable();
        $subselect = $adapter->select()
            ->from(['log' => $logTable], ['customer_id', 'last_login' => 'MAX(login_at)'])
            ->where('log.customer_id IS NOT NULL')
            ->group('log.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'last_login', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceLoginCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $logTable = $this->getLogCustomerTable();
        $subselect = $adapter->select()
            ->from(['log' => $logTable], ['customer_id', 'last_login' => 'MAX(login_at)'])
            ->where('log.customer_id IS NOT NULL')
            ->group('log.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), last_login)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLoginCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $logTable = $this->getLogCustomerTable();
        $subselect = $adapter->select()
            ->from(['log' => $logTable], ['customer_id', 'login_count' => 'COUNT(*)'])
            ->where('log.customer_id IS NOT NULL')
            ->group('log.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'login_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLoginFrequencyCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $logTable = $this->getLogCustomerTable();
        $subselect = $adapter->select()
            ->from(['log' => $logTable], [
                'customer_id',
                'frequency' => 'COUNT(*) / GREATEST(1, DATEDIFF(NOW(), MIN(login_at)) / 30)',
            ])
            ->where('log.customer_id IS NOT NULL')
            ->group('log.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'frequency', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildPageViewsCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // This would need custom tracking implementation
        // For now, return a condition based on log_visitor data
        $visitorTable = $this->getLogVisitorTable();
        $customerTable = $this->getLogCustomerTable();

        $subselect = $adapter->select()
            ->from(['lc' => $customerTable], ['customer_id'])
            ->join(['lv' => $visitorTable], 'lc.visitor_id = lv.visitor_id', [])
            ->where('lc.customer_id IS NOT NULL')
            ->group('lc.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'COUNT(DISTINCT lv.visitor_id)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLastActivityCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // Consider last login or last order as activity
        $logTable = $this->getLogCustomerTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['e' => $this->getCustomerTable()], ['entity_id'])
            ->joinLeft(
                ['log' => new Zend_Db_Expr("(SELECT customer_id, MAX(login_at) as last_login FROM {$logTable} GROUP BY customer_id)")],
                'e.entity_id = log.customer_id',
                [],
            )
            ->joinLeft(
                ['ord' => new Zend_Db_Expr("(SELECT customer_id, MAX(created_at) as last_order FROM {$orderTable} WHERE customer_id IS NOT NULL GROUP BY customer_id)")],
                'e.entity_id = ord.customer_id',
                [],
            )
            ->where($this->_buildSqlCondition($adapter, 'GREATEST(COALESCE(log.last_login, "2000-01-01"), COALESCE(ord.last_order, "2000-01-01"))', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysInactiveCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $logTable = $this->getLogCustomerTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['e' => $this->getCustomerTable()], ['entity_id'])
            ->joinLeft(
                ['log' => new Zend_Db_Expr("(SELECT customer_id, MAX(login_at) as last_login FROM {$logTable} GROUP BY customer_id)")],
                'e.entity_id = log.customer_id',
                [],
            )
            ->joinLeft(
                ['ord' => new Zend_Db_Expr("(SELECT customer_id, MAX(created_at) as last_order FROM {$orderTable} WHERE customer_id IS NOT NULL GROUP BY customer_id)")],
                'e.entity_id = ord.customer_id',
                [],
            )
            ->where($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), GREATEST(COALESCE(log.last_login, e.created_at), COALESCE(ord.last_order, e.created_at)))', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildIsOnlineCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $onlineTable = $this->getLogVisitorOnlineTable();
        $condition = 'e.entity_id IN (SELECT customer_id FROM ' . $onlineTable . ' WHERE customer_id IS NOT NULL)';

        if (($operator == '=' && $value == '0') || ($operator == '!=' && $value == '1')) {
            $condition = 'e.entity_id NOT IN (SELECT customer_id FROM ' . $onlineTable . ' WHERE customer_id IS NOT NULL)';
        }

        return $condition;
    }

    protected function buildAbandonedCartCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $quoteTable = $this->getQuoteTable();
        $subselect = $adapter->select()
            ->from(['q' => $quoteTable], ['customer_id', 'abandoned_count' => 'COUNT(*)'])
            ->where('q.customer_id IS NOT NULL')
            ->where('q.is_active = 1')
            ->where('q.items_count > 0')
            ->where('q.updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)') // Consider abandoned after 1 hour
            ->group('q.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'abandoned_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildPasswordResetCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // This would require tracking password resets in a custom table
        // For now, we'll check if customer has an RP token
        $field = 'e.rp_token';
        if ($operator == '>' && $value == '0') {
            return $field . ' IS NOT NULL';
        } elseif ($operator == '=' && $value == '0') {
            return $field . ' IS NULL';
        }

        // For actual count tracking, would need custom implementation
        return '1=1';
    }

    protected function getLogCustomerTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('log/customer');
    }

    protected function getLogVisitorTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('log/visitor');
    }

    protected function getLogVisitorOnlineTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('log/visitor_online');
    }
}
