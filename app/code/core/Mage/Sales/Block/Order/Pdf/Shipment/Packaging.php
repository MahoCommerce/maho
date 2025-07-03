<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Order_Pdf_Shipment_Packaging extends Mage_Core_Block_Template
{
    protected $_shipment;
    protected $_order;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/shipment/packaging.phtml');
    }

    public function setDocument($shipment)
    {
        $this->_shipment = $shipment;
        $this->_order = $shipment->getOrder();
        return $this;
    }

    public function setOrder($order)
    {
        $this->_order = $order;
        return $this;
    }

    public function getShipment()
    {
        return $this->_shipment;
    }

    public function getOrder()
    {
        return $this->_order;
    }

    public function getShipmentNumber()
    {
        return $this->_shipment ? $this->_shipment->getIncrementId() : '';
    }

    public function getShipmentDate()
    {
        if ($this->_shipment) {
            return Mage::helper('core')->formatDate($this->_shipment->getCreatedAt(), 'medium', false);
        }
        return '';
    }

    public function getOrderNumber()
    {
        return $this->_order ? $this->_order->getRealOrderId() : '';
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

    public function getShippingMethod()
    {
        return $this->_order ? $this->_order->getShippingDescription() : '';
    }

    public function getLogoUrl()
    {
        $logoFile = Mage::getStoreConfig('sales/identity/logo', $this->getStore());
        if ($logoFile) {
            $logoPath = Mage::getBaseDir('media') . DS . 'sales' . DS . 'store' . DS . 'logo' . DS . $logoFile;
            if (file_exists($logoPath) && is_readable($logoPath)) {
                try {
                    // Check file size (limit to 2MB for PDF performance)
                    $fileSize = filesize($logoPath);
                    if ($fileSize > 2 * 1024 * 1024) {
                        Mage::log('Logo file too large for PDF: ' . $logoPath . ' (' . $fileSize . ' bytes)', Zend_Log::WARN);
                        return null;
                    }

                    $imageData = file_get_contents($logoPath);
                    if ($imageData === false) {
                        return null;
                    }

                    $mimeType = mime_content_type($logoPath);

                    // Validate image type for PDF compatibility
                    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
                        Mage::log('Unsupported logo format for PDF: ' . $mimeType, Zend_Log::WARN);
                        return null;
                    }

                    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                } catch (Exception $e) {
                    Mage::logException($e);
                    return null;
                }
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

    public function getPackages()
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

    public function getPackageHtml($package)
    {
        $packageObj = new Varien_Object($package);
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

    public function formatPrice($price)
    {
        return $this->_order ? $this->_order->formatPriceTxt($price) : Mage::helper('core')->currency($price);
    }
}
