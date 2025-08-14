<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Cart_Attributes extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_cart_attributes');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Shopping Cart Information'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'items_count' => Mage::helper('customersegmentation')->__('Items Count'),
            'items_qty' => Mage::helper('customersegmentation')->__('Items Quantity'),
            'base_subtotal' => Mage::helper('customersegmentation')->__('Subtotal'),
            'base_grand_total' => Mage::helper('customersegmentation')->__('Grand Total'),
            'created_at' => Mage::helper('customersegmentation')->__('Cart Created Date'),
            'updated_at' => Mage::helper('customersegmentation')->__('Cart Updated Date'),
            'is_active' => Mage::helper('customersegmentation')->__('Cart Status'),
            'store_id' => Mage::helper('customersegmentation')->__('Store'),
            'applied_rule_ids' => Mage::helper('customersegmentation')->__('Applied Promotion Rules'),
            'coupon_code' => Mage::helper('customersegmentation')->__('Coupon Code'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getAttributeElement(): Varien_Data_Form_Element_Abstract
    {
        if (!$this->hasAttributeOption()) {
            $this->loadAttributeOptions();
        }

        $element = parent::getAttributeElement();
        return $element;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'items_count', 'items_qty', 'base_subtotal', 'base_grand_total' => 'numeric',
            'created_at', 'updated_at' => 'date',
            'is_active', 'store_id' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'created_at', 'updated_at' => 'date',
            'is_active', 'store_id' => 'select',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'is_active':
                $options = [
                    ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')],
                    ['value' => '1', 'label' => Mage::helper('customersegmentation')->__('Active')],
                    ['value' => '0', 'label' => Mage::helper('customersegmentation')->__('Inactive')],
                ];
                break;
            case 'store_id':
                $options = Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm();
                if (empty($options) || $options[0]['value'] !== '') {
                    array_unshift($options, ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]);
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
            'items_count', 'items_qty', 'base_subtotal', 'base_grand_total', 'created_at', 'updated_at', 'is_active', 'store_id', 'coupon_code' => $this->_buildCartFieldCondition($adapter, $attribute, $operator, $value),
            'applied_rule_ids' => $this->_buildAppliedRulesCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function _buildCartFieldCondition(Varien_Db_Adapter_Interface $adapter, string $field, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['q' => $this->_getQuoteTable()], ['customer_id'])
            ->where('q.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, "q.{$field}", $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildAppliedRulesCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['q' => $this->_getQuoteTable()], ['customer_id'])
            ->where('q.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'q.applied_rule_ids', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    #[\Override]
    protected function _getQuoteTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/quote');
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $this->loadAttributeOptions();
        $attributeOptions = $this->getAttributeOption();
        $attributeLabel = is_array($attributeOptions) && isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;

        return $attributeLabel . ' ' . $this->getOperatorName() . ' ' . $this->getValueName();
    }
}
