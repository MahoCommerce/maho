<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$guestCheckout       = 'checkout/options/guest_checkout';
$redirectRegister    = 'checkout/options/redirect_register';
$customerMustBeLogged = 'checkout/options/customer_must_be_logged';

$defaultConfigValue = false;
foreach (Mage::app()->getWebsites(true) as $website) {
    $configValue = !$website->getConfig($guestCheckout) && $website->getConfig($customerMustBeLogged);
    if ($website->getId() === 0) {
        $defaultConfigValue = $configValue;
        $installer->setConfigData($redirectRegister, (string) (int) $configValue);
    } elseif ($configValue !== $defaultConfigValue) {
        $installer->setConfigData($redirectRegister, (string) (int) $configValue, 'websites', $website->getId());
    }
}

$installer->endSetup();
