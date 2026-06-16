<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

/**
 * Bridges the deleted Mage_Paypal `paypal_setup` resource (last shipped at 1.6.0.4)
 * to Maho_Paypal's 6.0.0. Mage builds upgrade chains by version range, so this
 * single file covers any merchant whose recorded paypal_setup version sits between
 * 1.6.0.0 and 6.0.0, i.e. every legacy Mage_Paypal install.
 */
require __DIR__ . '/data-install-6.0.0.php';
