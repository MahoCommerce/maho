<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

/**
 * Migrate `main`-tracker config from the pre-#877 maho_paypal/... namespace.
 * No-op for fresh installs and for case-3 legacy Mage_Paypal merchants since
 * neither has any maho_paypal/... rows.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->update(
    $installer->getTable('core/config_data'),
    ['path' => new Maho\Db\Expr("REPLACE(path, 'maho_paypal/', 'paypal/')")],
    ['path LIKE ?' => 'maho_paypal/%'],
);

$installer->endSetup();
