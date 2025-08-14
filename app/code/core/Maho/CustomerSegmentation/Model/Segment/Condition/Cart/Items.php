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
        // Load product attributes from EAV
        $productAttributes = Mage::getResourceSingleton('catalog/product')
            ->loadAllAttributes()
            ->getAttributesByCode();

        $attributes = [];
        
        // Add product EAV attributes
        foreach ($productAttributes as $attribute) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
            if (!$attribute->isAllowedForRuleCondition()
                || !$attribute->getData('is_used_for_promo_rules')
            ) {
                continue;
            }
            $attributes['product_' . $attribute->getAttributeCode()] = 
                Mage::helper('customersegmentation')->__('Product: %s', $attribute->getFrontendLabel());
        }

        // Add cart item specific attributes (from quote_item table)
        $cartItemAttributes = [
            'qty' => Mage::helper('customersegmentation')->__('Quantity in Cart'),
            'price' => Mage::helper('customersegmentation')->__('Price'),
            'base_price' => Mage::helper('customersegmentation')->__('Base Price'),
            'row_total' => Mage::helper('customersegmentation')->__('Row Total'),
            'base_row_total' => Mage::helper('customersegmentation')->__('Base Row Total'),
            'created_at' => Mage::helper('customersegmentation')->__('Added to Cart Date'),
            'updated_at' => Mage::helper('customersegmentation')->__('Last Updated Date'),
        ];

        $attributes = array_merge($attributes, $cartItemAttributes);
        
        asort($attributes);
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

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        
        // Handle product attributes (prefixed with 'product_')
        if (str_starts_with($attribute, 'product_')) {
            $productAttributeCode = substr($attribute, 8); // Remove 'product_' prefix
            return $this->_buildProductAttributeCondition($adapter, $productAttributeCode, $operator, $value);
        }
        
        // Handle cart item attributes (direct quote_item fields)
        return match ($attribute) {
            'qty', 'price', 'base_price', 'row_total', 'base_row_total', 'created_at', 'updated_at' => $this->_buildCartItemFieldCondition($adapter, $attribute, $operator, $value),
            default => false,
        };
    }

    protected function _buildCartItemFieldCondition(Varien_Db_Adapter_Interface $adapter, string $field, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['qi' => $this->_getQuoteItemTable()], [])
            ->join(['q' => $this->_getQuoteTable()], 'qi.quote_id = q.entity_id', ['customer_id'])
            ->where('q.customer_id IS NOT NULL')
            ->where('q.is_active = ?', 1)
            ->where($this->_buildSqlCondition($adapter, "qi.{$field}", $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildProductAttributeCondition(Varien_Db_Adapter_Interface $adapter, string $attributeCode, string $operator, mixed $value): string
    {
        $productResource = Mage::getResourceSingleton('catalog/product');
        $attribute = $productResource->getAttribute($attributeCode);
        
        if (!$attribute) {
            return false;
        }

        $attributeTable = $attribute->getBackend()->getTable();
        $attributeId = $attribute->getId();

        $subselect = $adapter->select()
            ->from(['qi' => $this->_getQuoteItemTable()], [])
            ->join(['q' => $this->_getQuoteTable()], 'qi.quote_id = q.entity_id', ['customer_id'])
            ->join(['p' => $productResource->getTable('catalog/product')], 'qi.product_id = p.entity_id', [])
            ->where('q.customer_id IS NOT NULL')
            ->where('q.is_active = ?', 1);

        // Join the appropriate attribute table based on attribute backend type
        if ($attribute->getBackendType() == 'static') {
            // Static attributes are stored directly in the product entity table
            $subselect->where($this->_buildSqlCondition($adapter, "p.{$attributeCode}", $operator, $value));
        } else {
            // EAV attributes need to be joined from their respective tables
            $subselect->join(
                ['attr' => $attributeTable],
                "attr.entity_id = p.entity_id AND attr.attribute_id = {$attributeId}",
                []
            )->where($this->_buildSqlCondition($adapter, 'attr.value', $operator, $value));
        }

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
        $this->loadAttributeOptions();
        $attributeOptions = $this->getAttributeOption();
        $attributeLabel = is_array($attributeOptions) && isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;

        $operatorName = $this->getOperatorName();
        $valueName = $this->getValueName();
        return $attributeLabel . ' ' . (is_string($operatorName) ? $operatorName : '') . ' ' . (is_string($valueName) ? $valueName : '');
    }
}
