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
$websiteTable = $installer->getTable('core/website');

// Backfill the new giftcard_website junction so every existing card stays
// usable everywhere it was valid before the 1.1.0 multi-website migration.
//
// Permissive backfill: each existing card is associated with every active
// website (website_id > 0 excludes the admin pseudo-website). This preserves
// the most common pre-1.1.0 behaviour where a single website assignment was
// made administratively but card validation was often effectively
// store-agnostic, and avoids stranding cards that were created before the
// install grew a second website.
//
// Operators who want to scope cards more tightly can edit the website
// multiselect on the card's admin page after the upgrade runs.
//
// Fresh installs have an empty `giftcard` table, so the LEFT JOIN/CROSS JOIN
// short-circuits to zero inserts — no special-case branching needed.
//
// LEFT JOIN ... WHERE NULL instead of INSERT IGNORE so the script is portable
// across MySQL, PostgreSQL and SQLite (the Maho portable-SQL rule); rerunning
// is then naturally idempotent because already-present pairs filter out.
$connection->query(
    "INSERT INTO {$junctionTable} (giftcard_id, website_id)
     SELECT g.giftcard_id, w.website_id
     FROM {$giftcardTable} g
     CROSS JOIN {$websiteTable} w
     LEFT JOIN {$junctionTable} gw
       ON gw.giftcard_id = g.giftcard_id AND gw.website_id = w.website_id
     WHERE w.website_id > 0 AND gw.giftcard_id IS NULL",
);

$installer->endSetup();
