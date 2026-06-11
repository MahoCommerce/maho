<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

class Mage_Api2_Block_Adminhtml_Attribute_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Initialize edit form container
     */
    public function __construct()
    {
        $this->_objectId   = 'id';
        $this->_blockGroup = 'api2';
        $this->_controller = 'adminhtml_attribute';

        parent::__construct();

        $this->_updateButton('save', 'label', $this->__('Save'))
            ->_removeButton('delete');
    }

    /**
     * Retrieve text for header element depending on loaded page
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        $userTypes = Mage_Api2_Model_Auth_User::getUserTypes();
        return $this->__('Edit attribute rules for %s Role', $userTypes[$this->getRequest()->getParam('type')]);
    }
}
