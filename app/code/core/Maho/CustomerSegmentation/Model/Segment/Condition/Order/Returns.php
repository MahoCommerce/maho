<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Order_Returns extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_order_returns');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Return/Refund History'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'has_returns' => Mage::helper('customersegmentation')->__('Has Made Returns'),
            'return_count' => Mage::helper('customersegmentation')->__('Number of Returns'),
            'return_rate' => Mage::helper('customersegmentation')->__('Return Rate (%)'),
            'total_refunded' => Mage::helper('customersegmentation')->__('Total Amount Refunded'),
            'last_return_date' => Mage::helper('customersegmentation')->__('Last Return Date'),
            'days_since_last_return' => Mage::helper('customersegmentation')->__('Days Since Last Return'),
            'has_credit_memos' => Mage::helper('customersegmentation')->__('Has Credit Memos'),
            'credit_memo_count' => Mage::helper('customersegmentation')->__('Number of Credit Memos'),
            'return_reason' => Mage::helper('customersegmentation')->__('Return Reason'),
            'average_return_processing_days' => Mage::helper('customersegmentation')->__('Average Return Processing Days'),
            'refund_to_purchase_ratio' => Mage::helper('customersegmentation')->__('Refund to Purchase Ratio'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'has_returns', 'has_credit_memos' => 'select',
            'return_count', 'return_rate', 'total_refunded', 'days_since_last_return', 'credit_memo_count', 'average_return_processing_days', 'refund_to_purchase_ratio' => 'numeric',
            'last_return_date' => 'date',
            'return_reason' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'has_returns', 'has_credit_memos', 'return_reason' => 'select',
            'last_return_date' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        $options = match ($this->getAttribute()) {
            'has_returns', 'has_credit_memos' => [
                ['value' => '1', 'label' => Mage::helper('customersegmentation')->__('Yes')],
                ['value' => '0', 'label' => Mage::helper('customersegmentation')->__('No')],
            ],
            // These would typically come from a configuration or database
            'return_reason' => [
                ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')],
                ['value' => 'defective', 'label' => Mage::helper('customersegmentation')->__('Defective Product')],
                ['value' => 'wrong_item', 'label' => Mage::helper('customersegmentation')->__('Wrong Item Shipped')],
                ['value' => 'not_as_described', 'label' => Mage::helper('customersegmentation')->__('Not as Described')],
                ['value' => 'damaged', 'label' => Mage::helper('customersegmentation')->__('Damaged in Shipping')],
                ['value' => 'other', 'label' => Mage::helper('customersegmentation')->__('Other')],
            ],
            default => $options,
        };
        return $options;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'has_returns' => $this->buildHasReturnsCondition($adapter, $operator, $value),
            'return_count' => $this->buildReturnCountCondition($adapter, $operator, $value),
            'return_rate' => $this->buildReturnRateCondition($adapter, $operator, $value),
            'total_refunded' => $this->buildTotalRefundedCondition($adapter, $operator, $value),
            'last_return_date' => $this->buildLastReturnDateCondition($adapter, $operator, $value),
            'days_since_last_return' => $this->buildDaysSinceLastReturnCondition($adapter, $operator, $value),
            'has_credit_memos' => $this->buildHasCreditMemosCondition($adapter, $operator, $value),
            'credit_memo_count' => $this->buildCreditMemoCountCondition($adapter, $operator, $value),
            'return_reason' => $this->buildReturnReasonCondition($adapter, $operator, $value),
            'average_return_processing_days' => $this->buildAverageReturnProcessingDaysCondition($adapter, $operator, $value),
            'refund_to_purchase_ratio' => $this->buildRefundToPurchaseRatioCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildHasReturnsCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $condition = 'e.entity_id IN (
            SELECT DISTINCT o.customer_id 
            FROM ' . $orderTable . ' o
            INNER JOIN ' . $creditMemoTable . ' cm ON o.entity_id = cm.order_id
            WHERE o.customer_id IS NOT NULL
        )';

        if (($operator == '=' && $value == '0') || ($operator == '!=' && $value == '1')) {
            $condition = 'e.entity_id NOT IN (
                SELECT DISTINCT o.customer_id 
                FROM ' . $orderTable . ' o
                INNER JOIN ' . $creditMemoTable . ' cm ON o.entity_id = cm.order_id
                WHERE o.customer_id IS NOT NULL
            )';
        }

        return $condition;
    }

    protected function buildReturnCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->join(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', ['return_count' => 'COUNT(cm.entity_id)'])
            ->where('o.customer_id IS NOT NULL')
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'return_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildReturnRateCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], [
                'customer_id',
                'return_rate' => '(COUNT(DISTINCT cm.order_id) / COUNT(DISTINCT o.entity_id)) * 100',
            ])
            ->joinLeft(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'return_rate', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildTotalRefundedCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->join(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', ['total_refunded' => 'SUM(cm.grand_total)'])
            ->where('o.customer_id IS NOT NULL')
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'total_refunded', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLastReturnDateCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->join(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', ['last_return' => 'MAX(cm.created_at)'])
            ->where('o.customer_id IS NOT NULL')
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'last_return', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceLastReturnCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->join(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', ['last_return' => 'MAX(cm.created_at)'])
            ->where('o.customer_id IS NOT NULL')
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), last_return)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildHasCreditMemosCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        return $this->buildHasReturnsCondition($adapter, $operator, $value);
    }

    protected function buildCreditMemoCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        return $this->buildReturnCountCondition($adapter, $operator, $value);
    }

    protected function buildReturnReasonCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // This would require a custom field in credit memo comments or a separate table
        // For now, check if there are credit memos with comments containing the reason
        $creditMemoTable = $this->getCreditMemoTable();
        $creditMemoCommentTable = $this->getCreditMemoCommentTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->join(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', [])
            ->join(['cmc' => $creditMemoCommentTable], 'cm.entity_id = cmc.parent_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'cmc.comment', 'LIKE', '%' . $value . '%'))
            ->group('o.customer_id');

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildAverageReturnProcessingDaysCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], ['customer_id'])
            ->join(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', [
                'avg_days' => 'AVG(DATEDIFF(cm.created_at, o.created_at))',
            ])
            ->where('o.customer_id IS NOT NULL')
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'avg_days', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildRefundToPurchaseRatioCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $creditMemoTable = $this->getCreditMemoTable();
        $orderTable = $this->getOrderTable();

        $subselect = $adapter->select()
            ->from(['o' => $orderTable], [
                'customer_id',
                'ratio' => 'COALESCE(SUM(cm.grand_total), 0) / SUM(o.grand_total)',
            ])
            ->joinLeft(['cm' => $creditMemoTable], 'o.entity_id = cm.order_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'ratio', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getCreditMemoTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/creditmemo');
    }

    protected function getCreditMemoCommentTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/creditmemo_comment');
    }
}
