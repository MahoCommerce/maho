<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Order_Pdf_Shipment_Packaging extends Mage_Sales_Block_Order_Pdf_Abstract
{
    protected ?Mage_Sales_Model_Order_Shipment $_shipment = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/shipment/packaging.phtml');
    }

    public function setDocument(Mage_Sales_Model_Order_Shipment $shipment): self
    {
        $this->_shipment = $shipment;
        $this->_order = $shipment->getOrder();
        return $this;
    }

    public function setOrder(Mage_Sales_Model_Order $order): self
    {
        $this->_order = $order;
        return $this;
    }

    public function getShipment(): ?Mage_Sales_Model_Order_Shipment
    {
        return $this->_shipment;
    }

    public function getOrder(): ?Mage_Sales_Model_Order
    {
        return $this->_order;
    }

    public function getShipmentNumber(): string
    {
        return $this->_shipment ? $this->_shipment->getIncrementId() : '';
    }

    public function getShipmentDate(): string
    {
        if ($this->_shipment) {
            return Mage::helper('core')->formatDate($this->_shipment->getCreatedAt(), 'medium', false);
        }
        return '';
    }

    public function getOrderNumber(): string
    {
        return $this->_order ? $this->_order->getRealOrderId() : '';
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

    public function getShippingMethod(): string
    {
        return $this->_order ? $this->_order->getShippingDescription() : '';
    }

    public function getPackages(): array
    {
        $packages = [];
        if ($this->_shipment) {
            $packaging = Mage::getBlockSingleton('adminhtml/sales_order_shipment_packaging');
            if ($packaging) {
                $packages = $packaging->getPackages();
            }
        }
        return $packages;
    }

    public function getPackageHtml(array $package): string
    {
        $packageObj = new \Maho\DataObject($package);
        $html = '<div class="package-details">';

        // Package type
        if ($packageObj->getPackageType()) {
            $html .= '<div class="package-type">' . $this->escapeHtml($packageObj->getPackageType()) . '</div>';
        }

        // Package weight
        if ($packageObj->getWeight()) {
            $html .= '<div class="package-weight">' . $this->__('Weight: %s', $packageObj->getWeight()) . '</div>';
        }

        // Package dimensions
        if ($packageObj->getLength() || $packageObj->getWidth() || $packageObj->getHeight()) {
            $dimensions = [];
            if ($packageObj->getLength()) {
                $dimensions[] = $packageObj->getLength();
            }
            if ($packageObj->getWidth()) {
                $dimensions[] = $packageObj->getWidth();
            }
            if ($packageObj->getHeight()) {
                $dimensions[] = $packageObj->getHeight();
            }

            if (!empty($dimensions)) {
                $html .= '<div class="package-dimensions">' . $this->__('Dimensions: %s', implode(' x ', $dimensions)) . '</div>';
            }
        }

        // Package value
        if ($packageObj->getValue()) {
            $html .= '<div class="package-value">' . $this->__('Value: %s', $this->formatPrice($packageObj->getValue())) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    #[\Override]
    public function formatPrice(float $price, ?string $currency = null): string
    {
        return $this->_order ? $this->_order->formatPriceTxt($price) : Mage::helper('core')->currency($price);
    }
}
