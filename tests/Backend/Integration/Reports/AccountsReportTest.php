<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The new accounts report counts customers per period, so its rows have no entity ID. The EAV
 * collection once used that missing ID as an array key, and PHP 8.5 reports a null array offset
 * as deprecated, which stopped the report page in developer mode.
 */
it('loads the new accounts report without a PHP error', function () {
    $errors = [];
    set_error_handler(function (int $level, string $message) use (&$errors): bool {
        $errors[] = $message;
        return true;
    });
    try {
        $collection = Mage::getResourceModel('reports/accounts_collection')
            ->setDateRange('2000-01-01 00:00:00', Mage_Core_Model_Locale::nowUtc())
            ->setStoreIds([]);
        $rows = $collection->load()->getItems();
    } finally {
        restore_error_handler();
    }

    expect($errors)->toBe([])
        ->and($rows)->toHaveCount(1)
        ->and((int) reset($rows)->getAccounts())->toBe((int) Mage::getResourceModel('customer/customer_collection')->getSize());
});
