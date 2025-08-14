<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Cart_Items extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_cart_items');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Cart Items'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'product_id' => Mage::helper('customersegmentation')->__('Product ID'),
            'sku' => Mage::helper('customersegmentation')->__('Product SKU'),
            'name' => Mage::helper('customersegmentation')->__('Product Name'),
            'qty' => Mage::helper('customersegmentation')->__('Quantity'),
            'price' => Mage::helper('customersegmentation')->__('Price'),
            'base_price' => Mage::helper('customersegmentation')->__('Base Price'),
            'row_total' => Mage::helper('customersegmentation')->__('Row Total'),
            'base_row_total' => Mage::helper('customersegmentation')->__('Base Row Total'),
            'product_type' => Mage::helper('customersegmentation')->__('Product Type'),
            'created_at' => Mage::helper('customersegmentation')->__('Added to Cart Date'),
            'updated_at' => Mage::helper('customersegmentation')->__('Last Updated Date'),
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
            'product_id', 'qty', 'price', 'base_price', 'row_total', 'base_row_total' => 'numeric',
            'created_at', 'updated_at' => 'date',
            'product_type' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'created_at', 'updated_at' => 'date',
            'product_type' => 'select',
            'product_id' => 'text',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'product_type':
                $options = [
                    ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')],
                    ['value' => 'simple', 'label' => Mage::helper('customersegmentation')->__('Simple Product')],
                    ['value' => 'configurable', 'label' => Mage::helper('customersegmentation')->__('Configurable Product')],
                    ['value' => 'grouped', 'label' => Mage::helper('customersegmentation')->__('Grouped Product')],
                    ['value' => 'bundle', 'label' => Mage::helper('customersegmentation')->__('Bundle Product')],
                    ['value' => 'downloadable', 'label' => Mage::helper('customersegmentation')->__('Downloadable Product')],
                    ['value' => 'virtual', 'label' => Mage::helper('customersegmentation')->__('Virtual Product')],
                ];
                break;
        }
        return $options;
    }

    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'product_id', 'sku', 'name', 'qty', 'price', 'base_price', 'row_total', 'base_row_total', 'product_type', 'created_at', 'updated_at' => $this->_buildCartItemFieldCondition($adapter, $attribute, $operator, $value),
            default => false,
        };
    }

    protected function _buildCartItemFieldCondition(Varien_Db_Adapter_Interface $adapter, string $field, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['qi' => $this->_getQuoteItemTable()], ['quote_id'])
            ->join(['q' => $this->_getQuoteTable()], 'qi.quote_id = q.entity_id', ['customer_id'])
            ->where('q.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, "qi.{$field}", $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    #[\Override]
    protected function _getQuoteTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/quote');
    }

    protected function _getQuoteItemTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/quote_item');
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $attributeOptions = $this->loadAttributeOptions()->getAttributeOption();
        $attributeLabel = $attributeOptions[$attribute] ?? $attribute;

        return $attributeLabel . ' ' . $this->getOperatorName() . ' ' . $this->getValueName();
    }
}
