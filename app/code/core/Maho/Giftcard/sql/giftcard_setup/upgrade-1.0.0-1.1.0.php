<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$giftcardTable = $installer->getTable('giftcard/giftcard');
$junctionTable = $installer->getTable('giftcard/website');

// Move the single-website association into the junction, then drop the scalar
// column the declarative pass left behind. Guarded to stay idempotent.
if ($connection->tableColumnExists($giftcardTable, 'website_id')) {
    $connection->query(
        "INSERT INTO {$junctionTable} (giftcard_id, website_id)
         SELECT g.giftcard_id, g.website_id
         FROM {$giftcardTable} g
         LEFT JOIN {$junctionTable} gw
           ON gw.giftcard_id = g.giftcard_id AND gw.website_id = g.website_id
         WHERE g.website_id > 0 AND gw.giftcard_id IS NULL",
    );

    $connection->dropColumn($giftcardTable, 'website_id');
}

$installer->endSetup();
