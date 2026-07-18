<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_Method_StandardCheckout extends Maho_Paypal_Model_Method_Abstract
{
    protected $_code = Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT;

    protected $_formBlockType = 'paypal/checkout_standard_form';

    protected $_canUseInternal = false;
    protected $_canReviewPayment = false;
}
