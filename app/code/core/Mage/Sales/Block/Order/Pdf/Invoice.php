<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Order_Pdf_Invoice extends Mage_Core_Block_Template
{
    protected $_invoice;
    protected $_order;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/invoice/default.phtml');
        Mage::log('PDF Invoice block constructed', null, 'pdf_debug.log');
    }

    public function setDocument($invoice)
    {
        $this->_invoice = $invoice;
        $this->_order = $invoice->getOrder();
        return $this;
    }

    public function setOrder($order)
    {
        $this->_order = $order;
        return $this;
    }

    public function getInvoice()
    {
        return $this->_invoice;
    }

    public function getOrder()
    {
        return $this->_order;
    }

    public function getInvoiceNumber()
    {
        return $this->_invoice ? $this->_invoice->getIncrementId() : '';
    }

    public function getInvoiceDate()
    {
        if ($this->_invoice) {
            return Mage::helper('core')->formatDate($this->_invoice->getCreatedAt(), 'medium', false);
        }
        return '';
    }

    public function getOrderNumber()
    {
        if ($this->_order && Mage::getStoreConfigFlag(Mage_Sales_Model_Order_Pdf_Abstract::XML_PATH_SALES_PDF_INVOICE_PUT_ORDER_ID, $this->_order->getStoreId())) {
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
        if ($this->_invoice) {
            foreach ($this->_invoice->getAllItems() as $item) {
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
        $renderer->setSource($this->getInvoice());

        return $renderer->toHtml();
    }

    protected function _getItemRenderer($type)
    {
        $rendererModel = Mage::getStoreConfig('sales_pdf/invoice/' . $type) ?: 'sales/order_pdf_items_invoice_default';
        if (!isset($this->_renderers[$type])) {
            $this->_renderers[$type] = new Mage_Sales_Model_Order_Pdf_Items_Invoice_Default();
        }
        return $this->_renderers[$type];
    }

    protected $_renderers = [];

    public function getTotals()
    {
        if (!$this->_invoice) {
            return [];
        }

        $totals = [];

        // Subtotal
        $totals[] = [
            'label' => $this->__('Subtotal'),
            'value' => $this->formatPrice($this->_invoice->getSubtotal()),
        ];

        // Discount
        if (abs($this->_invoice->getDiscountAmount()) > 0.01) {
            $totals[] = [
                'label' => $this->__('Discount'),
                'value' => $this->formatPrice(-$this->_invoice->getDiscountAmount()),
            ];
        }

        // Shipping
        if (!$this->_order->getIsVirtual()) {
            $totals[] = [
                'label' => $this->__('Shipping & Handling'),
                'value' => $this->formatPrice($this->_invoice->getShippingAmount()),
            ];
        }

        // Tax
        if (abs($this->_invoice->getTaxAmount()) > 0.01) {
            $totals[] = [
                'label' => $this->__('Tax'),
                'value' => $this->formatPrice($this->_invoice->getTaxAmount()),
            ];
        }

        // Grand Total
        $totals[] = [
            'label' => $this->__('Grand Total'),
            'value' => $this->formatPrice($this->_invoice->getGrandTotal()),
            'strong' => true,
        ];

        return $totals;
    }

    public function formatPrice($price)
    {
        return $this->_order->formatPriceTxt($price);
    }

}
