<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/** @var Mage_Customer_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Add reset password link customer Id attribute
$installer->addAttribute('customer', 'rp_customer_id', [
    'type'     => 'varchar',
    'input'    => 'hidden',
    'visible'  => false,
    'required' => false,
]);

$installer->endSetup();
