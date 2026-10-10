<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * The assistant links a record to the admin page of the ACL resource of its API resource. When two
 * resources share an ACL resource, the page edits only one of them, so the other one must set
 * ADMIN_RECORD_PAGE to false. Otherwise an address links to the customer with the same ID.
 */
it('gives each admin ACL resource at most one API resource with a record page', function (): void {
    $groups = [];
    foreach (glob(Mage::getBaseDir() . '/app/code/core/*/*/Api/*.php') as $file) {
        $source = (string) file_get_contents($file);
        if (!preg_match('/const ADMIN_RESOURCE = ([^;]+);/', $source, $acl)
            || !preg_match("~uriTemplate: '/[a-z0-9-]+/\\{\\w+\\}'~", $source)
            || preg_match('/const ADMIN_RECORD_PAGE = false;/', $source)
        ) {
            continue;
        }
        $groups[trim($acl[1])][] = basename($file, '.php');
    }

    expect(count($groups))->toBeGreaterThan(20);
    expect(array_filter($groups, static fn(array $resources): bool => count($resources) > 1))->toBe([]);
});
