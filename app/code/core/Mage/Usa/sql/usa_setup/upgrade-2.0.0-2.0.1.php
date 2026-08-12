<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$configTable = $installer->getTable('core/config_data');

// The SOAP web service credentials have no REST equivalent: the meter number is gone
// entirely and the key/password pair is replaced by an OAuth2 client id/secret. Drop
// the rows so no stale encrypted SOAP secret lingers in the database.
$connection->delete($configTable, [
    'path IN (?)' => [
        'carriers/fedex/meter_number',
        'carriers/fedex/key',
        'carriers/fedex/password',
    ],
]);

// REST collapses the five SOAP DropoffType values into three pickupType values.
$dropoffMap = [
    'REGULAR_PICKUP'          => 'USE_SCHEDULED_PICKUP',
    'REQUEST_COURIER'         => 'CONTACT_FEDEX_TO_SCHEDULE',
    'DROP_BOX'                => 'DROPOFF_AT_FEDEX_LOCATION',
    'BUSINESS_SERVICE_CENTER' => 'DROPOFF_AT_FEDEX_LOCATION',
    'STATION'                 => 'DROPOFF_AT_FEDEX_LOCATION',
];

foreach ($dropoffMap as $legacy => $rest) {
    $connection->update(
        $configTable,
        ['value' => $rest],
        ['path = ?' => 'carriers/fedex/dropoff', 'value = ?' => $legacy],
    );
}

// REST renamed INTERNATIONAL_PRIORITY to FEDEX_INTERNATIONAL_PRIORITY and added a distinct
// FEDEX_INTERNATIONAL_PRIORITY_EXPRESS service. Rewriting the stored codes keeps the two
// apart; aliasing them at read time would collapse two differently-priced services into one.
$select = $connection->select()
    ->from($configTable, ['config_id', 'path', 'value'])
    ->where('path IN (?)', ['carriers/fedex/allowed_methods', 'carriers/fedex/free_method']);

foreach ($connection->fetchAll($select) as $row) {
    $codes = explode(',', (string) $row['value']);
    $updated = false;
    foreach ($codes as &$code) {
        if (trim($code) === 'INTERNATIONAL_PRIORITY') {
            $code = 'FEDEX_INTERNATIONAL_PRIORITY';
            $updated = true;
        }
    }
    unset($code);

    if ($updated) {
        $connection->update(
            $configTable,
            ['value' => implode(',', $codes)],
            ['config_id = ?' => $row['config_id']],
        );
    }
}

$installer->endSetup();
