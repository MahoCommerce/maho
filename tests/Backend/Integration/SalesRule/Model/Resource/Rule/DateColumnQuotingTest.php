<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('SalesRule rule collection date filter quoting', function () {
    // from_date/to_date are reserved as date functions on newer MariaDB, so the
    // collection must emit them as quoted identifiers, not bare column references.
    test('quotes from_date/to_date and executes', function () {
        $collection = Mage::getResourceModel('salesrule/rule_collection');
        $collection->addWebsiteGroupDateFilter(1, 0);

        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = $collection->getSelect()->__toString();

        expect($sql)->toContain($read->quoteIdentifier('from_date'));
        expect($sql)->toContain($read->quoteIdentifier('to_date'));
        // No bare (unquoted) column reference must survive.
        expect($sql)->not->toContain('from_date IS NULL');
        expect($sql)->not->toContain('to_date IS NULL');

        // Assemble + run against the live database to prove the syntax parses.
        expect(fn() => $collection->getSize())->not->toThrow(Throwable::class);
    });
});
