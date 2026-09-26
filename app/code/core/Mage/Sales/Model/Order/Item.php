<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * @method Mage_Sales_Model_Resource_Order_Item _getResource()
 * @method Mage_Sales_Model_Resource_Order_Item getResource()
 * @method Mage_Sales_Model_Resource_Order_Item_Collection getCollection()
 */
class Mage_Sales_Model_Order_Item extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING        = 1; // No items shipped, invoiced, canceled, refunded nor backordered
    public const STATUS_SHIPPED        = 2; // When qty ordered - [qty canceled + qty returned] = qty shipped
    public const STATUS_INVOICED       = 9; // When qty ordered - [qty canceled + qty returned] = qty invoiced
    public const STATUS_BACKORDERED    = 3; // When qty ordered - [qty canceled + qty returned] = qty backordered
    public const STATUS_CANCELED       = 5; // When qty ordered = qty canceled
    public const STATUS_PARTIAL        = 6; // If [qty shipped or(max of two) qty invoiced + qty canceled + qty returned]
    // < qty ordered
    public const STATUS_MIXED          = 7; // All other combinations
    public const STATUS_REFUNDED       = 8; // When qty ordered = qty refunded

    public const STATUS_RETURNED       = 4; // When qty ordered = qty returned // not used at the moment

    #[\Override]
    protected $_eventPrefix = 'sales_order_item';
    #[\Override]
    protected $_eventObject = 'item';

    protected static $_statuses = null;

    /**
     * Order instance
     *
     * @var Mage_Sales_Model_Order|null
     */
    protected $_order       = null;

    protected $_parentItem  = null;
    protected $_children    = [];

    /**
     * Init resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_item');
    }

    /**
     * Init mapping array of short fields to
     * its full names
     *
     * @return \Maho\DataObject
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        // pre 1.6 fields names, old => new
        $this->_oldFieldsMap = [
            'base_weee_tax_applied_row_amount' => 'base_weee_tax_applied_row_amnt',
        ];
        return $this;
    }

    /**
     * Prepare data before save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        if (!$this->getOrderId() && $this->getOrder()) {
            $this->setOrderId($this->getOrder()->getId());
        }
        if ($this->getParentItem()) {
            $this->setParentItemId($this->getParentItem()->getId());
        }
        return $this;
    }

    /**
     * Set parent item
     *
     * @param   Mage_Sales_Model_Order_Item $item
     * @return  $this
     */
    public function setParentItem($item)
    {
        if ($item) {
            $this->_parentItem = $item;
            $item->setHasChildren();
            $item->addChildItem($this);
        }
        return $this;
    }

    /**
     * Get parent item
     *
     * @return $this|null
     */
    public function getParentItem()
    {
        return $this->_parentItem;
    }

    /**
     * Check item invoice availability
     *
     * @return bool
     */
    public function canInvoice()
    {
        return $this->getQtyToInvoice() > 0;
    }

    /**
     * Check item ship availability
     *
     * @return bool
     */
    public function canShip()
    {
        return $this->getQtyToShip() > 0;
    }

    /**
     * Check item refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
        return $this->getQtyToRefund() > 0;
    }

    /**
     * Retrieve item qty available for ship
     *
     * @return float|integer
     */
    public function getQtyToShip()
    {
        if ($this->isDummy(true)) {
            return 0;
        }

        return $this->getSimpleQtyToShip();
    }

    /**
     * Retrieve item qty available for ship
     *
     * @return float|integer
     */
    public function getSimpleQtyToShip()
    {
        $qty = $this->getQtyOrdered()
            - $this->getQtyShipped()
            - $this->getQtyRefunded()
            - $this->getQtyCanceled();
        return max($qty, 0);
    }

    /**
     * Retrieve item qty available for invoice
     *
     * @return float|integer
     */
    public function getQtyToInvoice()
    {
        if ($this->isDummy()) {
            return 0;
        }

        $qty = $this->getQtyOrdered()
            - $this->getQtyInvoiced()
            - $this->getQtyCanceled();
        return max($qty, 0);
    }

    /**
     * Retrieve item qty available for refund
     *
     * @return float|integer
     */
    public function getQtyToRefund()
    {
        if ($this->isDummy()) {
            return 0;
        }
        return max($this->getQtyInvoiced() - $this->getQtyRefunded(), 0);
    }

    /**
     * Retrieve item qty available for cancel
     *
     * @return float|integer
     */
    public function getQtyToCancel()
    {
        if ($this->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            $qtyToCancel = $this->getQtyToCancelBundle();
        } elseif ($this->getParentItem()
            && $this->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE
        ) {
            $qtyToCancel = $this->getQtyToCancelBundleItem();
        } else {
            $qtyToCancel = min($this->getQtyToInvoice(), $this->getQtyToShip());
        }
        return max($qtyToCancel, 0);
    }

    /**
     * Retrieve Bundle item qty available for cancel
     * getQtyToInvoice() will always deliver 0 for Bundle
     *
     * @return float|integer
     */
    public function getQtyToCancelBundle()
    {
        if ($this->isDummy()) {
            $qty = $this->getQtyOrdered()
                - $this->getQtyInvoiced()
                - $this->getQtyCanceled();
            return min(max($qty, 0), $this->getQtyToShip());
        }
        return min($this->getQtyToInvoice(), $this->getQtyToShip());
    }

    /**
     * Retrieve Bundle child item qty available for cancel
     * getQtyToShip() always returns 0 for BundleItems that ship together
     *
     * @return float|integer
     */
    public function getQtyToCancelBundleItem()
    {
        if ($this->isDummy(true)) {
            return min($this->getQtyToInvoice(), $this->getSimpleQtyToShip());
        }
        return min($this->getQtyToInvoice(), $this->getQtyToShip());
    }

    /**
     * Declare order
     *
     * @return  $this
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;
        if ($this->getOrderId() != $order->getId()) {
            $this->setOrderId($order->getId());
        }
        return $this;
    }

    /**
     * Retrieve order model object
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (is_null($this->_order) && ($orderId = $this->getOrderId())) {
            $order = Mage::getModel('sales/order');
            $order->load($orderId);
            $this->setOrder($order);
        }
        return $this->_order;
    }

    /**
     * Retrieve item status identifier
     *
     * @return int
     */
    public function getStatusId()
    {
        $backordered = (float) $this->getQtyBackordered();
        if (!$backordered && $this->getHasChildren()) {
            $backordered = (float) $this->_getQtyChildrenBackordered();
        }
        $canceled    = (float) $this->getQtyCanceled();
        $invoiced    = (float) $this->getQtyInvoiced();
        $ordered     = (float) $this->getQtyOrdered();
        $refunded    = (float) $this->getQtyRefunded();
        $shipped     = (float) $this->getQtyShipped();

        $actuallyOrdered = $ordered - $canceled - $refunded;

        if (!$invoiced && !$shipped && !$refunded && !$canceled && !$backordered) {
            return self::STATUS_PENDING;
        }
        if ($shipped && $invoiced && ($actuallyOrdered == $shipped)) {
            return self::STATUS_SHIPPED;
        }

        if ($invoiced && !$shipped && ($actuallyOrdered == $invoiced)) {
            return self::STATUS_INVOICED;
        }

        if ($backordered && ($actuallyOrdered == $backordered)) {
            return self::STATUS_BACKORDERED;
        }

        if ($refunded && $ordered == $refunded) {
            return self::STATUS_REFUNDED;
        }

        if ($canceled && $ordered == $canceled) {
            return self::STATUS_CANCELED;
        }

        if (max($shipped, $invoiced) < $actuallyOrdered) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_MIXED;
    }

    /**
     * Retrieve backordered qty of children items
     *
     * @return float|null
     */
    protected function _getQtyChildrenBackordered()
    {
        $backordered = null;
        foreach ($this->_children as $childItem) {
            $backordered += (float) $childItem->getQtyBackordered();
        }

        return $backordered;
    }

    /**
     * Retrieve status
     *
     * @return string
     */
    public function getStatus()
    {
        return self::getStatusName($this->getStatusId());
    }

    /**
     * Retrieve status name
     *
     * @param int $statusId
     * @return string
     */
    public static function getStatusName($statusId)
    {
        if (is_null(self::$_statuses)) {
            self::getStatuses();
        }
        return self::$_statuses[$statusId] ?? Mage::helper('sales')->__('Unknown Status');
    }

    /**
     * Cancel order item
     *
     * @return $this
     */
    public function cancel()
    {
        if ($this->getStatusId() !== self::STATUS_CANCELED) {
            Mage::dispatchEvent('sales_order_item_cancel', ['item' => $this]);
            $this->setQtyCanceled($this->getQtyToCancel());
            $this->setTaxCanceled(
                $this->getTaxCanceled()
                + $this->getBaseTaxAmount() * $this->getQtyCanceled() / $this->getQtyOrdered(),
            );
            $this->setHiddenTaxCanceled(
                $this->getHiddenTaxCanceled()
                + $this->getHiddenTaxAmount() * $this->getQtyCanceled() / $this->getQtyOrdered(),
            );
        }
        return $this;
    }

    /**
     * Retrieve order item statuses array
     *
     * @return array
     */
    public static function getStatuses()
    {
        self::$_statuses ??= [
            self::STATUS_PENDING        => Mage::helper('sales')->__('Ordered'),
            self::STATUS_SHIPPED        => Mage::helper('sales')->__('Shipped'),
            self::STATUS_INVOICED       => Mage::helper('sales')->__('Invoiced'),
            self::STATUS_BACKORDERED    => Mage::helper('sales')->__('Backordered'),
            self::STATUS_RETURNED       => Mage::helper('sales')->__('Returned'),
            self::STATUS_REFUNDED       => Mage::helper('sales')->__('Refunded'),
            self::STATUS_CANCELED       => Mage::helper('sales')->__('Canceled'),
            self::STATUS_PARTIAL        => Mage::helper('sales')->__('Partial'),
            self::STATUS_MIXED          => Mage::helper('sales')->__('Mixed'),
        ];
        return self::$_statuses;
    }

    /**
     * Redeclare getter for back compatibility
     *
     * @return float
     */
    public function getOriginalPrice()
    {
        $price = $this->getData('original_price');
        if (is_null($price)) {
            return $this->getPrice();
        }
        return $price;
    }

    /**
     * Set product options
     *
     * @return  $this
     */
    public function setProductOptions(array $options)
    {
        $this->setData('product_options', Mage::helper('core')->jsonEncode($options));
        return $this;
    }

    /**
     * Get product options array
     *
     * @return array
     */
    public function getProductOptions()
    {
        if ($options = $this->_getData('product_options')) {
            return Mage::helper('core/string')->unserialize($options);
        }
        return [];
    }

    /**
     * Get product options array by code.
     * If code is null return all options
     *
     * @param string $code
     * @return array|string|null
     */
    public function getProductOptionByCode($code = null)
    {
        $options = $this->getProductOptions();
        if (is_null($code)) {
            return $options;
        }
        return $options[$code] ?? null;
    }

    /**
     * Display-only notes attached to the item, always a list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProductAdditionalOptions(): array
    {
        $options = $this->getProductOptionByCode('additional_options');
        return is_array($options) ? $options : [];
    }

    /**
     * Return real product type of item or NULL if item is not composite
     *
     * @return array|null
     */
    public function getRealProductType()
    {
        if ($productType = $this->getProductOptionByCode('real_product_type')) {
            return $productType;
        }
        return null;
    }

    /**
     * Adds child item to this item
     *
     * @param Mage_Sales_Model_Order_Item $item
     */
    public function addChildItem($item)
    {
        if ($item instanceof Mage_Sales_Model_Order_Item) {
            $this->_children[] = $item;
        } elseif (is_array($item)) {
            $this->_children = array_merge($this->_children, $item);
        }
    }

    /**
     * Return chilgren items of this item
     *
     * @return array
     */
    public function getChildrenItems()
    {
        return $this->_children;
    }

    /**
     * Return checking of what calculation
     * type was for this product
     *
     * @return bool
     */
    public function isChildrenCalculated()
    {
        if ($parentItem = $this->getParentItem()) {
            $options = $parentItem->getProductOptions();
        } else {
            $options = $this->getProductOptions();
        }

        if (isset($options['product_calculations'])
             && $options['product_calculations'] == Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_CHILD
        ) {
            return true;
        }
        return false;
    }
    /**
     * Check if discount has to be applied to parent item
     *
     * @return bool
     */
    public function getForceApplyDiscountToParentItem()
    {
        if ($this->getParentItem()) {
            $product = $this->getParentItem()->getProduct();
        } else {
            $product = $this->getProduct();
        }

        return $product->getTypeInstance()->getForceApplyDiscountToParentItem();
    }

    /**
     * Return checking of what shipment
     * type was for this product
     *
     * @return bool
     */
    public function isShipSeparately()
    {
        if ($parentItem = $this->getParentItem()) {
            $options = $parentItem->getProductOptions();
        } else {
            $options = $this->getProductOptions();
        }

        if (isset($options['shipment_type'])
            && $options['shipment_type'] == Mage_Catalog_Model_Product_Type_Abstract::SHIPMENT_SEPARATELY
        ) {
            return true;
        }
        return false;
    }

    /**
     * This is Dummy item or not
     * if $shipment is true then we checking this for shipping situation if not
     * then we checking this for calculation
     *
     * @param bool $shipment
     * @return bool
     */
    public function isDummy($shipment = false)
    {
        if ($shipment) {
            if ($this->getHasChildren() && $this->isShipSeparately()) {
                return true;
            }

            if ($this->getHasChildren() && !$this->isShipSeparately()) {
                return false;
            }

            if ($this->getParentItem() && $this->isShipSeparately()) {
                return false;
            }

            if ($this->getParentItem() && !$this->isShipSeparately()) {
                return true;
            }
        } else {
            if ($this->getHasChildren() && $this->isChildrenCalculated()) {
                return true;
            }

            if ($this->getHasChildren() && !$this->isChildrenCalculated()) {
                return false;
            }

            if ($this->getParentItem() && $this->isChildrenCalculated()) {
                return false;
            }

            if ($this->getParentItem() && !$this->isChildrenCalculated()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns formatted buy request - object, holding request received from
     * product view page with keys and options for configured product
     *
     * @return \Maho\DataObject
     */
    public function getBuyRequest()
    {
        $option = $this->getProductOptionByCode('info_buyRequest');
        if (!$option) {
            $option = [];
        }
        $buyRequest = new \Maho\DataObject($option);
        $buyRequest->setQty($this->getQtyOrdered() * 1);
        return $buyRequest;
    }

    /**
     * Retrieve product
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        if (!$this->getData('product')) {
            $product = Mage::getModel('catalog/product')->setStoreId($this->getStoreId())->load($this->getProductId());
            $this->setProduct($product);
        }

        return $this->getData('product');
    }

    /**
     * Get the discount amount applied on weee in base
     *
     * @return float
     */
    public function getBaseDiscountAppliedForWeeeTax()
    {
        $weeeTaxAppliedAmounts = Mage::helper('core/string')->unserialize($this->getWeeeTaxApplied());
        $totalDiscount = 0;
        if (!is_array($weeeTaxAppliedAmounts)) {
            return $totalDiscount;
        }
        foreach ($weeeTaxAppliedAmounts as $weeeTaxAppliedAmount) {
            if (isset($weeeTaxAppliedAmount['total_base_weee_discount'])) {
                return $weeeTaxAppliedAmount['total_base_weee_discount'];
            }
            $totalDiscount += $weeeTaxAppliedAmount['base_weee_discount'] ?? 0;
        }
        return $totalDiscount;
    }

    /**
     * Get the discount amount applied on Weee
     *
     * @return float
     */
    public function getDiscountAppliedForWeeeTax()
    {
        $weeeTaxAppliedAmounts = Mage::helper('core/string')->unserialize($this->getWeeeTaxApplied());
        $totalDiscount = 0;
        if (!is_array($weeeTaxAppliedAmounts)) {
            return $totalDiscount;
        }
        foreach ($weeeTaxAppliedAmounts as $weeeTaxAppliedAmount) {
            if (isset($weeeTaxAppliedAmount['total_weee_discount'])) {
                return $weeeTaxAppliedAmount['total_weee_discount'];
            }
            $totalDiscount += $weeeTaxAppliedAmount['weee_discount'] ?? 0;
        }
        return $totalDiscount;
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

    public function getParentItemId(): ?int
    {
        $value = $this->getData('parent_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setParentItemId(?int $value): static
    {
        return $this->setData('parent_item_id', $value);
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

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
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

    public function getProductType(): ?string
    {
        $value = $this->getData('product_type');
        return $value === null ? null : (string) $value;
    }

    public function setProductType(?string $value): static
    {
        return $this->setData('product_type', $value);
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

    public function getIsVirtual(): ?bool
    {
        $value = $this->getData('is_virtual');
        return $value === null ? null : (bool) $value;
    }

    public function setIsVirtual(?bool $value = true): static
    {
        return $this->setData('is_virtual', $value);
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

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
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

    public function getAppliedRuleIds(): ?string
    {
        $value = $this->getData('applied_rule_ids');
        return $value === null ? null : (string) $value;
    }

    public function setAppliedRuleIds(?string $value): static
    {
        return $this->setData('applied_rule_ids', $value);
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

    public function getFreeShipping(): ?bool
    {
        $value = $this->getData('free_shipping');
        return $value === null ? null : (bool) $value;
    }

    public function setFreeShipping(?bool $value = true): static
    {
        return $this->setData('free_shipping', $value);
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

    public function getNoDiscount(): ?bool
    {
        $value = $this->getData('no_discount');
        return $value === null ? null : (bool) $value;
    }

    public function setNoDiscount(?bool $value = true): static
    {
        return $this->setData('no_discount', $value);
    }

    public function getQtyBackordered(): ?float
    {
        $value = $this->getData('qty_backordered');
        return $value === null ? null : (float) $value;
    }

    public function setQtyBackordered(?float $value): static
    {
        return $this->setData('qty_backordered', $value);
    }

    public function getQtyCanceled(): ?float
    {
        $value = $this->getData('qty_canceled');
        return $value === null ? null : (float) $value;
    }

    public function setQtyCanceled(?float $value): static
    {
        return $this->setData('qty_canceled', $value);
    }

    public function getQtyInvoiced(): ?float
    {
        $value = $this->getData('qty_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setQtyInvoiced(?float $value): static
    {
        return $this->setData('qty_invoiced', $value);
    }

    public function getQtyOrdered(): ?float
    {
        $value = $this->getData('qty_ordered');
        return $value === null ? null : (float) $value;
    }

    public function setQtyOrdered(?float $value): static
    {
        return $this->setData('qty_ordered', $value);
    }

    public function getQtyRefunded(): ?float
    {
        $value = $this->getData('qty_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setQtyRefunded(?float $value): static
    {
        return $this->setData('qty_refunded', $value);
    }

    public function getQtyShipped(): ?float
    {
        $value = $this->getData('qty_shipped');
        return $value === null ? null : (float) $value;
    }

    public function setQtyShipped(?float $value): static
    {
        return $this->setData('qty_shipped', $value);
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

    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float) $value;
    }

    public function setPrice(?float $value): static
    {
        return $this->setData('price', $value);
    }

    public function getBasePrice(): ?float
    {
        $value = $this->getData('base_price');
        return $value === null ? null : (float) $value;
    }

    public function setBasePrice(?float $value): static
    {
        return $this->setData('base_price', $value);
    }

    public function setOriginalPrice(?float $value): static
    {
        return $this->setData('original_price', $value);
    }

    public function getBaseOriginalPrice(): ?float
    {
        $value = $this->getData('base_original_price');
        return $value === null ? null : (float) $value;
    }

    public function setBaseOriginalPrice(?float $value): static
    {
        return $this->setData('base_original_price', $value);
    }

    public function getTaxPercent(): ?float
    {
        $value = $this->getData('tax_percent');
        return $value === null ? null : (float) $value;
    }

    public function setTaxPercent(?float $value): static
    {
        return $this->setData('tax_percent', $value);
    }

    public function getTaxAmount(): ?float
    {
        $value = $this->getData('tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxAmount(?float $value): static
    {
        return $this->setData('tax_amount', $value);
    }

    public function getBaseTaxAmount(): ?float
    {
        $value = $this->getData('base_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxAmount(?float $value): static
    {
        return $this->setData('base_tax_amount', $value);
    }

    public function getTaxInvoiced(): ?float
    {
        $value = $this->getData('tax_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setTaxInvoiced(?float $value): static
    {
        return $this->setData('tax_invoiced', $value);
    }

    public function getBaseTaxInvoiced(): ?float
    {
        $value = $this->getData('base_tax_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxInvoiced(?float $value): static
    {
        return $this->setData('base_tax_invoiced', $value);
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

    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountAmount(?float $value): static
    {
        return $this->setData('discount_amount', $value);
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

    public function getDiscountInvoiced(): ?float
    {
        $value = $this->getData('discount_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountInvoiced(?float $value): static
    {
        return $this->setData('discount_invoiced', $value);
    }

    public function getBaseDiscountInvoiced(): ?float
    {
        $value = $this->getData('base_discount_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setBaseDiscountInvoiced(?float $value): static
    {
        return $this->setData('base_discount_invoiced', $value);
    }

    public function getAmountRefunded(): ?float
    {
        $value = $this->getData('amount_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setAmountRefunded(?float $value): static
    {
        return $this->setData('amount_refunded', $value);
    }

    public function getBaseAmountRefunded(): ?float
    {
        $value = $this->getData('base_amount_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setBaseAmountRefunded(?float $value): static
    {
        return $this->setData('base_amount_refunded', $value);
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

    public function getBaseRowTotal(): ?float
    {
        $value = $this->getData('base_row_total');
        return $value === null ? null : (float) $value;
    }

    public function setBaseRowTotal(?float $value): static
    {
        return $this->setData('base_row_total', $value);
    }

    public function getRowInvoiced(): ?float
    {
        $value = $this->getData('row_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setRowInvoiced(?float $value): static
    {
        return $this->setData('row_invoiced', $value);
    }

    public function getBaseRowInvoiced(): ?float
    {
        $value = $this->getData('base_row_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setBaseRowInvoiced(?float $value): static
    {
        return $this->setData('base_row_invoiced', $value);
    }

    public function getRowWeight(): ?float
    {
        $value = $this->getData('row_weight');
        return $value === null ? null : (float) $value;
    }

    public function setRowWeight(?float $value): static
    {
        return $this->setData('row_weight', $value);
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

    public function getGiftMessageAvailable(): ?int
    {
        $value = $this->getData('gift_message_available');
        return $value === null ? null : (int) $value;
    }

    public function setGiftMessageAvailable(?int $value): static
    {
        return $this->setData('gift_message_available', $value);
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

    public function getTaxBeforeDiscount(): ?float
    {
        $value = $this->getData('tax_before_discount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxBeforeDiscount(?float $value): static
    {
        return $this->setData('tax_before_discount', $value);
    }

    public function getExtOrderItemId(): ?string
    {
        $value = $this->getData('ext_order_item_id');
        return $value === null ? null : (string) $value;
    }

    public function setExtOrderItemId(?string $value): static
    {
        return $this->setData('ext_order_item_id', $value);
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

    public function getLockedDoInvoice(): ?bool
    {
        $value = $this->getData('locked_do_invoice');
        return $value === null ? null : (bool) $value;
    }

    public function setLockedDoInvoice(?bool $value = true): static
    {
        return $this->setData('locked_do_invoice', $value);
    }

    public function getLockedDoShip(): ?bool
    {
        $value = $this->getData('locked_do_ship');
        return $value === null ? null : (bool) $value;
    }

    public function setLockedDoShip(?bool $value = true): static
    {
        return $this->setData('locked_do_ship', $value);
    }

    public function getPriceInclTax(): ?float
    {
        $value = $this->getData('price_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setPriceInclTax(?float $value): static
    {
        return $this->setData('price_incl_tax', $value);
    }

    public function getBasePriceInclTax(): ?float
    {
        $value = $this->getData('base_price_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBasePriceInclTax(?float $value): static
    {
        return $this->setData('base_price_incl_tax', $value);
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

    public function getBaseRowTotalInclTax(): ?float
    {
        $value = $this->getData('base_row_total_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseRowTotalInclTax(?float $value): static
    {
        return $this->setData('base_row_total_incl_tax', $value);
    }

    public function getHiddenTaxAmount(): ?float
    {
        $value = $this->getData('hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setHiddenTaxAmount(?float $value): static
    {
        return $this->setData('hidden_tax_amount', $value);
    }

    public function getBaseHiddenTaxAmount(): ?float
    {
        $value = $this->getData('base_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseHiddenTaxAmount(?float $value): static
    {
        return $this->setData('base_hidden_tax_amount', $value);
    }

    public function getHiddenTaxInvoiced(): ?float
    {
        $value = $this->getData('hidden_tax_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setHiddenTaxInvoiced(?float $value): static
    {
        return $this->setData('hidden_tax_invoiced', $value);
    }

    public function getBaseHiddenTaxInvoiced(): ?float
    {
        $value = $this->getData('base_hidden_tax_invoiced');
        return $value === null ? null : (float) $value;
    }

    public function setBaseHiddenTaxInvoiced(?float $value): static
    {
        return $this->setData('base_hidden_tax_invoiced', $value);
    }

    public function getHiddenTaxRefunded(): ?float
    {
        $value = $this->getData('hidden_tax_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setHiddenTaxRefunded(?float $value): static
    {
        return $this->setData('hidden_tax_refunded', $value);
    }

    public function getBaseHiddenTaxRefunded(): ?float
    {
        $value = $this->getData('base_hidden_tax_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setBaseHiddenTaxRefunded(?float $value): static
    {
        return $this->setData('base_hidden_tax_refunded', $value);
    }

    public function getIsNominal(): ?bool
    {
        $value = $this->getData('is_nominal');
        return $value === null ? null : (bool) $value;
    }

    public function setIsNominal(?bool $value = true): static
    {
        return $this->setData('is_nominal', $value);
    }

    public function getTaxCanceled(): ?float
    {
        $value = $this->getData('tax_canceled');
        return $value === null ? null : (float) $value;
    }

    public function setTaxCanceled(?float $value): static
    {
        return $this->setData('tax_canceled', $value);
    }

    public function getHiddenTaxCanceled(): ?float
    {
        $value = $this->getData('hidden_tax_canceled');
        return $value === null ? null : (float) $value;
    }

    public function setHiddenTaxCanceled(?float $value): static
    {
        return $this->setData('hidden_tax_canceled', $value);
    }

    public function getTaxRefunded(): ?float
    {
        $value = $this->getData('tax_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setTaxRefunded(?float $value): static
    {
        return $this->setData('tax_refunded', $value);
    }

    public function getBaseTaxRefunded(): ?float
    {
        $value = $this->getData('base_tax_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxRefunded(?float $value): static
    {
        return $this->setData('base_tax_refunded', $value);
    }

    public function getDiscountRefunded(): ?float
    {
        $value = $this->getData('discount_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountRefunded(?float $value): static
    {
        return $this->setData('discount_refunded', $value);
    }

    public function getBaseDiscountRefunded(): ?float
    {
        $value = $this->getData('base_discount_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setBaseDiscountRefunded(?float $value): static
    {
        return $this->setData('base_discount_refunded', $value);
    }

    public function setShippingAmount(?float $value): static
    {
        return $this->setData('shipping_amount', $value);
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

    public function setProduct(?Mage_Catalog_Model_Product $value): static
    {
        return $this->setData('product', $value);
    }

    public function setGiftMessage(?string $value): static
    {
        return $this->setData('gift_message', $value);
    }

    public function setQuoteParentItemId(?int $value): static
    {
        return $this->setData('quote_parent_item_id', $value);
    }

    public function getParentProductId(): ?int
    {
        $value = $this->getData('parent_product_id');
        return $value === null ? null : (int) $value;
    }

    public function getQuoteParentItemId(): ?int
    {
        $value = $this->getData('quote_parent_item_id');
        return $value === null ? null : (int) $value;
    }
}
