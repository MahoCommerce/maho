<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */


const XML_PATH_GUEST_CHECKOUT          = 'checkout/options/guest_checkout';
const XML_PATH_REDIRECT_REGISTER       = 'checkout/options/redirect_register';
const XML_PATH_CUSTOMER_MUST_BE_LOGGED = 'checkout/options/customer_must_be_logged';

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;

$defaultConfigValue = false;
foreach (Mage::app()->getWebsites(true) as $website) {
    $configValue = !$website->getConfig(XML_PATH_GUEST_CHECKOUT) && $website->getConfig(XML_PATH_CUSTOMER_MUST_BE_LOGGED);
    if ($website->getId() === 0) {
        $defaultConfigValue = $configValue;
        $this->setConfigData(XML_PATH_REDIRECT_REGISTER, $configValue);
    } elseif ($configValue !== $defaultConfigValue) {
        $this->setConfigData(XML_PATH_REDIRECT_REGISTER, true, 'websites', $website->getId());
    }
}

$installer->endSetup();
