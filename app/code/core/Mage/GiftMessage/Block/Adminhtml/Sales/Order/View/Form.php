<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GiftMessage
 */

class Mage_GiftMessage_Block_Adminhtml_Sales_Order_View_Form extends Mage_Adminhtml_Block_Template
{
    /**
     * Indicates that block can display gift message form
     *
     * @return bool
     */
    public function canDisplayGiftmessageForm()
    {
        $order = Mage::registry('current_order');
        if ($order) {
            foreach ($order->getAllItems() as $item) {
                if ($item->getGiftMessageId()) {
                    return true;
                }
            }
        }
        return false;
    }
}
