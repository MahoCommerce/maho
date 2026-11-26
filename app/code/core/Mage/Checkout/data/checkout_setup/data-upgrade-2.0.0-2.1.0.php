<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/** @var Mage_Checkout_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$versionTable = $installer->getTable('checkout/agreement_version');
$agreements = $connection->fetchAll(
    $connection->select()->from(
        $installer->getTable('checkout/agreement'),
        ['agreement_id', 'version', 'name', 'content', 'checkbox_text', 'is_html'],
    ),
);
foreach ($agreements as $agreement) {
    $exists = $connection->fetchOne(
        $connection->select()
            ->from($versionTable, ['version_id'])
            ->where('agreement_id = ?', $agreement['agreement_id'])
            ->where('version = ?', $agreement['version']),
    );
    if ($exists) {
        continue;
    }
    $connection->insert($versionTable, $agreement + ['created_at' => Mage::app()->getLocale()->formatDateForDb('now')]);
}

$installer->endSetup();
