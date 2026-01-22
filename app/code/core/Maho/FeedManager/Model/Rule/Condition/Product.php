<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Rule_Condition_Product extends Mage_Rule_Model_Condition_Product_Abstract
{
    /**
     * Validate product attribute value for condition
     */
    #[\Override]
    public function validate(\Maho\DataObject $object): bool
    {
        $attrCode = $this->getAttribute();

        if ($attrCode === 'category_ids') {
            return $this->validateAttribute($object->getCategoryIds());
        }

        if ($attrCode === 'attribute_set_id') {
            return $this->validateAttribute($object->getData($attrCode));
        }

        if ($attrCode === 'type_id') {
            return $this->validateAttribute($object->getData($attrCode));
        }

        // Handle stock attributes specially
        if ($attrCode === 'is_in_stock' || $attrCode === 'qty') {
            return $this->_validateStockAttribute($object);
        }

        $oldAttrValue = $object->hasData($attrCode) ? $object->getData($attrCode) : null;
        $object->setData($attrCode, $this->_getAttributeValue($object));
        $result = $this->_validateProduct($object);
        $this->_restoreOldAttrValue($object, $oldAttrValue);

        return (bool) $result;
    }

    /**
     * Validate stock-related attributes
     */
    protected function _validateStockAttribute(\Maho\DataObject $object): bool
    {
        $attrCode = $this->getAttribute();

        if ($object->hasData($attrCode)) {
            return $this->validateAttribute($object->getData($attrCode));
        }

        // Try to get from stock item
        $stockItem = $object->getStockItem();
        if (!$stockItem) {
            $stockItem = Mage::getModel('cataloginventory/stock_item');
            $stockItem->loadByProduct($object->getId());
        }

        if ($attrCode === 'is_in_stock') {
            return $this->validateAttribute((int) $stockItem->getIsInStock());
        }

        if ($attrCode === 'qty') {
            return $this->validateAttribute((float) $stockItem->getQty());
        }

        return false;
    }

    /**
     * Validate product
     */
    protected function _validateProduct(\Maho\DataObject $object): bool
    {
        return Mage_Rule_Model_Condition_Abstract::validate($object);
    }

    /**
     * Restore old attribute value
     */
    protected function _restoreOldAttrValue(\Maho\DataObject $object, mixed $oldAttrValue): void
    {
        $attrCode = $this->getAttribute();
        if (is_null($oldAttrValue)) {
            $object->unsetData($attrCode);
        } else {
            $object->setData($attrCode, $oldAttrValue);
        }
    }

    /**
     * Get attribute value
     */
    protected function _getAttributeValue(\Maho\DataObject $object): mixed
    {
        $attrCode = $this->getAttribute();
        $storeId = $object->getStoreId();
        $defaultStoreId = Mage_Core_Model_App::ADMIN_STORE_ID;
        $productValues = $this->_entityAttributeValues[$object->getId()] ?? [];
        $defaultValue = $productValues[$defaultStoreId] ?? $object->getData($attrCode);
        $value = $productValues[$storeId] ?? $defaultValue;

        $value = $this->_prepareDatetimeValue($value, $object);
        $value = $this->_prepareMultiselectValue($value, $object);

        return $value;
    }

    /**
     * Prepare datetime attribute value
     */
    protected function _prepareDatetimeValue(mixed $value, \Maho\DataObject $object): mixed
    {
        $attribute = $object->getResource()->getAttribute($this->getAttribute());
        if ($attribute && $attribute->getBackendType() === 'datetime') {
            if (!$value) {
                return null;
            }
            $value = strtotime($value);
        }
        return $value;
    }

    /**
     * Prepare multiselect attribute value
     */
    protected function _prepareMultiselectValue(mixed $value, \Maho\DataObject $object): mixed
    {
        $attribute = $object->getResource()->getAttribute($this->getAttribute());
        if ($attribute && $attribute->getFrontendInput() === 'multiselect') {
            $value = strlen((string) $value) ? explode(',', (string) $value) : [];
        }
        return $value;
    }

    /**
     * Load attribute options with additional feed-specific attributes
     */
    #[\Override]
    public function loadAttributeOptions(): self
    {
        $productAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        $attributes = [];

        // Add category_ids as special attribute
        $attributes['category_ids'] = Mage::helper('feedmanager')->__('Category');

        // Add product type
        $attributes['type_id'] = Mage::helper('feedmanager')->__('Product Type');

        // Add attribute set
        $attributes['attribute_set_id'] = Mage::helper('feedmanager')->__('Attribute Set');

        // Add stock attributes
        $attributes['is_in_stock'] = Mage::helper('feedmanager')->__('Stock Availability');
        $attributes['qty'] = Mage::helper('feedmanager')->__('Quantity');

        foreach ($productAttributes as $attribute) {
            $label = $attribute->getFrontendLabel();
            if ($label) {
                $attributes[$attribute->getAttributeCode()] = $label;
            }
        }

        asort($attributes);
        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Get input type for attribute
     */
    #[\Override]
    public function getInputType(): string
    {
        switch ($this->getAttribute()) {
            case 'category_ids':
                return 'multiselect';
            case 'type_id':
            case 'attribute_set_id':
            case 'is_in_stock':
                return 'select';
            case 'qty':
                return 'numeric';
        }

        $attribute = $this->getAttributeObject();
        if ($attribute) {
            switch ($attribute->getFrontendInput()) {
                case 'select':
                case 'boolean':
                    return 'select';
                case 'multiselect':
                    return 'multiselect';
                case 'date':
                    return 'date';
                case 'price':
                case 'weight':
                    return 'numeric';
            }
        }

        return 'string';
    }

    /**
     * Get value element type
     */
    #[\Override]
    public function getValueElementType(): string
    {
        switch ($this->getAttribute()) {
            case 'category_ids':
                return 'multiselect';
            case 'type_id':
            case 'attribute_set_id':
            case 'is_in_stock':
                return 'select';
        }

        $attribute = $this->getAttributeObject();
        if ($attribute) {
            switch ($attribute->getFrontendInput()) {
                case 'select':
                case 'boolean':
                    return 'select';
                case 'multiselect':
                    return 'multiselect';
                case 'date':
                    return 'date';
            }
        }

        return 'text';
    }

    /**
     * Get value select options
     */
    #[\Override]
    public function getValueSelectOptions(): array
    {
        if (!$this->hasData('value_select_options')) {
            $options = match ($this->getAttribute()) {
                'category_ids' => $this->_getCategoryOptions(),
                'type_id' => $this->_getProductTypeOptions(),
                'attribute_set_id' => $this->_getAttributeSetOptions(),
                'is_in_stock' => [
                    ['value' => 1, 'label' => Mage::helper('feedmanager')->__('In Stock')],
                    ['value' => 0, 'label' => Mage::helper('feedmanager')->__('Out of Stock')],
                ],
                default => parent::getValueSelectOptions(),
            };
            $this->setData('value_select_options', $options);
        }
        return $this->getData('value_select_options');
    }

    /**
     * Get category options
     */
    protected function _getCategoryOptions(): array
    {
        $options = [];
        $collection = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSort('path', 'asc')
            ->addIsActiveFilter();

        foreach ($collection as $category) {
            if ($category->getId() == 1) {
                continue;
            }
            $level = $category->getLevel();
            $prefix = str_repeat('— ', max(0, $level - 1));
            $options[] = [
                'value' => $category->getId(),
                'label' => $prefix . $category->getName(),
            ];
        }

        return $options;
    }

    /**
     * Get product type options
     */
    protected function _getProductTypeOptions(): array
    {
        $options = [];
        foreach (Mage::getSingleton('catalog/product_type')->getOptionArray() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    /**
     * Get attribute set options
     */
    protected function _getAttributeSetOptions(): array
    {
        $entityTypeId = Mage::getModel('eav/entity')
            ->setType(Mage_Catalog_Model_Product::ENTITY)
            ->getTypeId();

        $options = [];
        $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter($entityTypeId);

        foreach ($collection as $attributeSet) {
            $options[] = [
                'value' => $attributeSet->getId(),
                'label' => $attributeSet->getAttributeSetName(),
            ];
        }

        return $options;
    }

    /**
     * Get explicit apply for category_ids
     */
    #[\Override]
    public function getExplicitApply(): bool
    {
        return $this->getAttribute() === 'category_ids';
    }
}
