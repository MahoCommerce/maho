<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Quote_Address_Item _getResource()
 * @method Mage_Sales_Model_Resource_Quote_Address_Item getResource()
 * @method Mage_Sales_Model_Resource_Quote_Address_Item_Collection getCollection()
 *
 * @method bool hasQty()
 */
class Mage_Sales_Model_Quote_Address_Item extends Mage_Sales_Model_Quote_Item_Abstract
{
    /**
     * Quote address model object
     *
     * @var Mage_Sales_Model_Quote_Address
     */
    protected $_address;
    protected $_quote;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_address_item');
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        if ($this->getAddress()) {
            $this->setQuoteAddressId($this->getAddress()->getId());
        }
        return $this;
    }

    /**
     * Declare address model
     */
    #[\Override]
    public function setAddress(?Mage_Sales_Model_Quote_Address $address): static
    {
        $this->_address = $address;
        $this->_quote   = $address?->getQuote();
        return $this;
    }

    /**
     * Retrieve address model
     */
    #[\Override]
    public function getAddress(): ?Mage_Sales_Model_Quote_Address
    {
        return $this->_address;
    }

    /**
     * Retrieve quote model instance
     *
     * @return Mage_Sales_Model_Quote
     */
    #[\Override]
    public function getQuote()
    {
        return $this->_quote;
    }

    /**
     * Import item to quote
     *
     * @return $this
     */
    public function importQuoteItem(Mage_Sales_Model_Quote_Item $quoteItem)
    {
        $this->_quote = $quoteItem->getQuote();
        $this->setQuoteItem($quoteItem)
            ->setQuoteItemId($quoteItem->getId())
            ->setProductId($quoteItem->getProductId())
            ->setProduct($quoteItem->getProduct())
            ->setSku($quoteItem->getSku())
            ->setName($quoteItem->getName())
            ->setDescription($quoteItem->getDescription())
            ->setWeight($quoteItem->getWeight())
            ->setPrice($quoteItem->getPrice())
            ->setIsQtyDecimal($quoteItem->getIsQtyDecimal())
            ->setCost($quoteItem->getCost());

        if (!$this->hasQty()) {
            $this->setQty($quoteItem->getQty());
        }
        $this->setQuoteItemImported();
        return $this;
    }

    /**
     * @param string $code
     * @return Mage_Catalog_Model_Product_Configuration_Item_Option_Interface|null
     */
    #[\Override]
    public function getOptionByCode($code)
    {
        if ($this->getQuoteItem()) {
            return $this->getQuoteItem()->getOptionByCode($code);
        }
        return null;
    }

    public function getAdditionalData(): ?string
    {
        $value = $this->getData('additional_data');
        return $value === null ? null : (string) $value;
    }

    public function setAdditionalData(?string $value): static
    {
        return $this->setData('additional_data', $value);
    }

    public function getAppliedRuleIds(): ?string
    {
        $value = $this->getData('applied_rule_ids');
        return $value === null ? null : (string) $value;
    }

    public function getBaseCost(): ?float
    {
        $value = $this->getData('base_cost');
        return $value === null ? null : (float) $value;
    }

    public function setBaseCost(?float $value): static
    {
        return $this->setData('base_cost', $value);
    }

    public function getBaseDiscountTaxCompensation(): ?float
    {
        $value = $this->getData('base_discount_tax_compensation');
        return $value === null ? null : (float) $value;
    }

    public function getBaseHiddenTaxAmount(): ?float
    {
        $value = $this->getData('base_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function getBasePrice(): ?float
    {
        $value = $this->getData('base_price');
        return $value === null ? null : (float) $value;
    }

    public function setCustomerAddressId(?int $value): static
    {
        return $this->setData('customer_address_id', $value);
    }

    public function getQuoteAddressId(): ?int
    {
        $value = $this->getData('quote_address_id');
        return $value === null ? null : (int) $value;
    }

    public function setQuoteAddressId(?int $value): static
    {
        return $this->setData('quote_address_id', $value);
    }

    public function getQuoteItemId(): ?int
    {
        $value = $this->getData('quote_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setQuoteItemId(?int $value): static
    {
        return $this->setData('quote_item_id', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    public function getWeight(): ?float
    {
        $value = $this->getData('weight');
        return $value === null ? null : (float) $value;
    }

    public function setWeight(?float $value): static
    {
        return $this->setData('weight', $value);
    }

    public function setRowWeight(?float $value): static
    {
        return $this->setData('row_weight', $value);
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getSuperProductId(): ?int
    {
        $value = $this->getData('super_product_id');
        return $value === null ? null : (int) $value;
    }

    public function setSuperProductId(?int $value): static
    {
        return $this->setData('super_product_id', $value);
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

    public function getSku(): ?string
    {
        $value = $this->getData('sku');
        return $value === null ? null : (string) $value;
    }

    public function setSku(?string $value): static
    {
        return $this->setData('sku', $value);
    }

    public function getImage(): ?string
    {
        $value = $this->getData('image');
        return $value === null ? null : (string) $value;
    }

    public function setImage(?string $value): static
    {
        return $this->setData('image', $value);
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
    }

    public function getIsQtyDecimal(): ?bool
    {
        $value = $this->getData('is_qty_decimal');
        return $value === null ? null : (bool) $value;
    }

    public function setIsQtyDecimal(?bool $value = true): static
    {
        return $this->setData('is_qty_decimal', $value);
    }

    public function setNoDiscount(?int $value): static
    {
        return $this->setData('no_discount', $value);
    }

    public function getPriceInclTax(): ?float
    {
        $value = $this->getData('price_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function getBasePriceInclTax(): ?float
    {
        $value = $this->getData('base_price_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function getGiftMessageId(): ?int
    {
        $value = $this->getData('gift_message_id');
        return $value === null ? null : (int) $value;
    }

    public function setGiftMessageId(?int $value): static
    {
        return $this->setData('gift_message_id', $value);
    }

    public function getHiddenTaxAmount(): ?float
    {
        $value = $this->getData('hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setCost(?float $value): static
    {
        return $this->setData('cost', $value);
    }

    public function setShippingAmount(?float $value): static
    {
        return $this->setData('shipping_amount', $value);
    }

    public function getQuoteItem(): ?Mage_Sales_Model_Quote_Item
    {
        return $this->getData('quote_item');
    }

    public function setQuoteItem(?Mage_Sales_Model_Quote_Item $value): static
    {
        return $this->setData('quote_item', $value);
    }

    public function setQuoteItemImported(?bool $value = true): static
    {
        return $this->setData('quote_item_imported', $value);
    }

    public function setProductType(?string $value): static
    {
        return $this->setData('product_type', $value);
    }

    public function getCustomerAddressId(): ?int
    {
        $value = $this->getData('customer_address_id');
        return $value === null ? null : (int) $value;
    }
}
