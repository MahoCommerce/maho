<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
