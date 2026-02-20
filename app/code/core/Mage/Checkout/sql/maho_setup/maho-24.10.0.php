<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
