<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

class Mage_Checkout_Block_Cart_Coupon extends Mage_Checkout_Block_Cart_Abstract
{
    /**
     * @return string
     */
    public function getCouponCode()
    {
        return $this->getQuote()->getCouponCode();
    }

    /**
     * Return "discount" form action url
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('checkout/cart/couponPost', ['_secure' => $this->_isSecure()]);
    }
}
