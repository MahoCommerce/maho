<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Order_Coupons extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_order_coupons');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Coupon Usage'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'has_used_coupons' => Mage::helper('customersegmentation')->__('Has Used Coupons'),
            'coupon_usage_count' => Mage::helper('customersegmentation')->__('Number of Coupon Uses'),
            'specific_coupon_code' => Mage::helper('customersegmentation')->__('Used Specific Coupon Code'),
            'coupon_rule_id' => Mage::helper('customersegmentation')->__('Used Specific Coupon Rule'),
            'total_coupon_discount' => Mage::helper('customersegmentation')->__('Total Coupon Discount Amount'),
            'last_coupon_usage_date' => Mage::helper('customersegmentation')->__('Last Coupon Usage Date'),
            'days_since_last_coupon' => Mage::helper('customersegmentation')->__('Days Since Last Coupon Usage'),
            'coupon_usage_frequency' => Mage::helper('customersegmentation')->__('Coupon Usage Frequency (%)'),
            'average_coupon_discount' => Mage::helper('customersegmentation')->__('Average Coupon Discount'),
            'coupon_abandoner' => Mage::helper('customersegmentation')->__('Abandons Cart After Coupon Application'),
            'first_order_coupon_user' => Mage::helper('customersegmentation')->__('Used Coupon on First Order'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'has_used_coupons', 'coupon_abandoner', 'first_order_coupon_user' => 'select',
            'coupon_usage_count', 'total_coupon_discount', 'days_since_last_coupon', 'coupon_usage_frequency', 'average_coupon_discount' => 'numeric',
            'last_coupon_usage_date' => 'date',
            'specific_coupon_code' => 'string',
            'coupon_rule_id' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'has_used_coupons', 'coupon_abandoner', 'first_order_coupon_user', 'coupon_rule_id' => 'select',
            'last_coupon_usage_date' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'has_used_coupons':
            case 'coupon_abandoner':
            case 'first_order_coupon_user':
                $options = [
                    ['value' => '1', 'label' => Mage::helper('customersegmentation')->__('Yes')],
                    ['value' => '0', 'label' => Mage::helper('customersegmentation')->__('No')],
                ];
                break;
            case 'coupon_rule_id':
                // Get active coupon rules
                $rules = Mage::getResourceModel('salesrule/rule_collection')
                    ->addFieldToFilter('is_active', 1)
                    ->addFieldToFilter('coupon_type', ['neq' => Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON])
                    ->setOrder('name', 'ASC');

                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                foreach ($rules as $rule) {
                    $options[] = [
                        'value' => $rule->getId(),
                        'label' => $rule->getName(),
                    ];
                }
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
            'has_used_coupons' => $this->buildHasUsedCouponsCondition($adapter, $operator, $value),
            'coupon_usage_count' => $this->buildCouponUsageCountCondition($adapter, $operator, $value),
            'specific_coupon_code' => $this->buildSpecificCouponCodeCondition($adapter, $operator, $value),
            'coupon_rule_id' => $this->buildCouponRuleIdCondition($adapter, $operator, $value),
            'total_coupon_discount' => $this->buildTotalCouponDiscountCondition($adapter, $operator, $value),
            'last_coupon_usage_date' => $this->buildLastCouponUsageDateCondition($adapter, $operator, $value),
            'days_since_last_coupon' => $this->buildDaysSinceLastCouponCondition($adapter, $operator, $value),
            'coupon_usage_frequency' => $this->buildCouponUsageFrequencyCondition($adapter, $operator, $value),
            'average_coupon_discount' => $this->buildAverageCouponDiscountCondition($adapter, $operator, $value),
            'coupon_abandoner' => $this->buildCouponAbandonerCondition($adapter, $operator, $value),
            'first_order_coupon_user' => $this->buildFirstOrderCouponUserCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildHasUsedCouponsCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $condition = 'e.entity_id IN (
            SELECT DISTINCT customer_id 
            FROM ' . $orderTable . '
            WHERE customer_id IS NOT NULL 
            AND coupon_code IS NOT NULL 
            AND coupon_code != ""
            AND state NOT IN ("canceled")
        )';

        if (($operator == '=' && $value == '0') || ($operator == '!=' && $value == '1')) {
            $condition = 'e.entity_id NOT IN (
                SELECT DISTINCT customer_id 
                FROM ' . $orderTable . '
                WHERE customer_id IS NOT NULL 
                AND coupon_code IS NOT NULL 
                AND coupon_code != ""
                AND state NOT IN ("canceled")
            )';
        }

        return $condition;
    }

    protected function buildCouponUsageCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id', 'coupon_count' => 'COUNT(*)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.coupon_code IS NOT NULL')
            ->where('o.coupon_code != ""')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'coupon_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildSpecificCouponCodeCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->where($this->_buildSqlCondition($adapter, 'o.coupon_code', $operator, $value))
            ->group('o.customer_id');

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCouponRuleIdCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->where($this->_buildSqlCondition($adapter, 'o.applied_rule_ids', 'LIKE', '%' . $value . '%'))
            ->group('o.customer_id');

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildTotalCouponDiscountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id', 'total_discount' => 'SUM(ABS(o.discount_amount))'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.coupon_code IS NOT NULL')
            ->where('o.coupon_code != ""')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'total_discount', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLastCouponUsageDateCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id', 'last_coupon_date' => 'MAX(o.created_at)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.coupon_code IS NOT NULL')
            ->where('o.coupon_code != ""')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'last_coupon_date', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceLastCouponCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id', 'last_coupon_date' => 'MAX(o.created_at)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.coupon_code IS NOT NULL')
            ->where('o.coupon_code != ""')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), last_coupon_date)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCouponUsageFrequencyCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], [
                'customer_id',
                'frequency' => '(COUNT(CASE WHEN o.coupon_code IS NOT NULL AND o.coupon_code != "" THEN 1 END) / COUNT(*)) * 100',
            ])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'frequency', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildAverageCouponDiscountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id', 'avg_discount' => 'AVG(ABS(o.discount_amount))'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.coupon_code IS NOT NULL')
            ->where('o.coupon_code != ""')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'avg_discount', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCouponAbandonerCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // Find customers who have abandoned carts with coupons applied
        $quoteTable = $this->getQuoteTable();
        $condition = 'e.entity_id IN (
            SELECT DISTINCT customer_id 
            FROM ' . $quoteTable . ' 
            WHERE customer_id IS NOT NULL 
            AND is_active = 1 
            AND items_count > 0
            AND coupon_code IS NOT NULL 
            AND coupon_code != ""
            AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        )';

        if (($operator == '=' && $value == '0') || ($operator == '!=' && $value == '1')) {
            $condition = 'e.entity_id NOT IN (
                SELECT DISTINCT customer_id 
                FROM ' . $quoteTable . ' 
                WHERE customer_id IS NOT NULL 
                AND is_active = 1 
                AND items_count > 0
                AND coupon_code IS NOT NULL 
                AND coupon_code != ""
                AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            )';
        }

        return $condition;
    }

    protected function buildFirstOrderCouponUserCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $orderTable = $this->getOrderTable();
        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->join(
                ['first_order' => new Zend_Db_Expr("(SELECT customer_id, MIN(created_at) as first_order_date FROM {$orderTable} WHERE customer_id IS NOT NULL AND state NOT IN ('canceled') GROUP BY customer_id)")],
                'o.customer_id = first_order.customer_id AND o.created_at = first_order.first_order_date',
                [],
            )
            ->where('o.customer_id IS NOT NULL')
            ->where('o.coupon_code IS NOT NULL')
            ->where('o.coupon_code != ""')
            ->group('o.customer_id');

        if (($operator == '=' && $value == '0') || ($operator == '!=' && $value == '1')) {
            return 'e.entity_id NOT IN (' . $subselect . ')';
        }

        return 'e.entity_id IN (' . $subselect . ')';
    }
}
