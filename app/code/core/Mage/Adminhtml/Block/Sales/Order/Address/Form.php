<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Address_Form extends Mage_Adminhtml_Block_Sales_Order_Create_Form_Address
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sales/order/address/form.phtml');
    }

    /**
     * Order address getter
     *
     * @return Mage_Sales_Model_Order_Address
     */
    protected function _getAddress()
    {
        return Mage::registry('order_address');
    }

    /**
     * Define form attributes (id, method, action)
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareForm()
    {
        parent::_prepareForm();
        $this->_form->setId('edit_form');
        $this->_form->setMethod('post');
        $this->_form->setAction($this->getUrl('*/*/addressSave', ['address_id' => $this->_getAddress()->getId()]));
        $this->_form->setUseContainer(true);
        return $this;
    }

    /**
     * Form header text getter
     *
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Order Address Information');
    }

    /**
     * Return Form Elements values
     *
     * @return array
     */
    #[\Override]
    public function getFormValues()
    {
        return $this->_getAddress()->getData();
    }
}
