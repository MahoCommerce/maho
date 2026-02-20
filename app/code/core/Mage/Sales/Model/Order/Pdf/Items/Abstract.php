<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Sales_Model_Order_Pdf_Items_Abstract extends Mage_Core_Block_Template
{
    /**
     * Order model
     *
     * @var Mage_Sales_Model_Order|null
     */
    protected $_order;

    /**
     * Source model (invoice, shipment, creditmemo)
     *
     * @var Mage_Core_Model_Abstract|null
     */
    protected $_source;

    /**
     * Item object
     *
     * @var \Maho\DataObject|null
     */
    protected $_item;

    /**
     * Pdf object
     *
     * @var Mage_Sales_Model_Order_Pdf_Abstract|null
     */
    protected $_pdf;

    /**
     * Set order model
     *
     * @return $this
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;
        return $this;
    }

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * Set Source model
     *
     * @return $this
     */
    public function setSource(Mage_Core_Model_Abstract $source)
    {
        $this->_source = $source;
        return $this;
    }

    /**
     * Get source model
     *
     * @return Mage_Core_Model_Abstract|null
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * Set item object
     *
     * @return $this
     */
    public function setItem(\Maho\DataObject $item)
    {
        $this->_item = $item;
        return $this;
    }

    /**
     * Get item object
     *
     * @return \Maho\DataObject|null
     */
    public function getItem()
    {
        return $this->_item;
    }

    /**
     * Set Pdf model
     *
     * @return $this
     */
    public function setPdf(Mage_Sales_Model_Order_Pdf_Abstract $pdf)
    {
        $this->_pdf = $pdf;
        return $this;
    }

    /**
     * Get Pdf model
     *
     * @return Mage_Sales_Model_Order_Pdf_Abstract|null
     */
    public function getPdf()
    {
        return $this->_pdf;
    }

    /**
     * Get order item
     *
     * @return Mage_Sales_Model_Order_Item|null
     */
    public function getOrderItem()
    {
        $item = $this->getItem();
        if ($item && method_exists($item, 'getOrderItem')) {
            return $item->getOrderItem();
        }
        // If item doesn't have getOrderItem method, it's likely already an order item
        // or we need to return null to maintain type safety
        return ($item instanceof Mage_Sales_Model_Order_Item) ? $item : null;
    }

    /**
     * Get product
     *
     * @return Mage_Catalog_Model_Product|null
     */
    public function getProduct()
    {
        $orderItem = $this->getOrderItem();
        return $orderItem ? $orderItem->getProduct() : null;
    }

    /**
     * Get item name
     *
     * @return string
     */
    public function getItemName()
    {
        $item = $this->getItem();
        return $item ? $item->getName() : '';
    }

    /**
     * Get item SKU
     *
     * @return string
     */
    public function getItemSku()
    {
        $item = $this->getItem();
        return $item ? $item->getSku() : '';
    }

    /**
     * Get item price
     *
     * @return float
     */
    public function getItemPrice()
    {
        $item = $this->getItem();
        return $item ? (float) $item->getPrice() : 0.0;
    }

    /**
     * Get item qty
     *
     * @return float
     */
    public function getItemQty()
    {
        $item = $this->getItem();
        return $item ? (float) $item->getQty() : 0.0;
    }

    /**
     * Get item tax
     *
     * @return float
     */
    public function getItemTax()
    {
        $item = $this->getItem();
        return $item ? (float) $item->getTaxAmount() : 0.0;
    }

    /**
     * Get item subtotal
     *
     * @return float
     */
    public function getItemSubtotal()
    {
        $item = $this->getItem();
        return $item ? (float) $item->getRowTotal() : 0.0;
    }

    /**
     * Format price
     *
     * @param float $price
     * @return string
     */
    public function formatPrice($price)
    {
        if ($this->getOrder()) {
            return $this->getOrder()->formatPriceTxt($price);
        }
        return Mage::helper('core')->formatPrice($price, false);
    }

    /**
     * Get item prices for display based on tax configuration
     */
    public function getItemPricesForDisplay(): array
    {
        $order = $this->getOrder();
        $item = $this->getItem();

        if (!$order || !$item) {
            return [];
        }

        $prices = [];

        if (Mage::helper('tax')->displaySalesBothPrices()) {
            $prices[] = [
                'label' => Mage::helper('tax')->__('Excl. Tax') . ':',
                'price' => $order->formatPriceTxt($item->getPrice()),
                'subtotal' => $order->formatPriceTxt($item->getRowTotal()),
            ];
            $prices[] = [
                'label' => Mage::helper('tax')->__('Incl. Tax') . ':',
                'price' => $order->formatPriceTxt($item->getPriceInclTax()),
                'subtotal' => $order->formatPriceTxt($item->getRowTotalInclTax()),
            ];
        } elseif (Mage::helper('tax')->displaySalesPriceInclTax()) {
            $prices[] = [
                'price' => $order->formatPriceTxt($item->getPriceInclTax()),
                'subtotal' => $order->formatPriceTxt($item->getRowTotalInclTax()),
            ];
        } else {
            $prices[] = [
                'price' => $order->formatPriceTxt($item->getPrice()),
                'subtotal' => $order->formatPriceTxt($item->getRowTotal()),
            ];
        }

        return $prices;
    }

    /**
     * Get item options
     *
     * @return array
     */
    public function getItemOptions()
    {
        $result = [];
        $options = $this->getOrderItem()->getProductOptions();
        if ($options) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }
        return $result;
    }

    /**
     * Render item as HTML
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->getItem()) {
            return '';
        }

        return parent::_toHtml();
    }
}
