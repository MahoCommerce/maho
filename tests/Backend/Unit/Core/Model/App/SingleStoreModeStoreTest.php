<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('gives getStore(true) a real store after a store read during the setup scripts', function () {
    $app = Mage::app();
    $store = new ReflectionProperty($app, '_store');
    $singleStore = new ReflectionProperty($app, '_isSingleStore');

    // A request with a cold configuration cache runs the setup scripts before the stores load
    $store->setValue($app, null);
    $app->setUpdateMode(true);
    try {
        $app->getStore();
    } finally {
        $app->setUpdateMode(false);
    }

    try {
        $app->reinitStores();
        $singleStore->setValue($app, true);

        $current = $app->getStore(true);

        expect($app->getStores())->toContain($current)
            ->and($current->getWebsiteId())->not->toBe(0);
    } finally {
        $app->reinitStores();
    }
});
