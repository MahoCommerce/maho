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
 * The 2.0.0 seed of directory_country_name carried CLDR codes that directory_country never
 * had (AC, AN, CP, DG, EA, EU, IC, QO, TA, XK, ZZ). Setup runs with foreign key checks off,
 * so the rows slipped past the constraint; a PostgreSQL dump cannot be restored while they
 * exist, and the storefront never lists them.
 */
$connection = $installer->getConnection();
$nameTable = $installer->getTable('directory/country_name');
$countryTable = $installer->getTable('directory/country');

$known = $connection->fetchCol($connection->select()->from($countryTable, ['country_id']));
if ($known !== []) {
    $connection->delete($nameTable, ['country_id NOT IN (?)' => $known]);
}

$installer->endSetup();
