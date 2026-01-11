<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_View_Items_Renderer_Default extends Mage_Adminhtml_Block_Sales_Items_Abstract
{
    public function getItem()
    {
        return $this->_getData('item');
    }

    /**
     * Retrieve real html id for field
     *
     * @param string $id
     * @return string
     */
    public function getFieldId($id)
    {
        return $this->getFieldIdPrefix() . $id;
    }

    /**
     * Retrieve field html id prefix
     *
     * @return string
     */
    public function getFieldIdPrefix()
    {
        return 'order_item_' . $this->getItem()->getId() . '_';
    }

    /**
     * Indicate that block can display container
     *
     * @return bool
     * @throws Exception
     */
    public function canDisplayContainer()
    {
        return $this->getRequest()->getParam('reload') != 1;
    }

    /**
     * Retrieve block html id
     *
     * @return string
     */
    public function getHtmlId()
    {
        return substr($this->getFieldIdPrefix(), 0, -1);
    }

    /**
     * Indicates that block can display giftmessages form
     *
     * TODO set return type
     * @return bool
     */
    public function canDisplayGiftmessage()
    {
        if (!$this->isModuleOutputEnabled('Mage_GiftMessage')) {
            return false;
        }
        /** @var Mage_GiftMessage_Helper_Message $helper */
        $helper = $this->helper('giftmessage/message');
        return $helper->getIsMessagesAvailable(
            $helper::TYPE_ORDER_ITEM,
            $this->getItem(),
            $this->getItem()->getOrder()->getStoreId(),
        );
    }

    /**
     * Display susbtotal price including tax
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @return string
     */
    #[\Override]
    public function displaySubtotalInclTax($item)
    {
        /** @var Mage_Checkout_Helper_Data $helper */
        $helper = $this->helper('checkout');
        return $this->displayPrices(
            $helper->getBaseSubtotalInclTax($item),
            $helper->getSubtotalInclTax($item),
        );
    }

    /**
     * Display item price including tax
     *
     * @return string
     */
    #[\Override]
    public function displayPriceInclTax(\Maho\DataObject $item)
    {
        /** @var Mage_Checkout_Helper_Data $helper */
        $helper = $this->helper('checkout');
        return $this->displayPrices(
            $helper->getBasePriceInclTax($item),
            $helper->getPriceInclTax($item),
        );
    }
}
