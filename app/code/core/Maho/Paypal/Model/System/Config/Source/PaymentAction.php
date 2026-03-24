<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_System_Config_Source_PaymentAction
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Maho_Paypal_Model_Config::PAYMENT_ACTION_AUTHORIZE,
                'label' => Mage::helper('maho_paypal')->__('Authorize Only'),
            ],
            [
                'value' => Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE,
                'label' => Mage::helper('maho_paypal')->__('Authorize and Capture'),
            ],
        ];
    }
}
