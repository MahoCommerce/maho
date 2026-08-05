<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * The CurrencyConverterAPI service is gone: its free servers stopped serving data and the paid
 * tier is untestable. A store still pointing at it would be left with an import service that no
 * longer resolves, so move it to Frankfurter (same EUR-based rates, no key) and enable that so
 * it stays selectable in the admin.
 */
$connection = $installer->getConnection();
$table = $installer->getTable('core/config_data');

$usesRemovedService = (bool) $connection->fetchOne(
    $connection->select()
        ->from($table, ['config_id'])
        ->where('path = ?', 'currency/import/service')
        ->where('value = ?', 'currencyconverterapi')
        ->limit(1),
);

if ($usesRemovedService) {
    $connection->update(
        $table,
        ['value' => 'frankfurter'],
        ['path = ?' => 'currency/import/service', 'value = ?' => 'currencyconverterapi'],
    );
    $installer->setConfigData('currency/frankfurter/active', '1');
}

$installer->deleteConfigData('currency/currencyconverterapi/active');
$installer->deleteConfigData('currency/currencyconverterapi/timeout');
$installer->deleteConfigData('currency/currencyconverterapi/api_key');

$installer->endSetup();
