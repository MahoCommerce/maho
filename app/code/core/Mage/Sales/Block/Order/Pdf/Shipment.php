<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Order_Pdf_Shipment extends Mage_Core_Block_Template
{
    protected $_shipment;
    protected $_order;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/shipment/default.phtml');
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
        if ($this->_order && Mage::getStoreConfigFlag(Mage_Sales_Model_Order_Pdf_Abstract::XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID, $this->_order->getStoreId())) {
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

    public function getShippingMethod()
    {
        return $this->_order ? $this->_order->getShippingDescription() : '';
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
        if ($this->_shipment) {
            foreach ($this->_shipment->getAllItems() as $item) {
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
        $renderer->setSource($this->getShipment());

        return $renderer->toHtml();
    }

    protected function _getItemRenderer($type)
    {
        $rendererModel = Mage::getStoreConfig('sales_pdf/shipment/' . $type) ?: 'sales/order_pdf_items_shipment_default';
        if (!isset($this->_renderers[$type])) {
            $this->_renderers[$type] = new Mage_Sales_Model_Order_Pdf_Items_Shipment_Default();
        }
        return $this->_renderers[$type];
    }

    protected $_renderers = [];

    public function getTracking()
    {
        $tracks = [];
        if ($this->_shipment) {
            foreach ($this->_shipment->getAllTracks() as $track) {
                $tracks[] = [
                    'title' => $track->getTitle(),
                    'number' => $track->getNumber(),
                ];
            }
        }
        return $tracks;
    }

}
