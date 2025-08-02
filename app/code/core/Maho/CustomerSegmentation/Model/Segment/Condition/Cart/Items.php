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

    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Cart Items'),
        ];
    }

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

    public function getAttributeElement(): Varien_Data_Form_Element_Abstract
    {
        if (!$this->hasAttributeOption()) {
            $this->loadAttributeOptions();
        }
        
        $element = parent::getAttributeElement();
        return $element;
    }

    public function getInputType(): string
    {
        switch ($this->getAttribute()) {
            case 'product_id':
            case 'qty':
            case 'price':
            case 'base_price':
            case 'row_total':
            case 'base_row_total':
                return 'numeric';
            case 'created_at':
            case 'updated_at':
                return 'date';
            case 'product_type':
                return 'select';
            default:
                return 'string';
        }
    }

    public function getValueElementType(): string
    {
        switch ($this->getAttribute()) {
            case 'created_at':
            case 'updated_at':
                return 'date';
            case 'product_type':
                return 'select';
            case 'product_id':
                return 'text'; // Could be enhanced with product chooser
            default:
                return 'text';
        }
    }

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

        switch ($attribute) {
            case 'product_id':
            case 'sku':
            case 'name':
            case 'qty':
            case 'price':
            case 'base_price':
            case 'row_total':
            case 'base_row_total':
            case 'product_type':
            case 'created_at':
            case 'updated_at':
                return $this->_buildCartItemFieldCondition($adapter, $attribute, $operator, $value);
        }

        return false;
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

    protected function _getQuoteTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/quote');
    }

    protected function _getQuoteItemTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/quote_item');
    }

    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $attributeOptions = $this->loadAttributeOptions()->getAttributeOption();
        $attributeLabel = isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;

        return $attributeLabel . ' ' . $this->getOperatorName() . ' ' . $this->getValueName();
    }
}
