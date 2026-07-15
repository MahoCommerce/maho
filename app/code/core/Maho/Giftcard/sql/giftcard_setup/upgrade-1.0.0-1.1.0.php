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

// 1.0.0 → 1.1.0: move the single-website association into the giftcard_website
// junction, then retire the scalar. The declarative pass has already run at
// this point (it creates the junction and, because `website_id` is no longer
// declared, drops its index/FK while preserving the column and its data —
// additive merge), so this script owns the data move and the column drop.
//
// Conservative backfill — one row per card, its original website — preserves
// pre-1.1.0 behaviour exactly: validation was a strict website match, so a
// card stays spendable only where it was before. Operators can broaden a
// card's websites from its admin page after the upgrade.
//
// Guarded by column existence, so fresh installs (where the declarative
// schema never creates the column) skip the whole block, and a re-run after
// the drop is a no-op. The LEFT JOIN keeps the INSERT idempotent if the
// script is interrupted between the backfill and the drop.
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
