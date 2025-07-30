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
    protected ?Mage_Sales_Model_Order_Shipment $_shipment = null;
    protected ?Mage_Sales_Model_Order $_order = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/shipment/default.phtml');
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
        if ($this->_order && Mage::getStoreConfigFlag(Mage_Sales_Model_Order_Pdf_Abstract::XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID, $this->_order->getStoreId())) {
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

    public function getShippingMethod(): string
    {
        return $this->_order ? $this->_order->getShippingDescription() : '';
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
        return Mage::getStoreConfig('sales/identity/address', $this->getStore());
    }

    public function getItems(): array
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

    public function getItemHtml(Mage_Sales_Model_Order_Shipment_Item $item): string
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

    protected function _getItemRenderer(string $type): ?Mage_Sales_Model_Order_Pdf_Items_Abstract
    {
        $rendererModel = Mage::getStoreConfig('sales_pdf/shipment/' . $type) ?: 'sales/order_pdf_items_shipment_default';
        if (!isset($this->_renderers[$type])) {
            $this->_renderers[$type] = new Mage_Sales_Model_Order_Pdf_Items_Shipment_Default();
        }
        return $this->_renderers[$type];
    }

    protected array $_renderers = [];

    public function getTracking(): array
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
