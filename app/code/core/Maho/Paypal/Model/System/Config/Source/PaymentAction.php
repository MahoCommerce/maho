<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_System_Config_Source_PaymentAction
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Maho_Paypal_Model_Config::PAYMENT_ACTION_AUTHORIZE,
                'label' => Mage::helper('paypal')->__('Authorize Only'),
            ],
            [
                'value' => Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE,
                'label' => Mage::helper('paypal')->__('Authorize and Capture'),
            ],
        ];
    }
}
