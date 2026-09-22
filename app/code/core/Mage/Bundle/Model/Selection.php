<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

/**
 * Bundle Selection Model
 *
 * @package    Mage_Bundle
 *
 * @method Mage_Bundle_Model_Resource_Selection _getResource()
 * @method Mage_Bundle_Model_Resource_Selection getResource()
 * @method Mage_Bundle_Model_Resource_Selection_Collection getCollection()
 *
 * @method $this unsSelectionPriceValue()
 * @method $this unsSelectionPriceType()
 * @method bool isSalable()
 */
class Mage_Bundle_Model_Selection extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('bundle/selection');
        parent::_construct();
    }

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    protected function _afterSave()
    {
        $storeId = Mage::registry('product')->getStoreId();
        if (!Mage::helper('catalog')->isPriceGlobal() && $storeId) {
            $this->setWebsiteId(Mage::app()->getStore($storeId)->getWebsiteId());
            $this->getResource()->saveSelectionPrice($this);

            if (!$this->getDefaultPriceScope()) {
                $this->unsSelectionPriceValue();
                $this->unsSelectionPriceType();
            }
        }
        return parent::_afterSave();
    }

    public function getDefaultPriceScope(): ?string
    {
        $value = $this->getData('default_price_scope');
        return $value === null ? null : (string) $value;
    }

    public function getIsDefault(): ?bool
    {
        $value = $this->getData('is_default');
        return $value === null ? null : (bool) $value;
    }

    public function setIsDefault(?bool $value): static
    {
        return $this->setData('is_default', $value);
    }

    public function getOptionId(): ?int
    {
        $value = $this->getData('option_id');
        return $value === null ? null : (int) $value;
    }

    public function setOptionId(?int $value): static
    {
        return $this->setData('option_id', $value);
    }

    public function getParentProductId(): ?int
    {
        $value = $this->getData('parent_product_id');
        return $value === null ? null : (int) $value;
    }

    public function setParentProductId(?int $value): static
    {
        return $this->setData('parent_product_id', $value);
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

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getSelectionCanChangeQty(): ?bool
    {
        $value = $this->getData('selection_can_change_qty');
        return $value === null ? null : (bool) $value;
    }

    public function setSelectionCanChangeQty(?bool $value): static
    {
        return $this->setData('selection_can_change_qty', $value);
    }

    public function getSelectionId(): ?int
    {
        $value = $this->getData('selection_id');
        return $value === null ? null : (int) $value;
    }

    public function getSelectionPriceType(): ?int
    {
        $value = $this->getData('selection_price_type');
        return $value === null ? null : (int) $value;
    }

    public function setSelectionPriceType(?int $value): static
    {
        return $this->setData('selection_price_type', $value);
    }

    public function getSelectionPriceValue(): ?float
    {
        $value = $this->getData('selection_price_value');
        return $value === null ? null : (float) $value;
    }

    public function setSelectionPriceValue(?float $value): static
    {
        return $this->setData('selection_price_value', $value);
    }

    public function getSelectionQty(): ?float
    {
        $value = $this->getData('selection_qty');
        return $value === null ? null : (float) $value;
    }

    public function setSelectionQty(?float $value): static
    {
        return $this->setData('selection_qty', $value);
    }

    public function getWebsiteId(): ?int
    {
        $value = $this->getData('website_id');
        return $value === null ? null : (int) $value;
    }

    public function setWebsiteId(?int $value): static
    {
        return $this->setData('website_id', $value);
    }

}
