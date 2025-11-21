<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer Magic Link Registration Mode Source Model
 *
 * @package Mage_Customer
 */
class Mage_Customer_Model_Config_Registrationmode
{
    public const MODE_REQUIRE_PASSWORD = 'require_password';
    public const MODE_NO_PASSWORD = 'no_password';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::MODE_REQUIRE_PASSWORD,
                'label' => Mage::helper('customer')->__('Require Password (Default)'),
            ],
            [
                'value' => self::MODE_NO_PASSWORD,
                'label' => Mage::helper('customer')->__('No Password (Full Passwordless)'),
            ],
        ];
    }

    public function toArray(): array
    {
        return [
            self::MODE_REQUIRE_PASSWORD => Mage::helper('customer')->__('Require Password (Default)'),
            self::MODE_NO_PASSWORD      => Mage::helper('customer')->__('No Password (Full Passwordless)'),
        ];
    }
}
