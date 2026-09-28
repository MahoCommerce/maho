<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// A coupon no longer copies the "To" date of its rule. Remove the copies, and keep an expiration set on the coupon itself.
$connection = $installer->getConnection();
$couponTable = $installer->getTable('salesrule/coupon');
$toDates = $connection->fetchPairs(
    $connection->select()
        ->from($installer->getTable('salesrule/rule'), ['rule_id', 'to_date'])
        ->where($connection->quoteIdentifier('to_date') . ' IS NOT NULL'),
);

foreach ($toDates as $ruleId => $toDate) {
    $date = substr((string) $toDate, 0, 10);
    $connection->update(
        $couponTable,
        ['expiration_date' => null],
        ['rule_id = ?' => (int) $ruleId, 'expiration_date = ?' => $date . ' 00:00:00'],
    );
}

$installer->endSetup();
