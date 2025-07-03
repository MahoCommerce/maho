<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Order_Pdf_Creditmemo extends Mage_Core_Block_Template
{
    protected $_creditmemo;
    protected $_order;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/creditmemo/default.phtml');
    }

    public function setDocument($creditmemo)
    {
        $this->_creditmemo = $creditmemo;
        $this->_order = $creditmemo->getOrder();
        return $this;
    }

    public function setOrder($order)
    {
        $this->_order = $order;
        return $this;
    }

    public function getCreditmemo()
    {
        return $this->_creditmemo;
    }

    public function getOrder()
    {
        return $this->_order;
    }

    public function getCreditmemoNumber()
    {
        return $this->_creditmemo ? $this->_creditmemo->getIncrementId() : '';
    }

    public function getCreditmemoDate()
    {
        if ($this->_creditmemo) {
            return Mage::helper('core')->formatDate($this->_creditmemo->getCreatedAt(), 'medium', false);
        }
        return '';
    }

    public function getOrderNumber()
    {
        if ($this->_order && Mage::getStoreConfigFlag(Mage_Sales_Model_Order_Pdf_Abstract::XML_PATH_SALES_PDF_CREDITMEMO_PUT_ORDER_ID, $this->_order->getStoreId())) {
            return $this->_order->getRealOrderId();
        }
        return '';
    }

    public function getOrderDate()
    {
        if ($this->_order) {
            return Mage::helper('core')->formatDate($this->_order->getCreatedAtStoreDate(), 'medium', false);
        }
        return '';
    }

    public function getBillingAddress()
    {
        return $this->_order ? $this->_order->getBillingAddress() : null;
    }

    public function getShippingAddress()
    {
        return $this->_order ? $this->_order->getShippingAddress() : null;
    }

    public function getPaymentInfo()
    {
        if (!$this->_order || !$this->_order->getPayment()) {
            return '';
        }

        $payment = $this->_order->getPayment();
        $paymentBlock = Mage::helper('payment')->getInfoBlock($payment)
            ->setIsSecureMode(true);

        // Use HTML output instead of PDF output since we're generating HTML
        return $paymentBlock->toHtml();
    }

    public function getLogoUrl()
    {
        $logoFile = Mage::getStoreConfig('sales/identity/logo', $this->getStore());
        if ($logoFile) {
            $logoPath = Mage::getBaseDir('media') . DS . 'sales' . DS . 'store' . DS . 'logo' . DS . $logoFile;
            if (file_exists($logoPath)) {
                $imageData = file_get_contents($logoPath);
                $mimeType = mime_content_type($logoPath);
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        }
        return null;
    }

    public function getStore()
    {
        return $this->_order ? $this->_order->getStore() : Mage::app()->getStore();
    }

    public function getStoreAddress()
    {
        return Mage::getStoreConfig('sales/identity/address', $this->getStore());
    }

    public function getItems()
    {
        $items = [];
        if ($this->_creditmemo) {
            foreach ($this->_creditmemo->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                $items[] = $item;
            }
        }
        return $items;
    }

    public function getItemHtml($item)
    {
        $orderItem = $item->getOrderItem();
        $type = $orderItem->getProductType();

        $renderer = $this->_getItemRenderer($type);
        if (!$renderer) {
            $renderer = $this->_getItemRenderer('default');
        }

        $renderer->setItem($item);
        $renderer->setOrder($this->getOrder());
        $renderer->setSource($this->getCreditmemo());

        return $renderer->toHtml();
    }

    protected function _getItemRenderer($type)
    {
        $rendererModel = Mage::getStoreConfig('sales_pdf/creditmemo/' . $type) ?: 'sales/order_pdf_items_creditmemo_default';
        if (!isset($this->_renderers[$type])) {
            $this->_renderers[$type] = new Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Default();
        }
        return $this->_renderers[$type];
    }

    protected $_renderers = [];

    public function getTotals()
    {
        if (!$this->_creditmemo) {
            return [];
        }

        $totals = [];

        // Subtotal
        $totals[] = [
            'label' => $this->__('Subtotal'),
            'value' => $this->formatPrice($this->_creditmemo->getSubtotal()),
        ];

        // Discount
        if (abs($this->_creditmemo->getDiscountAmount()) > 0.01) {
            $totals[] = [
                'label' => $this->__('Discount'),
                'value' => $this->formatPrice(-$this->_creditmemo->getDiscountAmount()),
            ];
        }

        // Shipping
        if (!$this->_order->getIsVirtual() && abs($this->_creditmemo->getShippingAmount()) > 0.01) {
            $totals[] = [
                'label' => $this->__('Shipping & Handling'),
                'value' => $this->formatPrice($this->_creditmemo->getShippingAmount()),
            ];
        }

        // Adjustment Refund
        if (abs($this->_creditmemo->getAdjustmentPositive()) > 0.01) {
            $totals[] = [
                'label' => $this->__('Adjustment Refund'),
                'value' => $this->formatPrice($this->_creditmemo->getAdjustmentPositive()),
            ];
        }

        // Adjustment Fee
        if (abs($this->_creditmemo->getAdjustmentNegative()) > 0.01) {
            $totals[] = [
                'label' => $this->__('Adjustment Fee'),
                'value' => $this->formatPrice($this->_creditmemo->getAdjustmentNegative()),
            ];
        }

        // Tax
        if (abs($this->_creditmemo->getTaxAmount()) > 0.01) {
            $totals[] = [
                'label' => $this->__('Tax'),
                'value' => $this->formatPrice($this->_creditmemo->getTaxAmount()),
            ];
        }

        // Grand Total
        $totals[] = [
            'label' => $this->__('Grand Total'),
            'value' => $this->formatPrice($this->_creditmemo->getGrandTotal()),
            'strong' => true,
        ];

        return $totals;
    }

    public function formatPrice($price)
    {
        return $this->_order->formatPriceTxt($price);
    }

}
