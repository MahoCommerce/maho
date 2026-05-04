<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Bridges the deleted Mage_Paypal `paypal_setup` resource (last shipped at 1.6.0.4)
 * to Maho_Paypal's 6.0.0. Mage builds upgrade chains by version range, so this
 * single file covers any merchant whose recorded paypal_setup version sits between
 * 1.6.0.0 and 6.0.0 — i.e. every legacy Mage_Paypal install.
 *
 * The install script is fully idempotent, so we just run it.
 */
require __DIR__ . '/install-6.0.0.php';
