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

// The twofa_enabled / twofa_secret columns are created by the declarative schema;
// register them as static attributes so the EAV resource persists them on save.
$installer->addAttribute('customer', 'twofa_enabled', [
    'type'     => 'static',
    'label'    => 'Two-Factor Authentication Enabled',
    'input'    => 'boolean',
    'backend'  => 'customer/attribute_backend_data_boolean',
    'visible'  => false,
    'required' => false,
]);

$installer->addAttribute('customer', 'twofa_secret', [
    'type'     => 'static',
    'label'    => 'Two-Factor Authentication Secret',
    'input'    => 'text',
    'visible'  => false,
    'required' => false,
]);

$installer->endSetup();
