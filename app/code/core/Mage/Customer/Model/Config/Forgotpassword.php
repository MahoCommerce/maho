<?php

/**
 * The ways to limit the requests for a password reset email.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Model_Config_Forgotpassword
{
    public const FLOW_DISABLED = 0;
    public const FLOW_IP_EMAIL = 1;
    public const FLOW_IP = 2;
    public const FLOW_EMAIL = 3;

    /**
     * @return list<array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('customer');
        return [
            ['value' => self::FLOW_DISABLED, 'label' => $helper->__('Disabled')],
            ['value' => self::FLOW_IP_EMAIL, 'label' => $helper->__('By IP and Email')],
            ['value' => self::FLOW_IP, 'label' => $helper->__('By IP')],
            ['value' => self::FLOW_EMAIL, 'label' => $helper->__('By Email')],
        ];
    }
}
