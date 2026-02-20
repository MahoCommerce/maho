<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Order_Pdf_Invoice extends Mage_Sales_Block_Order_Pdf_Abstract
{
    protected ?Mage_Sales_Model_Order_Invoice $_invoice = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/invoice/default.phtml');
    }

    public function setDocument(Mage_Sales_Model_Order_Invoice $invoice): self
    {
        $this->_invoice = $invoice;
        $this->_order = $invoice->getOrder();
        return $this;
    }

    public function setOrder(Mage_Sales_Model_Order $order): self
    {
        $this->_order = $order;
        return $this;
    }

    public function getInvoice(): ?Mage_Sales_Model_Order_Invoice
    {
        return $this->_invoice;
    }

    public function getOrder(): ?Mage_Sales_Model_Order
    {
        return $this->_order;
    }

    public function getInvoiceNumber(): string
    {
        return $this->_invoice ? $this->_invoice->getIncrementId() : '';
    }

    public function getInvoiceDate(): string
    {
        if ($this->_invoice) {
            return Mage::helper('core')->formatDate($this->_invoice->getCreatedAt(), 'medium', false);
        }
        return '';
    }

    public function getOrderNumber(): string
    {
        if ($this->_order) {
            return $this->_order->getRealOrderId();
        }
        return '';
    }

    public function getOrderDate(): string
    {
        if ($this->_order) {
            return Mage::helper('core')->formatDate($this->_order->getCreatedAtStoreDate(), 'medium', false);
        }
        return '';
    }

    public function getBillingAddress(): ?Mage_Sales_Model_Order_Address
    {
        return $this->_order ? $this->_order->getBillingAddress() : null;
    }

    public function getShippingAddress(): ?Mage_Sales_Model_Order_Address
    {
        return $this->_order ? $this->_order->getShippingAddress() : null;
    }

    public function getPaymentInfo(): string
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

    public function getItems(): array
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

    public function getItemHtml(Mage_Sales_Model_Order_Invoice_Item $item): string
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

    protected function _getItemRenderer(string $type): ?Mage_Sales_Model_Order_Pdf_Items_Abstract
    {
        $rendererModel = Mage::getStoreConfig('sales_pdf/invoice/' . $type) ?: 'sales/order_pdf_items_invoice_default';
        if (!isset($this->_renderers[$type])) {
            $this->_renderers[$type] = new Mage_Sales_Model_Order_Pdf_Items_Invoice_Default();
        }
        return $this->_renderers[$type];
    }

    protected array $_renderers = [];

    public function getTotals(): array
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

        // Gift Card
        if (abs((float) $this->_invoice->getGiftcardAmount()) >= 0.01) {
            $label = $this->__('Gift Card');
            $giftcardCodes = $this->_order->getGiftcardCodes();
            if ($giftcardCodes) {
                $codesArray = json_decode($giftcardCodes, true);
                if (is_array($codesArray) && $codesArray !== []) {
                    $label .= ' (' . implode(', ', array_keys($codesArray)) . ')';
                }
            }
            $totals[] = [
                'label' => $label,
                'value' => $this->formatPrice(-abs($this->_invoice->getGiftcardAmount())),
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

    #[\Override]
    public function formatPrice(float $price, ?string $currency = null): string
    {
        return $this->_order->formatPriceTxt($price);
    }
}
