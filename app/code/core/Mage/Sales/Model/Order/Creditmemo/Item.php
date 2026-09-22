<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Order_Creditmemo_Item _getResource()
 * @method Mage_Sales_Model_Resource_Order_Creditmemo_Item getResource()
 * @method Mage_Sales_Model_Resource_Order_Creditmemo_Item_Collection getCollection()
 *
 * @method bool hasBackToStock()
 * @method bool hasCanReturnToStock()
 */
class Mage_Sales_Model_Order_Creditmemo_Item extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected $_eventPrefix = 'sales_creditmemo_item';
    #[\Override]
    protected $_eventObject = 'creditmemo_item';
    protected $_creditmemo = null;
    protected $_orderItem = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_creditmemo_item');
    }

    /**
     * Declare creditmemo instance
     *
     * @return  $this
     */
    public function setCreditmemo(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $this->_creditmemo = $creditmemo;
        return $this;
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
     * Retrieve creditmemo instance
     *
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function getCreditmemo()
    {
        return $this->_creditmemo;
    }

    /**
     * Declare order item instance
     *
     * @return  $this
     */
    public function setOrderItem(Mage_Sales_Model_Order_Item $item)
    {
        $this->_orderItem = $item;
        if ($this->getOrderItemId() != $item->getId()) {
            $this->setOrderItemId($item->getId());
        }
        return $this;
    }

    /**
     * Retrieve order item instance
     *
     * @return Mage_Sales_Model_Order_Item
     */
    public function getOrderItem()
    {
        if (is_null($this->_orderItem)) {
            if ($this->getCreditmemo()) {
                $this->_orderItem = $this->getCreditmemo()->getOrder()->getItemById($this->getOrderItemId());
            } else {
                $this->_orderItem = Mage::getModel('sales/order_item')
                    ->load($this->getOrderItemId());
            }
        }
        return $this->_orderItem;
    }

    /**
     * Declare qty
     *
     * @param   float $qty
     * @return  $this
     */
    public function setQty($qty)
    {
        if ($this->getOrderItem()->getIsQtyDecimal()) {
            $qty = (float) $qty;
        } else {
            $qty = (int) $qty;
        }
        $qty = $qty > 0 ? $qty : 0;
        /**
         * Check qty availability
         */
        if ($qty <= $this->getOrderItem()->getQtyToRefund() || $this->getOrderItem()->isDummy()) {
            $this->setData('qty', $qty);
        } else {
            Mage::throwException(
                Mage::helper('sales')->__('Invalid qty to refund item "%s"', $this->getName()),
            );
        }
        return $this;
    }

    /**
     * Applying qty to order item
     *
     * @return $this
     */
    public function register()
    {
        $orderItem = $this->getOrderItem();

        $orderItem->setQtyRefunded($orderItem->getQtyRefunded() + $this->getQty());
        $orderItem->setTaxRefunded($orderItem->getTaxRefunded() + $this->getTaxAmount());
        $orderItem->setBaseTaxRefunded($orderItem->getBaseTaxRefunded() + $this->getBaseTaxAmount());
        $orderItem->setHiddenTaxRefunded($orderItem->getHiddenTaxRefunded() + $this->getHiddenTaxAmount());
        $orderItem->setBaseHiddenTaxRefunded($orderItem->getBaseHiddenTaxRefunded() + $this->getBaseHiddenTaxAmount());
        $orderItem->setAmountRefunded($orderItem->getAmountRefunded() + $this->getRowTotal());
        $orderItem->setBaseAmountRefunded($orderItem->getBaseAmountRefunded() + $this->getBaseRowTotal());
        $orderItem->setDiscountRefunded($orderItem->getDiscountRefunded() + $this->getDiscountAmount());
        $orderItem->setBaseDiscountRefunded($orderItem->getBaseDiscountRefunded() + $this->getBaseDiscountAmount());

        return $this;
    }

    /**
     * @return $this
     */
    public function cancel()
    {
        $this->getOrderItem()->setQtyRefunded(
            $this->getOrderItem()->getQtyRefunded() - $this->getQty(),
        );
        $this->getOrderItem()->setTaxRefunded(
            $this->getOrderItem()->getTaxRefunded()
                - $this->getOrderItem()->getBaseTaxAmount() * $this->getQty() / $this->getOrderItem()->getQtyOrdered(),
        );
        $this->getOrderItem()->setHiddenTaxRefunded(
            $this->getOrderItem()->getHiddenTaxRefunded()
                - $this->getOrderItem()->getHiddenTaxAmount() * $this->getQty() / $this->getOrderItem()->getQtyOrdered(),
        );
        return $this;
    }

    /**
     * Invoice item row total calculation
     *
     * @return $this
     */
    public function calcRowTotal()
    {
        $creditmemo           = $this->getCreditmemo();
        $orderItem            = $this->getOrderItem();
        $orderItemQtyInvoiced = $orderItem->getQtyInvoiced();

        $rowTotal            = $orderItem->getRowInvoiced() - $orderItem->getAmountRefunded();
        $baseRowTotal        = $orderItem->getBaseRowInvoiced() - $orderItem->getBaseAmountRefunded();
        $rowTotalInclTax     = $orderItem->getRowTotalInclTax();
        $baseRowTotalInclTax = $orderItem->getBaseRowTotalInclTax();

        if (!$this->isLast()) {
            $availableQty = $orderItemQtyInvoiced - $orderItem->getQtyRefunded();
            $rowTotal     = $creditmemo->roundPrice($rowTotal / $availableQty * $this->getQty());
            $baseRowTotal = $creditmemo->roundPrice($baseRowTotal / $availableQty * $this->getQty(), 'base');
        }
        $this->setRowTotal($rowTotal);
        $this->setBaseRowTotal($baseRowTotal);

        if ($rowTotalInclTax && $baseRowTotalInclTax) {
            $orderItemQty = $orderItem->getQtyOrdered();
            $this->setRowTotalInclTax(
                $creditmemo->roundPrice($rowTotalInclTax / $orderItemQty * $this->getQty(), 'including'),
            );
            $this->setBaseRowTotalInclTax(
                $creditmemo->roundPrice($baseRowTotalInclTax / $orderItemQty * $this->getQty(), 'including_base'),
            );
        }
        return $this;
    }

    /**
     * Checking if the item is last
     *
     * @return bool
     */
    public function isLast()
    {
        $orderItem = $this->getOrderItem();
        if ((string) (float) $this->getQty() == (string) (float) $orderItem->getQtyToRefund()
                && !$orderItem->getQtyToInvoice()
        ) {
            return true;
        }
        return false;
    }

    /**
     * Before object save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if (!$this->getParentId() && $this->getCreditmemo()) {
            $this->setParentId($this->getCreditmemo()->getId());
        }

        return $this;
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

    public function getBackToStock(): ?bool
    {
        $value = $this->getData('back_to_stock');
        return $value === null ? null : (bool) $value;
    }

    public function setBackToStock(?bool $value = true): static
    {
        return $this->setData('back_to_stock', $value);
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

    public function getBaseDiscountAmount(): ?float
    {
        $value = $this->getData('base_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseDiscountAmount(?float $value): static
    {
        return $this->setData('base_discount_amount', $value);
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

    public function getBasePrice(): ?float
    {
        $value = $this->getData('base_price');
        return $value === null ? null : (float) $value;
    }

    public function setBasePrice(?float $value): static
    {
        return $this->setData('base_price', $value);
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

    public function getBaseTaxAmount(): ?float
    {
        $value = $this->getData('base_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxAmount(?float $value): static
    {
        return $this->setData('base_tax_amount', $value);
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

    public function getCanReturnToStock(): ?bool
    {
        $value = $this->getData('can_return_to_stock');
        return $value === null ? null : (bool) $value;
    }

    public function setCanReturnToStock(?bool $value = true): static
    {
        return $this->setData('can_return_to_stock', $value);
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

    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountAmount(?float $value): static
    {
        return $this->setData('discount_amount', $value);
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

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
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

    public function getParentId(): ?int
    {
        $value = $this->getData('parent_id');
        return $value === null ? null : (int) $value;
    }

    public function setParentId(?int $value): static
    {
        return $this->setData('parent_id', $value);
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

    public function getPriceInclTax(): ?float
    {
        $value = $this->getData('price_incl_tax');
        return $value === null ? null : (float) $value;
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

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getQty(): ?float
    {
        $value = $this->getData('qty');
        return $value === null ? null : (float) $value;
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

    public function getRowTotalInclTax(): ?float
    {
        $value = $this->getData('row_total_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setRowTotalInclTax(?float $value): static
    {
        return $this->setData('row_total_incl_tax', $value);
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

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
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

    public function getWeeeTaxApplied(): ?string
    {
        $value = $this->getData('weee_tax_applied');
        return $value === null ? null : (string) $value;
    }

    public function setWeeeTaxApplied(?string $value): static
    {
        return $this->setData('weee_tax_applied', $value);
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
