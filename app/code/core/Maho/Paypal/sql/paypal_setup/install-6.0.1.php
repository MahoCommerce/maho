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
 * Fresh installs resolve to the highest install file <= the module version, so
 * they land here directly and record 6.0.1. That means no post-install
 * 6.0.0 -> 6.0.1 upgrade fires afterwards, and the schema body runs exactly once
 * (rather than install-6.0.0.php running again via upgrade-6.0.0-6.0.1.php).
 *
 * Existing shops are untouched by this file and keep repairing through
 * upgrade-6.0.0-6.0.1.php.
 */
require __DIR__ . '/install-6.0.0.php';
