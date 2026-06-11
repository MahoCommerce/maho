<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Giftmessage extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    /**
     * Generate form for editing of gift message for entity
     *
     * @param string        $entityType
     * @return string
     */
    public function getFormHtml(\Maho\DataObject $entity, $entityType = 'quote')
    {
        return $this->getLayout()->createBlock(
            'adminhtml/sales_order_create_giftmessage_form',
        )->setEntity($entity)->setEntityType($entityType)->toHtml();
    }

    /**
     * Retrieve items allowed for gift messages.
     *
     * If no items available return false.
     *
     * @return array|bool
     */
    public function getItems()
    {
        if (!$this->isModuleOutputEnabled('Mage_GiftMessage')) {
            return false;
        }

        /** @var Mage_GiftMessage_Helper_Message $helper */
        $helper = $this->helper('giftmessage/message');

        $items = [];
        $allItems = $this->getQuote()->getAllItems();

        foreach ($allItems as $item) {
            if ($this->_getGiftmessageSaveModel()->getIsAllowedQuoteItem($item)
                && $helper->getIsMessagesAvailable($helper::TYPE_ITEM, $item, $this->getStore())
            ) {
                // if item allowed
                $items[] = $item;
            }
        }

        if (count($items)) {
            return $items;
        }

        return false;
    }

    /**
     * Retrieve gift message save model
     *
     * @return Mage_Adminhtml_Model_Giftmessage_Save
     */
    protected function _getGiftmessageSaveModel()
    {
        return Mage::getSingleton('adminhtml/giftmessage_save');
    }

    public function canDisplayGiftmessage(): bool
    {
        if (!$this->isModuleOutputEnabled('Mage_GiftMessage')) {
            return false;
        }
        /** @var Mage_GiftMessage_Helper_Message $helper */
        $helper = $this->helper('giftmessage/message');
        return $helper->getIsMessagesAvailable($helper::TYPE_CONFIG, $this->getQuote(), $this->getStoreId());
    }
}
