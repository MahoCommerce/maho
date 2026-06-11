<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

/**
 * Repairs paypal_setup for shops that reached 6.0.0 without the schema.
 *
 * The 26.5.0 bridge upgrade only matched when the recorded paypal_setup version
 * was <= 1.6.0.0 (the resolver requires the file's "from" >= the DB version), yet
 * legacy Mage_Paypal shipped up to 1.6.0.6. Those merchants had paypal_setup
 * bumped straight to 6.0.0 with no tables created, so the webhook/vault tables
 * and the paypal_order_id columns are missing and /checkout/onepage/ 500s.
 *
 * A 6.0.0 -> 6.0.1 upgrade is the only file that can reach them: its "from" of
 * 6.0.0 is >= both the broken 6.0.0 state and any un-migrated 1.6.0.x version.
 * install-6.0.1.php is fully idempotent, so it is a no-op on healthy shops.
 */
require __DIR__ . '/install-6.0.1.php';
