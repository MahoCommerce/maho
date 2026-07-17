<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Messages extends Mage_Adminhtml_Block_Messages
{
    #[\Override]
    protected function _prepareLayout()
    {
        $this->addMessages(Mage::getSingleton('adminhtml/session_quote')->getMessages(true));
        return parent::_prepareLayout();
    }
}
