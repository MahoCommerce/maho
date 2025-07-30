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
    protected ?Mage_Sales_Model_Order_Creditmemo $_creditmemo = null;
    protected ?Mage_Sales_Model_Order $_order = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/creditmemo/default.phtml');
    }

    public function setDocument(Mage_Sales_Model_Order_Creditmemo $creditmemo): self
    {
        $this->_creditmemo = $creditmemo;
        $this->_order = $creditmemo->getOrder();
        return $this;
    }

    public function setOrder(Mage_Sales_Model_Order $order): self
    {
        $this->_order = $order;
        return $this;
    }

    public function getCreditmemo(): ?Mage_Sales_Model_Order_Creditmemo
    {
        return $this->_creditmemo;
    }

    public function getOrder(): ?Mage_Sales_Model_Order
    {
        return $this->_order;
    }

    public function getCreditmemoNumber(): string
    {
        return $this->_creditmemo ? $this->_creditmemo->getIncrementId() : '';
    }

    public function getCreditmemoDate(): string
    {
        if ($this->_creditmemo) {
            return Mage::helper('core')->formatDate($this->_creditmemo->getCreatedAt(), 'medium', false);
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

    public function getLogoUrl(): ?string
    {
        // First, try the PDF-specific logo
        $logoFile = Mage::getStoreConfig('sales/identity/logo', $this->getStore());
        if ($logoFile) {
            $logoPath = Mage::getBaseDir('media') . DS . 'sales' . DS . 'store' . DS . 'logo' . DS . $logoFile;
            if (file_exists($logoPath) && is_readable($logoPath)) {
                return 'file://' . $logoPath;
            }
        }

        // Fallback to the main store logo
        $storeLogo = Mage::getStoreConfig('design/header/logo_src', $this->getStore());
        if ($storeLogo) {
            // Get actual file path for skin logo
            $designPackage = Mage::getDesign()->getPackageName();
            $theme = Mage::getDesign()->getTheme('frontend');
            $logoPath = Mage::getBaseDir() . DS . 'public' . DS . 'skin' . DS . 'frontend' . DS . $designPackage . DS . $theme . DS . $storeLogo;

            if (file_exists($logoPath) && is_readable($logoPath)) {
                return 'file://' . $logoPath;
            }
        }

        return null;
    }

    public function getStore(): Mage_Core_Model_Store
    {
        return $this->_order ? $this->_order->getStore() : Mage::app()->getStore();
    }

    public function getStoreAddress(): string
    {
        return (string) Mage::getStoreConfig('sales/identity/address', $this->getStore());
    }

    public function getItems(): array
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

    public function getItemHtml(Mage_Sales_Model_Order_Creditmemo_Item $item): string
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

    protected function _getItemRenderer(string $type): ?Mage_Sales_Model_Order_Pdf_Items_Abstract
    {
        $rendererModel = Mage::getStoreConfig('sales_pdf/creditmemo/' . $type) ?: 'sales/order_pdf_items_creditmemo_default';
        if (!isset($this->_renderers[$type])) {
            $this->_renderers[$type] = new Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Default();
        }
        return $this->_renderers[$type];
    }

    protected array $_renderers = [];

    public function getTotals(): array
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

    public function formatPrice(float $price): string
    {
        return $this->_order->formatPriceTxt($price);
    }

}
