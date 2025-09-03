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

class Maho_CustomerSegmentation_Model_Segment_Condition_Order_Items extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_order_items');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Order Items'),
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
                $attribute->getStoreLabel() ?: $attribute->getFrontendLabel();
        }

        // Add order item specific attributes
        $attributes['qty_ordered'] = Mage::helper('customersegmentation')->__('Quantity Ordered');
        $attributes['row_total'] = Mage::helper('customersegmentation')->__('Row Total');
        $attributes['row_total_incl_tax'] = Mage::helper('customersegmentation')->__('Row Total (Inc. Tax)');
        $attributes['discount_amount'] = Mage::helper('customersegmentation')->__('Discount Amount');
        $attributes['product_type'] = Mage::helper('customersegmentation')->__('Product Type');

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        $attribute = $this->getAttribute();
        if (!$attribute) {
            return 'string';
        }

        // Handle product attributes
        if (str_starts_with($attribute, 'product_')) {
            $attributeCode = substr($attribute, 8); // Remove 'product_' prefix
            $productAttribute = Mage::getSingleton('eav/config')
                ->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeCode);

            if ($productAttribute) {
                return match ($productAttribute->getFrontendInput()) {
                    'select', 'multiselect' => 'select',
                    'date' => 'date',
                    'price' => 'numeric',
                    default => 'string',
                };
            }
        }

        // Handle order item specific attributes
        return match ($attribute) {
            'qty_ordered', 'row_total', 'row_total_incl_tax', 'discount_amount' => 'numeric',
            'product_type' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return $this->getInputType() === 'select' ? 'select' : 'text';
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $attribute = $this->getAttribute();
        if (!$attribute) {
            return [];
        }

        // Handle product attributes
        if (str_starts_with($attribute, 'product_')) {
            $attributeCode = substr($attribute, 8);
            $productAttribute = Mage::getSingleton('eav/config')
                ->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeCode);

            if ($productAttribute && $productAttribute->usesSource()) {
                $options = [];
                foreach ($productAttribute->getSource()->getAllOptions() as $option) {
                    if (is_array($option) && isset($option['value']) && $option['value'] !== '') {
                        $options[] = $option;
                    }
                }
                return $options;
            }
        }

        // Handle order item specific attributes
        if ($attribute === 'product_type') {
            return [
                ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')],
                ['value' => 'simple', 'label' => Mage::helper('customersegmentation')->__('Simple Product')],
                ['value' => 'configurable', 'label' => Mage::helper('customersegmentation')->__('Configurable Product')],
                ['value' => 'grouped', 'label' => Mage::helper('customersegmentation')->__('Grouped Product')],
                ['value' => 'bundle', 'label' => Mage::helper('customersegmentation')->__('Bundle Product')],
                ['value' => 'virtual', 'label' => Mage::helper('customersegmentation')->__('Virtual Product')],
                ['value' => 'downloadable', 'label' => Mage::helper('customersegmentation')->__('Downloadable Product')],
            ];
        }

        return [];
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();

        if (!$attribute || $value === null || $value === '') {
            return false;
        }

        // Handle product attributes
        if (str_starts_with($attribute, 'product_')) {
            $attributeCode = substr($attribute, 8);
            return $this->buildProductAttributeCondition($adapter, $attributeCode, $operator, $value);
        }

        // Handle order item specific attributes
        return match ($attribute) {
            'qty_ordered', 'row_total', 'row_total_incl_tax', 'discount_amount' => $this->buildOrderItemFieldCondition($adapter, $attribute, $operator, $value),
            'product_type' => $this->buildProductTypeCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildProductAttributeCondition(Varien_Db_Adapter_Interface $adapter, string $attributeCode, string $operator, mixed $value): string
    {
        $productResource = Mage::getResourceSingleton('catalog/product');
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeCode);

        if (!$attribute || !$attribute->getId()) {
            return 'FALSE';
        }

        $subselect = $adapter->select()
            ->from(['oi' => $this->getOrderItemTable()], [])
            ->join(['o' => $this->getOrderTable()], 'oi.order_id = o.entity_id', ['customer_id'])
            ->join(['p' => $productResource->getTable('catalog/product')], 'oi.product_id = p.entity_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled']);

        // Join the appropriate attribute table based on attribute backend type
        if ($attribute->getBackendType() == 'static') {
            $condition = $this->buildSqlCondition($adapter, "p.{$attributeCode}", $operator, $value);
            $subselect->where($condition);
        } else {
            $attributeTable = $attribute->getBackendTable();
            $subselect->join(
                ['pa' => $attributeTable],
                "pa.entity_id = p.entity_id AND pa.attribute_id = {$attribute->getId()} AND pa.store_id = 0",
                [],
            );
            $condition = $this->buildSqlCondition($adapter, 'pa.value', $operator, $value);
            $subselect->where($condition);
        }

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildOrderItemFieldCondition(Varien_Db_Adapter_Interface $adapter, string $field, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['oi' => $this->getOrderItemTable()], [])
            ->join(['o' => $this->getOrderTable()], 'oi.order_id = o.entity_id', ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->where($this->buildSqlCondition($adapter, "oi.{$field}", $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildProductTypeCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $productResource = Mage::getResourceSingleton('catalog/product');

        $subselect = $adapter->select()
            ->from(['oi' => $this->getOrderItemTable()], [])
            ->join(['o' => $this->getOrderTable()], 'oi.order_id = o.entity_id', ['customer_id'])
            ->join(['p' => $productResource->getTable('catalog/product')], 'oi.product_id = p.entity_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->where($this->buildSqlCondition($adapter, 'p.type_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    #[\Override]
    protected function getOrderTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/order');
    }

    protected function getOrderItemTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/order_item');
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Order Items') . ':' . ' ' . $attributeName;
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

        return Mage::helper('customersegmentation')->__('Order Items') . ': ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }
}
