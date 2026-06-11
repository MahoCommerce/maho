<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Customer_Edit_Tab_Carts extends Mage_Adminhtml_Block_Template
{
    /**
     * Add shopping cart grid of each website
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $sharedWebsiteIds = Mage::registry('current_customer')->getSharedWebsiteIds();
        $isShared = count($sharedWebsiteIds) > 1;
        foreach ($sharedWebsiteIds as $websiteId) {
            $blockName = 'customer_cart_' . $websiteId;
            $block = $this->getLayout()->createBlock(
                'adminhtml/customer_edit_tab_cart',
                $blockName,
                ['website_id' => $websiteId],
            );
            if ($isShared) {
                $block->setCartHeader($this->__('Shopping Cart from %s', Mage::app()->getWebsite($websiteId)->getName()));
            }
            $this->setChild($blockName, $block);
        }
        return parent::_prepareLayout();
    }

    /**
     * Just get child blocks html
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('adminhtml_block_html_before', ['block' => $this]);
        return $this->getChildHtml();
    }
}
