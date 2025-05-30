<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Invoice_Create_Tracking extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    public function _construct()
    {
        $this->setTemplate('sales/order/invoice/create/tracking.phtml');
    }

    /**
     * Prepares layout of block
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'add_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'   => Mage::helper('sales')->__('Add Tracking Number'),
                    'class'   => '',
                    'onclick' => 'trackingControl.add()',
                ]),
        );
        return $this;
    }

    /**
     * Retrieve shipment model instance
     *
     * @return Mage_Sales_Model_Order_Shipment
     */
    public function getShipment()
    {
        return Mage::registry('current_shipment');
    }

    /**
     * Retrieve shipment model instance
     *
     * @return Mage_Sales_Model_Order_Shipment
     */
    public function getInvoice()
    {
        return Mage::registry('current_invoice');
    }

    /**
     * Retrieve
     *
     * @return array
     */
    public function getCarriers()
    {
        $carriers = [];
        $carrierInstances = Mage::getSingleton('shipping/config')->getAllCarriers(
            $this->getInvoice()->getStoreId(),
        );
        $carriers['custom'] = Mage::helper('sales')->__('Custom Value');
        foreach ($carrierInstances as $code => $carrier) {
            if ($carrier->isTrackingAvailable()) {
                $carriers[$code] = $carrier->getConfigData('title');
            }
        }
        return $carriers;
    }
}
