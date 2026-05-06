<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\ComposerPlugin\AttributeCompiler;
use Maho\Routing\RouteCollectionBuilder;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin/install sentinel strings live in two repositories:
 *   - `Maho\ComposerPlugin\AttributeCompiler` (vendor plugin, writes the
 *     compiled `maho_attributes.php` / matcher / generator files)
 *   - `Maho\Routing\RouteCollectionBuilder` (runtime, reads them)
 *
 * If one drifts from the other, reverse-lookup silently returns null for admin
 * and install routes, breaking every admin URL in subtle ways (URLs fall back
 * to the legacy builder and often work by accident for simple routes). Pin the
 * values to the compiled artefact so drift is caught at test time.
 */
describe('Admin/install sentinels stay aligned between compiler and runtime', function () {
    it('runtime ADMIN_SENTINEL matches the compiler ADMIN_SENTINEL', function () {
        expect(RouteCollectionBuilder::ADMIN_SENTINEL)->toBe(AttributeCompiler::ADMIN_SENTINEL);
    });

    it('runtime INSTALL_SENTINEL matches the compiler INSTALL_SENTINEL', function () {
        expect(RouteCollectionBuilder::INSTALL_SENTINEL)->toBe(AttributeCompiler::INSTALL_SENTINEL);
    });

    it('compiled controllerLookup keys admin routes under ADMIN_SENTINEL', function () {
        // If the compiler emitted a different sentinel than the runtime expects,
        // none of the admin routes would be found in this map.
        $compiled = Maho::getCompiledAttributes();
        $prefix = RouteCollectionBuilder::ADMIN_SENTINEL . '/';
        $adminEntries = array_filter(
            array_keys($compiled['controllerLookup']),
            static fn(string $key): bool => str_starts_with($key, $prefix),
        );

        expect($adminEntries)->not->toBeEmpty();
    });

    it('compiled controllerLookup keys install routes under INSTALL_SENTINEL', function () {
        $compiled = Maho::getCompiledAttributes();
        $prefix = RouteCollectionBuilder::INSTALL_SENTINEL . '/';
        $installEntries = array_filter(
            array_keys($compiled['controllerLookup']),
            static fn(string $key): bool => str_starts_with($key, $prefix),
        );

        expect($installEntries)->not->toBeEmpty();
    });
});
