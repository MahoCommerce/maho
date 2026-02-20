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

class Mage_Adminhtml_Block_Checkout_Agreement_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'checkout_agreement';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('checkout')->__('Save Condition'));
        $this->_updateButton('delete', 'label', Mage::helper('checkout')->__('Delete Condition'));
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('checkout_agreement')->getId()) {
            return Mage::helper('checkout')->__('Edit Terms and Conditions');
        }
        return Mage::helper('checkout')->__('New Terms and Conditions');
    }
}
