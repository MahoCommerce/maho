<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml sales create order product search grid giftmessage column renderer
 *
 * @package    Mage_Adminhtml
 * @deprecated after 1.4.2.0 - gift column has been removed from search grid
 */
class Mage_Adminhtml_Block_Sales_Order_Create_Search_Grid_Renderer_Giftmessage extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Checkbox
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        /** @var Mage_GiftMessage_Helper_Message $helper */
        $helper = $this->helper('giftmessage/message');
        if (!$helper->getIsMessagesAvailable($helper::TYPE_ORDER_ITEM, $row, $this->getColumn()->getGrid()->getStore())) {
            return '&nbsp;';
        }

        return parent::render($row);
    }
}
