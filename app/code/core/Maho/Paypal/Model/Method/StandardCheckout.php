<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Method_StandardCheckout extends Maho_Paypal_Model_Method_Abstract
{
    protected $_code = Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT;

    protected $_formBlockType = 'paypal/checkout_standard_form';

    protected $_canUseInternal = false;
    protected $_canReviewPayment = false;
}
