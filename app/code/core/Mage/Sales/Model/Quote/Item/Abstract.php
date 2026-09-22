<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * Quote item abstract model
 *
 * Price attributes:
 *  - price - initial item price, declared during product association
 *  - original_price - product price before any calculations
 *  - calculation_price - prices for item totals calculation
 *  - custom_price - new price that can be declared by user and recalculated during calculation process
 *  - original_custom_price - original defined value of custom price without any conversion
 *
 * @method bool hasBaseCalculationPrice()
 * @method $this unsBasePriceInclTax()
 * @method $this unsBaseRowTotalInclTax()
 * @method bool hasCustomPrice()
 * @method $this unsHasConfigurationUnavailableError()
 * @method $this unsMessage()
 * @method bool hasOriginalCustomPrice()
 * @method $this unsPriceInclTax()
 * @method $this unsRowTotalInclTax()
 */
abstract class Mage_Sales_Model_Quote_Item_Abstract extends Mage_Core_Model_Abstract implements Mage_Catalog_Model_Product_Configuration_Item_Interface
{
    /**
     * Parent item for sub items for bundle product, configurable product, etc.
     *
     * @var Mage_Sales_Model_Quote_Item_Abstract|null
     */
    protected $_parentItem  = null;

    /**
     * Children items in bundle product, configurable product, etc.
     *
     * @var array
     */
    protected $_children    = [];

    /**
     * @var array
     */
    protected $_messages    = [];

    /**
     * @var array
     */
    protected $_optionsByCode;

    /**
     * Retrieve Quote instance
     *
     * @return Mage_Sales_Model_Quote
     */
    abstract public function getQuote();

    /**
     * Retrieve product model object associated with item
     *
     * @return Mage_Catalog_Model_Product
     */
    #[\Override]
    public function getProduct()
    {
        $product = $this->_getData('product');
        if ($product === null && $this->getProductId()) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId($this->getQuote()->getStoreId())
                ->load($this->getProductId());
            $this->setProduct($product);
        }

