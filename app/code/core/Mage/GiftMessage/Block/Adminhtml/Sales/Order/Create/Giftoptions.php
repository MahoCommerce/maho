<?php

/**
 * Maho
 *
 * @package    Mage_GiftMessage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml sales order create gift options block
 *
 * @package    Mage_GiftMessage
 */
class Mage_GiftMessage_Block_Adminhtml_Sales_Order_Create_Giftoptions extends Mage_Adminhtml_Block_Template
{
    /**
     * Get quote item object from parent block
     *
     * @return Mage_Sales_Model_Quote_Item
     */
    public function getItem()
    {
        return $this->getParentBlock()->getItem();
    }

    /**
     * Retrieve gift message for item
     */
    public function getMessageText(): string
    {
        if ($this->getItem()->getGiftMessageId()) {
            $model = $this->helper('giftmessage/message')
                ->getGiftMessage($this->getItem()->getGiftMessageId());

            return $this->escapeHtml($model->getMessage());
        }
        return '';
    }
}
