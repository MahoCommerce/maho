<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// customer_entity.is_active is created by the declarative schema but had no eav_attribute row, so
// Mage_Eav_Model_Entity_Abstract::_collectSaveData() dropped it and the flag could never be changed
// through the customer model. Deliberately no boolean backend (unlike twofa_enabled): that backend
// rewrites the value on every save, which would reset this default-1 column to 0 for every save
// that does not carry the flag.
$installer->addAttribute('customer', 'is_active', [
    'type'     => 'static',
    'label'    => 'Account Enabled',
    'input'    => 'boolean',
    'visible'  => false,
    'required' => false,
]);

$installer->endSetup();
