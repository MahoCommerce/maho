<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// The "Add Secret Key to URLs" toggle is gone: admin urls always carry the secret key now,
// so any stored override (including a 0 that used to disable it) is dead configuration.
$installer->deleteConfigData('admin/security/use_form_key');

$installer->endSetup();
