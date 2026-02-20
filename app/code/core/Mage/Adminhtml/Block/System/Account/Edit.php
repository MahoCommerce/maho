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

class Mage_Adminhtml_Block_System_Account_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_controller = 'system_account';
        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Account'));
        $this->_removeButton('delete');
        $this->_removeButton('back');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        return Mage::helper('adminhtml')->__('My Account');
    }
}