        /**
         * Reset product final price because it related to custom options
         */
        $product->setFinalPrice(null);
        if (is_array($this->_optionsByCode)) {
            $product->setCustomOptions($this->_optionsByCode);
        }
        return $product;
    }

    /**
     * Returns special download params (if needed) for custom option with type = 'file'
     * Needed to implement Mage_Catalog_Model_Product_Configuration_Item_Interface.
     * Return null, as quote item needs no additional configuration.
     *
     * @return null|\Maho\DataObject
     */
    #[\Override]
    public function getFileDownloadParams()
    {
        return null;
    }

    /**
     * Specify parent item id before saving data
     *
     * @return  $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        if ($this->getParentItem()) {
            $this->setParentItemId($this->getParentItem()->getId());
        }
        return $this;
    }

    /**
     * Set parent item
     *
     * @param  Mage_Sales_Model_Quote_Item $parentItem
     * @return $this
     */
    public function setParentItem($parentItem)
    {
        if ($parentItem) {
            $this->_parentItem = $parentItem;
            // Prevent duplication of children in those are already set
            if (!in_array($this, $parentItem->getChildren(), true)) {
                $parentItem->addChild($this);
            }
        }
        return $this;
    }

    /**
     * Get parent item
     *
     * @return Mage_Sales_Model_Quote_Item_Abstract|null
     */
    public function getParentItem()
    {
        return $this->_parentItem;
    }

    /**
     * Get child items
     *
     * @return Mage_Sales_Model_Quote_Item_Abstract[]
     */
    public function getChildren()
    {
        return $this->_children;
    }

    /**
     * Add child item
     *
     * @param  Mage_Sales_Model_Quote_Item_Abstract $child
     * @return $this
     */
    public function addChild($child)
    {
        $this->setHasChildren();
        $this->_children[] = $child;
        return $this;
    }

    /**
     * Adds message(s) for quote item. Duplicated messages are not added.
     *
     * @param  array|string $messages
     * @return $this
     */
    public function setMessage($messages)
    {
        $messagesExists = $this->getMessage(false);
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        foreach ($messages as $message) {
            if (!in_array($message, $messagesExists)) {
                $this->addMessage($message);
            }
        }
        return $this;
    }

    /**
     * Add message of quote item to array of messages
     *
     * @param   string $message
     * @return  $this
     */
    public function addMessage($message)
    {
        $this->_messages[] = $message;
        return $this;
    }

    /**
     * Get messages array of quote item
     *
     * @param   bool $string flag for converting messages to string
     * @return  array|string
     */
    public function getMessage($string = true)
    {
        if ($string) {
            return implode("\n", $this->_messages);
        }
        return $this->_messages;
    }

    /**
     * Removes message by text
     *
     * @param string $text
     * @return $this
     */
    public function removeMessageByText($text)
    {
        foreach ($this->_messages as $key => $message) {
            if ($message == $text) {
                unset($this->_messages[$key]);
            }
        }
        return $this;
    }

    /**
     * Clears all messages
     *
     * @return $this
     */
    public function clearMessage()
    {
        $this->unsMessage(); // For older compatibility, when we kept message inside data array
        $this->_messages = [];
        return $this;
    }

    /**
     * Retrieve store model object
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        return $this->getQuote()->getStore();
    }

    /**
     * Checking item data
     *
     * @return $this
     */
    public function checkData()
    {
        $this->setHasError(false);
        $this->clearMessage();

        $qty = $this->_getData('qty');

        try {
            $this->setQty($qty);
        } catch (Mage_Core_Exception $e) {
            $this->setHasError();
            $this->setMessage($e->getMessage());
        } catch (Exception $e) {
            $this->setHasError();
            $this->setMessage(Mage::helper('sales')->__('Item qty declaration error.'));
        }

        try {
            $this->getProduct()->getTypeInstance(true)->checkProductBuyState($this->getProduct());
        } catch (Mage_Core_Exception $e) {
            $this->setHasError()
                ->setMessage($e->getMessage());
            $this->getQuote()->setHasError(true)
                ->addMessage(Mage::helper('sales')->__('Some of the products below do not have all the required options.'));
        } catch (Exception) {
            $this->setHasError()
                ->setMessage(Mage::helper('sales')->__('Item options declaration error.'));
            $this->getQuote()->setHasError(true)
                ->addMessage(Mage::helper('sales')->__('Items options declaration error.'));
        }

        if ($this->getProduct()->getHasError()) {
            $this->setHasError()
                ->setMessage(Mage::helper('sales')->__('Some of the selected options are not currently available.'));
            $this->getQuote()->setHasError(true)
                ->addMessage($this->getProduct()->getMessage(), 'options');
        }

        if ($this->getHasConfigurationUnavailableError()) {
            $this->setHasError()
                ->setMessage(Mage::helper('sales')->__('Selected option(s) or their combination is not currently available.'));
            $this->getQuote()->setHasError(true)
                ->addMessage(Mage::helper('sales')->__('Some item options or their combination are not currently available.'), 'unavailable-configuration');
            $this->unsHasConfigurationUnavailableError();
        }

        return $this;
    }

    public function getQty(): ?float
    {
        $value = $this->getData('qty');
        return $value === null ? null : (float) $value;
    }

    /**
     * Get total item quantity (include parent item relation)
     *
     * @return  int|float
     */
    public function getTotalQty()
    {
        if ($this->getParentItem()) {
            return $this->getQty() * $this->getParentItem()->getQty();
        }
        return $this->getQty();
    }

    /**
     * Calculate item row total price
     *
     * @return $this
     */
    public function calcRowTotal()
    {
        $qty        = $this->getTotalQty();
        // Round unit price before multiplying to prevent losing 1 cent on subtotal
        $total      = $this->getStore()->roundPrice($this->getCalculationPriceOriginal()) * $qty;
        $baseTotal  = $this->getStore()->roundPrice($this->getBaseCalculationPriceOriginal()) * $qty;

        $this->setRowTotal($this->getStore()->roundPrice($total));
        $this->setBaseRowTotal($this->getStore()->roundPrice($baseTotal));
        return $this;
    }

    /**
     * Get item price used for quote calculation process.
     * This method get custom price (if it is defined) or original product final price
     *
     * @return float
     */
    public function getCalculationPrice()
    {
        $price = $this->_getData('calculation_price');
        if (is_null($price)) {
            if ($this->hasCustomPrice()) {
                $price = $this->getCustomPrice();
            } else {
                $price = $this->getConvertedPrice();
            }
            $this->setData('calculation_price', $price);
        }
        return $price;
    }

    /**
     * Get item price used for quote calculation process.
     * This method get original custom price applied before tax calculation
     *
     * @return float
     */
    public function getCalculationPriceOriginal()
    {
        $price = $this->_getData('calculation_price');
        if (is_null($price)) {
            if ($this->hasOriginalCustomPrice()) {
                $price = $this->getOriginalCustomPrice();
            } else {
                $price = $this->getConvertedPrice();
            }
            $this->setData('calculation_price', $price);
        }
        return $price;
    }

    /**
     * Get calculation price used for quote calculation in base currency.
     *
     * @return float
     */
    public function getBaseCalculationPrice()
    {
        if (!$this->hasBaseCalculationPrice()) {
            if ($this->hasCustomPrice()) {
                $price = (float) $this->getCustomPrice();
                if ($price) {
                    $rate = $this->getStore()->convertPrice($price) / $price;
                    $price = $price / $rate;
                }
            } else {
                $price = $this->getPrice();
            }
            $this->setBaseCalculationPrice($price);
        }
        return $this->_getData('base_calculation_price');
    }

    /**
     * Get original calculation price used for quote calculation in base currency.
     *
     * @return float
     */
    public function getBaseCalculationPriceOriginal()
    {
        if (!$this->hasBaseCalculationPrice()) {
            if ($this->hasOriginalCustomPrice()) {
                $price = (float) $this->getOriginalCustomPrice();
                if ($price) {
                    $rate = $this->getStore()->convertPrice($price) / $price;
                    $price = $price / $rate;
                }
            } else {
                $price = $this->getPrice();
            }
            $this->setBaseCalculationPrice($price);
        }
        return $this->_getData('base_calculation_price');
    }

    /**
     * Get whether the item is nominal
     *
     * @return bool
     */
    public function isNominal()
    {
        if (!$this->hasData('is_nominal')) {
            $this->setData('is_nominal', $this->getProduct() ? $this->getProduct()->getIsRecurring() == '1' : false);
        }
        return $this->_getData('is_nominal');
    }

    /**
     * Data getter for 'is_nominal'
     * Used for converting item to order item
     *
     * @return int
     */
    public function getIsNominal()
    {
        return (int) $this->isNominal();
    }

    /**
     * Get original price (retrieved from product) for item.
     * Original price value is in quote selected currency
     *
     * @return float
     */
    public function getOriginalPrice()
    {
        $price = $this->_getData('original_price');
        if (is_null($price)) {
            $price = $this->getStore()->convertPrice($this->getBaseOriginalPrice());
            $this->setData('original_price', $price);
        }
        return $price;
    }

    /**
     * Set original price to item (calculation price will be refreshed too)
     *
     * @param   float $price
     * @return  $this
     */
    public function setOriginalPrice($price)
    {
        return $this->setData('original_price', $price);
    }

    public function getBaseOriginalPrice(): ?float
    {
        $value = $this->getData('base_original_price');
        return $value === null ? null : (float) $value;
    }

    /**
     * Specify custom item price (used in case when we have applied not product price to item)
     *
     * @param   float $value
     * @return  $this
     */
    public function setCustomPrice($value)
    {
        $this->setCalculationPrice($value);
        $this->setBaseCalculationPrice(null);
        return $this->setData('custom_price', $value);
    }

    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float) $value;
    }

    /**
     * Specify item price (base calculation price and converted price will be refreshed too)
     *
     * @param   float $value
     * @return  $this
     */
    public function setPrice($value)
    {
        $this->setBaseCalculationPrice(null);
        $this->setConvertedPrice(null);
        return $this->setData('price', $value);
    }

    /**
     * Get item price converted to quote currency
     * @return float
     */
    public function getConvertedPrice()
    {
        $price = $this->_getData('converted_price');
        if (is_null($price)) {
            $price = $this->getStore()->convertPrice($this->getPrice());
            $this->setData('converted_price', $price);
        }
        return $price;
    }

    /**
     * Set new value for converted price
     * @param float|null $value
     * @return $this
     */
    public function setConvertedPrice($value)
    {
        $this->setCalculationPrice(null);
        $this->setData('converted_price', $value);
        return $this;
    }

    /**
     * Clone quote item
     */
    public function __clone()
    {
        $this->setId(null);
        $this->_parentItem  = null;
        $this->_children    = [];
        $this->_messages    = [];
    }

    /**
     * Checking if there children calculated or parent item
     * when we have parent quote item and its children
     *
     * @return bool
     */
    public function isChildrenCalculated()
    {
        if ($this->getParentItem()) {
            $calculate = $this->getParentItem()->getProduct()->getPriceType();
        } else {
            $calculate = $this->getProduct()->getPriceType();
        }

        if (($calculate !== null) && (int) $calculate === Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_CHILD) {
            return true;
        }
        return false;
    }

    /**
     * Checking can we ship product separately (each child separately)
     * or each parent product item can be shipped only like one item
     *
     * @return bool
     */
    public function isShipSeparately()
    {
        if ($this->getParentItem()) {
            $shipmentType = $this->getParentItem()->getProduct()->getShipmentType();
        } else {
            $shipmentType = $this->getProduct()->getShipmentType();
        }

        if (($shipmentType !== null)
            && (int) $shipmentType === Mage_Catalog_Model_Product_Type_Abstract::SHIPMENT_SEPARATELY
        ) {
            return true;
        }
        return false;
    }

    /**
     * Calculate item tax amount
     *
     * @return  $this
     */
    #[\Deprecated(message: 'logic moved to tax totals calculation model')]
    public function calcTaxAmount()
    {
        $store = $this->getStore();

        if (!Mage::helper('tax')->priceIncludesTax($store)) {
            if (Mage::helper('tax')->applyTaxAfterDiscount($store)) {
                $rowTotal       = $this->getRowTotalWithDiscount();
                $rowBaseTotal   = $this->getBaseRowTotalWithDiscount();
            } else {
                $rowTotal       = $this->getRowTotal();
                $rowBaseTotal   = $this->getBaseRowTotal();
            }

            $taxPercent = $this->getTaxPercent() / 100;

            $this->setTaxAmount($store->roundPrice($rowTotal * $taxPercent));
            $this->setBaseTaxAmount($store->roundPrice($rowBaseTotal * $taxPercent));

            $rowTotal       = $this->getRowTotal();
            $rowBaseTotal   = $this->getBaseRowTotal();
            $this->setTaxBeforeDiscount($store->roundPrice($rowTotal * $taxPercent));
            $this->setBaseTaxBeforeDiscount($store->roundPrice($rowBaseTotal * $taxPercent));
        } else {
            if (Mage::helper('tax')->applyTaxAfterDiscount($store)) {
                $totalBaseTax = $this->getBaseTaxAmount();
                $totalTax = $this->getTaxAmount();

                if ($totalTax && $totalBaseTax) {
                    $totalTax -= $this->getDiscountAmount() * ($this->getTaxPercent() / 100);
                    $totalBaseTax -= $this->getBaseDiscountAmount() * ($this->getTaxPercent() / 100);

                    $this->setBaseTaxAmount($store->roundPrice($totalBaseTax));
                    $this->setTaxAmount($store->roundPrice($totalTax));
                }
            }
        }

        if (Mage::helper('tax')->discountTax($store) && !Mage::helper('tax')->applyTaxAfterDiscount($store)) {
            if ($this->getDiscountPercent()) {
                $baseTaxAmount =  $this->getBaseTaxBeforeDiscount();
                $taxAmount = $this->getTaxBeforeDiscount();

                $baseDiscountDisposition = $baseTaxAmount / 100 * $this->getDiscountPercent();
                $discountDisposition = $taxAmount / 100 * $this->getDiscountPercent();

                $this->setDiscountAmount($this->getDiscountAmount() + $discountDisposition);
                $this->setBaseDiscountAmount($this->getBaseDiscountAmount() + $baseDiscountDisposition);
            }
        }

        return $this;
    }

    public function getTaxAmount(): ?float
    {
        $value = $this->getData('tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function getBaseTaxAmount(): ?float
    {
        $value = $this->getData('base_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function getAddress(): ?Mage_Sales_Model_Quote_Address
    {
        return $this->getData('address');
    }

    public function setAddress(?Mage_Sales_Model_Quote_Address $value): static
    {
        return $this->setData('address', $value);
    }

    public function setAppliedRuleIds(?string $value): static
    {
        return $this->setData('applied_rule_ids', $value);
    }

    public function setBaseCalculationPrice(?float $value): static
    {
        return $this->setData('base_calculation_price', $value);
    }

    public function setBaseCustomPrice(?float $value): static
    {
        return $this->setData('base_custom_price', $value);
    }

    public function getBaseDiscountAmount(): ?float
    {
        $value = $this->getData('base_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseDiscountAmount(?float $value): static
    {
        return $this->setData('base_discount_amount', $value);
    }

    public function getBaseDiscountCalculationPrice(): ?float
    {
        $value = $this->getData('base_discount_calculation_price');
        return $value === null ? null : (float) $value;
    }

    public function setBaseDiscountCalculationPrice(?float $value): static
    {
        return $this->setData('base_discount_calculation_price', $value);
    }

    public function setBaseExtraRowTaxableAmount(?float $value): static
    {
        return $this->setData('base_extra_row_taxable_amount', $value);
    }

    public function setBaseExtraTaxableAmount(?float $value): static
    {
        return $this->setData('base_extra_taxable_amount', $value);
    }

    public function setBaseHiddenTaxAmount(?float $value): static
    {
        return $this->setData('base_hidden_tax_amount', $value);
    }

    public function getBaseOriginalDiscountAmount(): ?float
    {
        $value = $this->getData('base_original_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseOriginalDiscountAmount(?float $value): static
    {
        return $this->setData('base_original_discount_amount', $value);
    }

    public function setBaseOriginalPrice(?float $value): static
    {
        return $this->setData('base_original_price', $value);
    }

    public function setBasePriceInclTax(?float $value): static
    {
        return $this->setData('base_price_incl_tax', $value);
    }

    public function getBaseRowTax(): ?float
    {
        $value = $this->getData('base_row_tax');
        return $value === null ? null : (float) $value;
    }

    public function getBaseRowTotal(): ?float
    {
        $value = $this->getData('base_row_total');
        return $value === null ? null : (float) $value;
    }

    public function setBaseRowTotal(?float $value): static
    {
        return $this->setData('base_row_total', $value);
    }

    public function getBaseRowTotalInclTax(): ?float
    {
        $value = $this->getData('base_row_total_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseRowTotalInclTax(?float $value): static
    {
        return $this->setData('base_row_total_incl_tax', $value);
    }

    public function getBaseRowTotalWithDiscount(): ?float
    {
        $value = $this->getData('base_row_total_with_discount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseRowTotalWithDiscount(?float $value): static
    {
        return $this->setData('base_row_total_with_discount', $value);
    }

    public function getBaseShippingAmount(): ?float
    {
        $value = $this->getData('base_shipping_amount');
        return $value === null ? null : (float) $value;
    }

    public function getBaseTaxableAmount(): ?float
    {
        $value = $this->getData('base_taxable_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxableAmount(?float $value): static
    {
        return $this->setData('base_taxable_amount', $value);
    }

    public function setBaseTaxAmount(?float $value): static
    {
        return $this->setData('base_tax_amount', $value);
    }

    public function getBaseTaxBeforeDiscount(): ?float
    {
        $value = $this->getData('base_tax_before_discount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxBeforeDiscount(?float $value): static
    {
        return $this->setData('base_tax_before_discount', $value);
    }

    public function setBaseTaxCalcPrice(?float $value): static
    {
        return $this->setData('base_tax_calc_price', $value);
    }

    public function setBaseTaxCalcRowTotal(?float $value): static
    {
        return $this->setData('base_tax_calc_row_total', $value);
    }

    public function setBasePrice(?float $value): static
    {
        return $this->setData('base_price', $value);
    }

    public function setBaseRowTax(?float $value): static
    {
        return $this->setData('base_row_tax', $value);
    }

    public function setBaseShippingAmount(?float $value): static
    {
        return $this->setData('base_shipping_amount', $value);
    }

    public function getBaseWeeeDiscount(): ?float
    {
        $value = $this->getData('base_weee_discount');
        return $value === null ? null : (float) $value;
    }

    public function getBaseWeeeTaxAppliedAmount(): ?float
    {
        $value = $this->getData('base_weee_tax_applied_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseWeeeTaxAppliedAmount(?float $value): static
    {
        return $this->setData('base_weee_tax_applied_amount', $value);
    }

    public function getBaseWeeeTaxAppliedRowAmount(): ?float
    {
        $value = $this->getData('base_weee_tax_applied_row_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseWeeeTaxAppliedRowAmount(?float $value): static
    {
        return $this->setData('base_weee_tax_applied_row_amount', $value);
    }

    public function getBaseWeeeTaxDisposition(): ?float
    {
        $value = $this->getData('base_weee_tax_disposition');
        return $value === null ? null : (float) $value;
    }

    public function setBaseWeeeTaxDisposition(?float $value): static
    {
        return $this->setData('base_weee_tax_disposition', $value);
    }

    public function getBaseWeeeTaxRowDisposition(): ?float
    {
        $value = $this->getData('base_weee_tax_row_disposition');
        return $value === null ? null : (float) $value;
    }

    public function setBaseWeeeTaxRowDisposition(?float $value): static
    {
        return $this->setData('base_weee_tax_row_disposition', $value);
    }

    public function setCalculationPrice(?float $value): static
    {
        return $this->setData('calculation_price', $value);
    }

    public function getCustomPrice(): ?float
    {
        $value = $this->getData('custom_price');
        return $value === null ? null : (float) $value;
    }

    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountAmount(?float $value): static
    {
        return $this->setData('discount_amount', $value);
    }

    public function getDiscountCalculationPrice(): ?float
    {
        $value = $this->getData('discount_calculation_price');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountCalculationPrice(?float $value): static
    {
        return $this->setData('discount_calculation_price', $value);
    }

    public function getDiscountPercent(): ?float
    {
        $value = $this->getData('discount_percent');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountPercent(?float $value): static
    {
        return $this->setData('discount_percent', $value);
    }

    public function getDiscountTaxCompensation(): ?float
    {
        $value = $this->getData('discount_tax_compensation');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountTaxCompensation(?float $value): static
    {
        return $this->setData('discount_tax_compensation', $value);
    }

    public function setExtraRowTaxableAmount(?float $value): static
    {
        return $this->setData('extra_row_taxable_amount', $value);
    }

    public function setExtraTaxableAmount(?float $value): static
    {
        return $this->setData('extra_taxable_amount', $value);
    }

    public function getFreeShipping(): bool|float
    {
        // true marks the whole item free, a number is a free quantity
        $value = $this->getData('free_shipping');
        if ($value === null || is_bool($value)) {
            return (bool) $value;
        }
        return (float) $value;
    }

    public function setFreeShipping(bool|float $value): static
    {
        return $this->setData('free_shipping', $value);
    }

    public function getHasChildren(): ?bool
    {
        $value = $this->getData('has_children');
        return $value === null ? null : (bool) $value;
    }

    public function setHasChildren(?bool $value = true): static
    {
        return $this->setData('has_children', $value);
    }

    public function setHasError(?bool $value = true): static
    {
        return $this->setData('has_error', $value);
    }

    public function getHasConfigurationUnavailableError(): ?bool
    {
        $value = $this->getData('has_configuration_unavailable_error');
        return $value === null ? null : (bool) $value;
    }

    public function setHiddenTaxAmount(?float $value): static
    {
        return $this->setData('hidden_tax_amount', $value);
    }

    public function getIsPriceInclTax(): ?bool
    {
        $value = $this->getData('is_price_incl_tax');
        return $value === null ? null : (bool) $value;
    }

    public function setIsPriceInclTax(?bool $value = true): static
    {
        return $this->setData('is_price_incl_tax', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function getNoDiscount(): ?bool
    {
        $value = $this->getData('no_discount');
        return $value === null ? null : (bool) $value;
    }

    public function getNominalRowTotal(): ?float
    {
        $value = $this->getData('nominal_row_total');
        return $value === null ? null : (float) $value;
    }

    public function getNominalTotalDetails(): ?array
    {
        return $this->getData('nominal_total_details');
    }

    public function getOriginalCustomPrice(): ?float
    {
        $value = $this->getData('original_custom_price');
        return $value === null ? null : (float) $value;
    }

    public function getOriginalDiscountAmount(): ?float
    {
        $value = $this->getData('original_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setOriginalDiscountAmount(?float $value): static
    {
        return $this->setData('original_discount_amount', $value);
    }

    public function getParentItemId(): ?int
    {
        $value = $this->getData('parent_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setParentItemId(?int $value): static
    {
        return $this->setData('parent_item_id', $value);
    }

    public function setPriceInclTax(?float $value): static
    {
        return $this->setData('price_incl_tax', $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProduct(?Mage_Catalog_Model_Product $value): static
    {
        return $this->setData('product', $value);
    }

    public function getProductOrderOptions(): ?array
    {
        return $this->getData('product_order_options');
    }

    public function getProductType(): ?string
    {
        $value = $this->getData('product_type');
        return $value === null ? null : (string) $value;
    }

    public function setQty(?float $value): static
    {
        return $this->setData('qty', $value);
    }

    public function getRowTax(): ?float
    {
        $value = $this->getData('row_tax');
        return $value === null ? null : (float) $value;
    }

    public function setRowTax(?float $value): static
    {
        return $this->setData('row_tax', $value);
    }

    public function getRowTotal(): ?float
    {
        $value = $this->getData('row_total');
        return $value === null ? null : (float) $value;
    }

    public function setRowTotal(?float $value): static
    {
        return $this->setData('row_total', $value);
    }

    public function setRowTotalExcTax(?float $value): static
    {
        return $this->setData('row_total_exc_tax', $value);
    }

    public function getRowTotalInclTax(): ?float
    {
        $value = $this->getData('row_total_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setRowTotalInclTax(?float $value): static
    {
        return $this->setData('row_total_incl_tax', $value);
    }

    public function getRowTotalWithDiscount(): ?float
    {
        $value = $this->getData('row_total_with_discount');
        return $value === null ? null : (float) $value;
    }

    public function setRowTotalWithDiscount(?float $value): static
    {
        return $this->setData('row_total_with_discount', $value);
    }

    public function getRowWeight(): ?float
    {
        $value = $this->getData('row_weight');
        return $value === null ? null : (float) $value;
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function getTaxableAmount(): ?float
    {
        $value = $this->getData('taxable_amount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxableAmount(?float $value): static
    {
        return $this->setData('taxable_amount', $value);
    }

    public function getTaxBeforeDiscount(): ?float
    {
        $value = $this->getData('tax_before_discount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxCalcPrice(?float $value): static
    {
        return $this->setData('tax_calc_price', $value);
    }

    public function setTaxCalcRowTotal(?float $value): static
    {
        return $this->setData('tax_calc_row_total', $value);
    }

    public function getTaxPercent(): ?float
    {
        $value = $this->getData('tax_percent');
        return $value === null ? null : (float) $value;
    }

    public function setTaxRates(?array $value): static
    {
        return $this->setData('tax_rates', $value);
    }

    public function setTaxAmount(?float $value): static
    {
        return $this->setData('tax_amount', $value);
    }

    public function setTaxBeforeDiscount(?float $value): static
    {
        return $this->setData('tax_before_discount', $value);
    }

    public function setTaxPercent(?float $value): static
    {
        return $this->setData('tax_percent', $value);
    }

    public function getWeeeDiscount(): ?float
    {
        $value = $this->getData('weee_discount');
        return $value === null ? null : (float) $value;
    }

    public function getWeeeTaxApplied(): ?string
    {
        $value = $this->getData('weee_tax_applied');
        return $value === null ? null : (string) $value;
    }

    public function setWeeeTaxApplied(?string $value): static
    {
        return $this->setData('weee_tax_applied', $value);
    }

    public function getWeeeTaxAppliedAmount(): ?float
    {
        $value = $this->getData('weee_tax_applied_amount');
        return $value === null ? null : (float) $value;
    }

    public function setWeeeTaxAppliedAmount(?float $value): static
    {
        return $this->setData('weee_tax_applied_amount', $value);
    }

    public function getWeeeTaxAppliedRowAmount(): ?float
    {
        $value = $this->getData('weee_tax_applied_row_amount');
        return $value === null ? null : (float) $value;
    }

    public function setWeeeTaxAppliedRowAmount(?float $value): static
    {
        return $this->setData('weee_tax_applied_row_amount', $value);
    }

    public function getWeeeTaxDisposition(): ?float
    {
        $value = $this->getData('weee_tax_disposition');
        return $value === null ? null : (float) $value;
    }

    public function setWeeeTaxDisposition(?float $value): static
    {
        return $this->setData('weee_tax_disposition', $value);
    }

    public function getWeeeTaxRowDisposition(): ?float
    {
        $value = $this->getData('weee_tax_row_disposition');
        return $value === null ? null : (float) $value;
    }

    public function setWeeeTaxRowDisposition(?float $value): static
    {
        return $this->setData('weee_tax_row_disposition', $value);
    }
}
