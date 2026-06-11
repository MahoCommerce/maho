<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Rule_Condition_Product extends Mage_Rule_Model_Condition_Product_Abstract
{
    /**
     * Validate product attribute value for condition
     */
    #[\Override]
    public function validate(\Maho\DataObject $object): bool
    {
        $attrCode = $this->getAttribute();
        if ($attrCode == 'category_ids') {
            return $this->validateAttribute($object->getCategoryIds());
        }
        if ($attrCode == 'attribute_set_id') {
            return $this->validateAttribute($object->getData($attrCode));
        }
        if ($attrCode == 'type_id') {
            return $this->validateAttribute($object->getData($attrCode));
        }

        $oldAttrValue = $object->hasData($attrCode) ? $object->getData($attrCode) : null;
        $object->setData($attrCode, $this->_getAttributeValue($object));
        $result = $this->_validateProduct($object);
        $this->_restoreOldAttrValue($object, $oldAttrValue);

        return (bool) $result;
    }

    protected function _validateProduct(\Maho\DataObject $object): bool
    {
        return Mage_Rule_Model_Condition_Abstract::validate($object);
    }

    protected function _restoreOldAttrValue(\Maho\DataObject $object, mixed $oldAttrValue): void
    {
        $attrCode = $this->getAttribute();
        if (is_null($oldAttrValue)) {
            $object->unsetData($attrCode);
        } else {
            $object->setData($attrCode, $oldAttrValue);
        }
    }

    protected function _getAttributeValue(\Maho\DataObject $object): mixed
    {
        $attrCode = $this->getAttribute();
        $storeId = $object->getStoreId();
        $defaultStoreId = Mage_Core_Model_App::ADMIN_STORE_ID;
        $productValues  = $this->_entityAttributeValues[$object->getId()] ?? [];
        $defaultValue = $productValues[$defaultStoreId] ?? $object->getData($attrCode);
        $value = $productValues[$storeId] ?? $defaultValue;

        $value = $this->_prepareDatetimeValue($value, $object);
        $value = $this->_prepareMultiselectValue($value, $object);

        return $value;
    }

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

    protected function _prepareMultiselectValue(mixed $value, \Maho\DataObject $object): mixed
    {
        $attribute = $object->getResource()->getAttribute($this->getAttribute());
        if ($attribute && $attribute->getFrontendInput() == 'multiselect') {
            $value = strlen($value) ? explode(',', $value) : [];
        }
        return $value;
    }
}
