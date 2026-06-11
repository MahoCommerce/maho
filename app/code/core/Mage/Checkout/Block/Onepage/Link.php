<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Block_Onepage_Link extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        return $this->getUrl('checkout/onepage', ['_secure' => true]);
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        return !Mage::getSingleton('checkout/session')->getQuote()->validateMinimumAmount();
    }

    /**
     * @return bool
     */
    public function isPossibleOnepageCheckout()
    {
        /** @var Mage_Checkout_Helper_Data $helper */
        $helper = $this->helper('checkout');
        return $helper->canOnepageCheckout();
    }
}
