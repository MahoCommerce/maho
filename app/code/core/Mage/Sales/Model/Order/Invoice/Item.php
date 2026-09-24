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
 * @method Mage_Sales_Model_Resource_Order_Invoice_Item _getResource()
 * @method Mage_Sales_Model_Resource_Order_Invoice_Item getResource()
 */
class Mage_Sales_Model_Order_Invoice_Item extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected $_eventPrefix = 'sales_invoice_item';
    #[\Override]
    protected $_eventObject = 'invoice_item';

    /**
     * @var Mage_Sales_Model_Order_Invoice
     */
    protected $_invoice = null;

    /**
     * @var Mage_Sales_Model_Order_Item|null
     */
    protected $_orderItem = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_invoice_item');
    }

    /**
     * Init mapping array of short fields to
     * its full names
     *
     * @return $this
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
     * Declare invoice instance
     *
     * @return  $this
     */
    public function setInvoice(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $this->_invoice = $invoice;
        return $this;
    }

    /**
     * Retrieve invoice instance
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function getInvoice()
    {
        return $this->_invoice;
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
            if ($this->getInvoice()) {
                $this->_orderItem = $this->getInvoice()->getOrder()->getItemById($this->getOrderItemId());
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
        $qtyToInvoice = sprintf('%F', $this->getOrderItem()->getQtyToInvoice());
        $qty = sprintf('%F', $qty);
        if ($qty <= $qtyToInvoice || $this->getOrderItem()->isDummy()) {
            $this->setData('qty', $qty);
        } else {
            Mage::throwException(
                Mage::helper('sales')->__('Invalid qty to invoice item "%s"', $this->getName()),
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
        $orderItem->setQtyInvoiced($orderItem->getQtyInvoiced() + $this->getQty());

        $orderItem->setTaxInvoiced($orderItem->getTaxInvoiced() + $this->getTaxAmount());
        $orderItem->setBaseTaxInvoiced($orderItem->getBaseTaxInvoiced() + $this->getBaseTaxAmount());
        $orderItem->setHiddenTaxInvoiced($orderItem->getHiddenTaxInvoiced() + $this->getHiddenTaxAmount());
        $orderItem->setBaseHiddenTaxInvoiced($orderItem->getBaseHiddenTaxInvoiced() + $this->getBaseHiddenTaxAmount());

        $orderItem->setDiscountInvoiced($orderItem->getDiscountInvoiced() + $this->getDiscountAmount());
        $orderItem->setBaseDiscountInvoiced($orderItem->getBaseDiscountInvoiced() + $this->getBaseDiscountAmount());

        $orderItem->setRowInvoiced($orderItem->getRowInvoiced() + $this->getRowTotal());
        $orderItem->setBaseRowInvoiced($orderItem->getBaseRowInvoiced() + $this->getBaseRowTotal());
        return $this;
    }

    /**
     * Cancelling invoice item
     *
     * @return $this
     */
    public function cancel()
    {
        $orderItem = $this->getOrderItem();
        $orderItem->setQtyInvoiced($orderItem->getQtyInvoiced() - $this->getQty());

        $orderItem->setTaxInvoiced($orderItem->getTaxInvoiced() - $this->getTaxAmount());
        $orderItem->setBaseTaxInvoiced($orderItem->getBaseTaxInvoiced() - $this->getBaseTaxAmount());
        $orderItem->setHiddenTaxInvoiced($orderItem->getHiddenTaxInvoiced() - $this->getHiddenTaxAmount());
        $orderItem->setBaseHiddenTaxInvoiced($orderItem->getBaseHiddenTaxInvoiced() - $this->getBaseHiddenTaxAmount());

        $orderItem->setDiscountInvoiced($orderItem->getDiscountInvoiced() - $this->getDiscountAmount());
        $orderItem->setBaseDiscountInvoiced($orderItem->getBaseDiscountInvoiced() - $this->getBaseDiscountAmount());

        $orderItem->setRowInvoiced($orderItem->getRowInvoiced() - $this->getRowTotal());
        $orderItem->setBaseRowInvoiced($orderItem->getBaseRowInvoiced() - $this->getBaseRowTotal());
        return $this;
    }

    /**
     * Invoice item row total calculation
     *
     * @return $this
     */
    public function calcRowTotal()
    {
        $invoice        = $this->getInvoice();
        $orderItem      = $this->getOrderItem();
        $orderItemQty   = $orderItem->getQtyOrdered();

        $rowTotal            = $orderItem->getRowTotal() - $orderItem->getRowInvoiced();
        $baseRowTotal        = $orderItem->getBaseRowTotal() - $orderItem->getBaseRowInvoiced();
        $rowTotalInclTax     = $orderItem->getRowTotalInclTax();
        $baseRowTotalInclTax = $orderItem->getBaseRowTotalInclTax();

        if (!$this->isLast()) {
            $availableQty = $orderItemQty - $orderItem->getQtyInvoiced();
            $rowTotal = $invoice->roundPrice($rowTotal / $availableQty * $this->getQty());
            $baseRowTotal = $invoice->roundPrice($baseRowTotal / $availableQty * $this->getQty(), 'base');
        }

        $this->setRowTotal($rowTotal);
        $this->setBaseRowTotal($baseRowTotal);

        if ($rowTotalInclTax && $baseRowTotalInclTax) {
            $this->setRowTotalInclTax($invoice->roundPrice($rowTotalInclTax / $orderItemQty * $this->getQty(), 'including'));
            $this->setBaseRowTotalInclTax($invoice->roundPrice($baseRowTotalInclTax / $orderItemQty * $this->getQty(), 'including_base'));
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
        if ((string) (float) $this->getQty() == (string) (float) $this->getOrderItem()->getQtyToInvoice()) {
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

        if (!$this->getParentId() && $this->getInvoice()) {
            $this->setParentId($this->getInvoice()->getId());
        }

        return $this;
    }

    /**
     * After object save
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        if (!$this->_orderItem == null) {
            $this->_orderItem->save();
        }

        parent::_afterSave();
        return $this;
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

    public function getBasePrice(): ?float
    {
        $value = $this->getData('base_price');
        return $value === null ? null : (float) $value;
    }

    public function setBasePrice(?float $value): static
    {
        return $this->setData('base_price', $value);
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

    public function getTaxAmount(): ?float
    {
        $value = $this->getData('tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxAmount(?float $value): static
    {
        return $this->setData('tax_amount', $value);
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

    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setDiscountAmount(?float $value): static
    {
        return $this->setData('discount_amount', $value);
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

    public function getWeeeTaxRowDisposition(): ?float
    {
        $value = $this->getData('weee_tax_row_disposition');
        return $value === null ? null : (float) $value;
    }

    public function setWeeeTaxRowDisposition(?float $value): static
    {
        return $this->setData('weee_tax_row_disposition', $value);
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

    public function getBaseWeeeTaxDisposition(): ?float
    {
        $value = $this->getData('base_weee_tax_disposition');
        return $value === null ? null : (float) $value;
    }

    public function setBaseWeeeTaxDisposition(?float $value): static
    {
        return $this->setData('base_weee_tax_disposition', $value);
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

    public function getWeeeTaxAppliedAmount(): ?float
    {
        $value = $this->getData('weee_tax_applied_amount');
        return $value === null ? null : (float) $value;
    }

    public function setWeeeTaxAppliedAmount(?float $value): static
    {
        return $this->setData('weee_tax_applied_amount', $value);
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

    public function getBasePriceInclTax(): ?float
    {
        $value = $this->getData('base_price_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBasePriceInclTax(?float $value): static
    {
        return $this->setData('base_price_incl_tax', $value);
    }

    public function getQty(): ?float
    {
        $value = $this->getData('qty');
        return $value === null ? null : (float) $value;
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

    public function getBaseCost(): ?float
    {
        $value = $this->getData('base_cost');
        return $value === null ? null : (float) $value;
    }

    public function setBaseCost(?float $value): static
    {
        return $this->setData('base_cost', $value);
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

    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float) $value;
    }

    public function setPrice(?float $value): static
    {
        return $this->setData('price', $value);
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

    public function getRowTotalInclTax(): ?float
    {
        $value = $this->getData('row_total_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setRowTotalInclTax(?float $value): static
    {
        return $this->setData('row_total_incl_tax', $value);
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

    public function getOrderItemId(): ?int
    {
        $value = $this->getData('order_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderItemId(?int $value): static
    {
        return $this->setData('order_item_id', $value);
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

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
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

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }
}
