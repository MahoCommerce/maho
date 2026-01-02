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

class Mage_Adminhtml_Block_Customer_Group_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_controller = 'customer_group';

        $this->_updateButton('save', 'label', Mage::helper('customer')->__('Save Customer Group'));
        $this->_updateButton('delete', 'label', Mage::helper('customer')->__('Delete Customer Group'));

        if (!Mage::registry('current_group')->getId() || Mage::registry('current_group')->usesAsDefault()) {
            $this->_removeButton('delete');
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    #[\Override]
    public function getDeleteUrl()
    {
        if (!Mage::getSingleton('adminhtml/url')->useSecretKey()) {
            return $this->getUrl('*/*/delete', [
                $this->_objectId => $this->getRequest()->getParam($this->_objectId),
                'form_key' => Mage::getSingleton('core/session')->getFormKey(),
            ]);
        }
        return parent::getDeleteUrl();
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (!is_null(Mage::registry('current_group')->getId())) {
            return Mage::helper('customer')->__('Edit Customer Group "%s"', $this->escapeHtml(Mage::registry('current_group')->getCustomerGroupCode()));
        }
        return Mage::helper('customer')->__('New Customer Group');
    }
}
