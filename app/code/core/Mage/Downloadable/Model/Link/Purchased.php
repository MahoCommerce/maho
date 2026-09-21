<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

/**
 * Downloadable links purchased model
 *
 * @package    Mage_Downloadable
 *
 * @method Mage_Downloadable_Model_Resource_Link_Purchased _getResource()
 * @method Mage_Downloadable_Model_Resource_Link_Purchased getResource()
 */
class Mage_Downloadable_Model_Link_Purchased extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('downloadable/link_purchased');
        parent::_construct();
    }

    /**
     * Check order id
     */
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getOrderId() == null) {
            throw new Exception(
                Mage::helper('downloadable')->__('Order id cannot be null'),
            );
        }
        $this->setUpdatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        return parent::_beforeSave();
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getLinkSectionTitle(): ?string
    {
        $value = $this->getData('link_section_title');
        return $value === null ? null : (string) $value;
    }

    public function setLinkSectionTitle(?string $value): static
    {
        return $this->setData('link_section_title', $value);
    }

    public function getOrderId(): ?int
    {
        $value = $this->getData('order_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderId(?int $value): static
    {
        return $this->setData('order_id', $value);
    }

    public function getOrderIncrementId(): ?string
    {
        $value = $this->getData('order_increment_id');
        return $value === null ? null : (string) $value;
    }

    public function setOrderIncrementId(?string $value): static
    {
        return $this->setData('order_increment_id', $value);
    }

    public function getOrderItemId(): ?int
    {
        $value = $this->getData('order_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderItemId(?int $value): static
    {
        return $this->setData('order_item_id', $value);
    }

    public function getProductName(): ?string
    {
        $value = $this->getData('product_name');
        return $value === null ? null : (string) $value;
    }

    public function setProductName(?string $value): static
    {
        return $this->setData('product_name', $value);
    }

    public function getProductSku(): ?string
    {
        $value = $this->getData('product_sku');
        return $value === null ? null : (string) $value;
    }

    public function setProductSku(?string $value): static
    {
        return $this->setData('product_sku', $value);
    }

    public function setPurchasedItems(?Mage_Downloadable_Model_Resource_Link_Purchased_Item_Collection $value): static
    {
        return $this->setData('purchased_items', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
