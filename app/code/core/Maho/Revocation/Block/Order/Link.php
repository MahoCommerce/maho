<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

/**
 * My-account convenience entry point on the order view page. Pre-fills the public
 * form for the session-owned order; only shown within the cooling-off window.
 */
class Maho_Revocation_Block_Order_Link extends Mage_Core_Block_Template
{
    public function getOrder(): ?Mage_Sales_Model_Order
    {
        $order = Mage::registry('current_order');
        return $order instanceof Mage_Sales_Model_Order ? $order : null;
    }

    public function getRevocationUrl(): string
    {
        return Mage::getUrl('revocation/index/index', ['_query' => ['order_id' => (int) $this->getOrder()?->getId()]]);
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $helper = Mage::helper('revocation');
        $order = $this->getOrder();
        if (!$order || !$helper->isEnabled() || !$helper->isOrderWithinCoolingOffWindow($order)) {
            return '';
        }
        return parent::_toHtml();
    }
}
