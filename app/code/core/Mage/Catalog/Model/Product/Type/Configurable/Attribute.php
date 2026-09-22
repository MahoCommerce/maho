<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * @method Mage_Catalog_Model_Resource_Product_Type_Configurable_Attribute _getResource()
 * @method Mage_Catalog_Model_Resource_Product_Type_Configurable_Attribute getResource()
 * @method Mage_Catalog_Model_Resource_Product_Type_Configurable_Attribute_Collection getCollection()
 */
class Mage_Catalog_Model_Product_Type_Configurable_Attribute extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_type_configurable_attribute');
    }

    /**
     * Add price data to attribute
     *
     * @param array $priceData
     * @return $this
     */
    public function addPrice($priceData)
    {
        $data = $this->getPrices();
        $data ??= [];
        $data[] = $priceData;
        $this->setPrices($data);
        return $this;
    }

    /**
     * Retrieve attribute label
     *
     * @return string
     */
    public function getLabel()
    {
        if ($this->getData('use_default') && $this->getProductAttribute()) {
            return $this->getProductAttribute()->getStoreLabel();
        }
        if (is_null($this->getData('label')) && $this->getProductAttribute()) {
            $this->setData('label', $this->getProductAttribute()->getStoreLabel());
        }

        return $this->getData('label');
    }

    /**
     * After save process
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        parent::_afterSave();
        $this->_getResource()->saveLabel($this);
        $this->_getResource()->savePrices($this);
        return $this;
    }

    public function getAttributeCode(): ?string
    {
        $value = $this->getData('attribute_code');
        return $value === null ? null : (string) $value;
    }

    public function getAttributeId(): ?int
    {
        $value = $this->getData('attribute_id');
        return $value === null ? null : (int) $value;
    }

    public function setAttributeId(?int $value): static
    {
        return $this->setData('attribute_id', $value);
    }

    public function setLabel(?string $value): static
    {
        return $this->setData('label', $value);
    }

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    public function getPrices(): ?array
    {
        return $this->getData('prices');
    }

    public function setPrices(?array $value): static
    {
        return $this->setData('prices', $value);
    }

    public function getProductAttribute(): ?Mage_Eav_Model_Entity_Attribute_Abstract
    {
        return $this->getData('product_attribute');
    }

    public function setProductAttribute(?Mage_Eav_Model_Entity_Attribute_Abstract $value): static
    {
        return $this->setData('product_attribute', $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getUseDefault(): ?int
    {
        $value = $this->getData('use_default');
        return $value === null ? null : (int) $value;
    }

    public function setUseDefault(?int $value): static
    {
        return $this->setData('use_default', $value);
    }

    public function getValues(): ?array
    {
        return $this->getData('values');
    }

}
