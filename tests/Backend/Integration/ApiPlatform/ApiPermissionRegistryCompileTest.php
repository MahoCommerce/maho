<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Security\ApiPermissionRegistry;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * The admin role editor renders all three of its sections (public storefront,
 * customer session, service account tree) from ApiPermissionRegistry. When
 * vendor/composer/maho_api_permissions.php was missing, or had been compiled
 * before the API modules were scannable, the registry served an empty map and
 * the editor rendered three blank boxes with no explanation. The registry now
 * rebuilds the map in-process instead.
 */

function apiPermissionsCompiledPath(): string
{
    return BP . '/vendor/composer/maho_api_permissions.php';
}

function resetApiPermissionRegistry(): void
{
    (new ReflectionProperty(ApiPermissionRegistry::class, 'compiled'))->setValue(null, null);
    (new ReflectionProperty(ApiPermissionRegistry::class, 'compileAttempted'))->setValue(null, false);
}

/**
 * Run $test with the compiled permission map replaced by $replacement
 * (null deletes it), restoring the original file afterwards.
 */
function withCompiledPermissionMap(?string $replacement, Closure $test): void
{
    $path = apiPermissionsCompiledPath();
    $original = is_file($path) ? file_get_contents($path) : null;

    try {
        if ($replacement === null) {
            @unlink($path);
        } else {
            file_put_contents($path, $replacement);
        }
        resetApiPermissionRegistry();
        $test();
    } finally {
        if ($original === null) {
            @unlink($path);
        } else {
            file_put_contents($path, $original);
        }
        resetApiPermissionRegistry();
    }
}

describe('ApiPermissionRegistry compiled map', function () {
    it('exposes grantable permissions for the role editor', function () {
        $registry = new ApiPermissionRegistry();

        expect($registry->getResources())->not->toBeEmpty();
        expect($registry->getServicePermissionsBySection())->not->toBeEmpty();
        expect($registry->getPermissionIds())->toContain('products/write');
    });

    it('rebuilds the map when the compiled file is missing', function () {
        withCompiledPermissionMap(null, function () {
            $registry = new ApiPermissionRegistry();

            expect($registry->getResources())->not->toBeEmpty();
            expect(apiPermissionsCompiledPath())->toBeFile();
        });
    });

    it('rebuilds the map when the compiled file holds an empty registry', function () {
        $empty = "<?php\n\nreturn ['resources' => [], 'publicRead' => [], 'customerScoped' => []];\n";

        withCompiledPermissionMap($empty, function () {
            $registry = new ApiPermissionRegistry();

            expect($registry->getResources())->not->toBeEmpty();
        });
    });

    it('rebuilds at most once per process', function () {
        withCompiledPermissionMap(null, function () {
            (new ApiPermissionRegistry())->getResources();

            // A second miss (the file was removed again) must not re-scan the
            // whole class map on every request of a still-broken install.
            @unlink(apiPermissionsCompiledPath());
            (new ReflectionProperty(ApiPermissionRegistry::class, 'compiled'))->setValue(null, null);

            expect((new ApiPermissionRegistry())->getResources())->toBeEmpty();
        });
    });
});

describe('API role editor permissions tab', function () {
    it('builds a service permission tree with checkable leaf nodes', function () {
        Mage::unregister('api_role_permissions');
        Mage::register('api_role_permissions', ['products/write']);

        try {
            $block = Mage::app()->getLayout()
                ->createBlock('apiplatform/adminhtml_apiplatform_role_edit_tab_permissions');

            expect($block->hasServicePermissions())->toBeTrue();

            $tree = Mage::helper('core')->jsonDecode($block->getServiceTreeJson());
            expect($tree)->not->toBeEmpty();

            $leaves = [];
            array_walk_recursive($tree, function ($value, $key) use (&$leaves) {
                if ($key === 'id') {
                    $leaves[] = $value;
                }
            });
            expect($leaves)->toContain('products/write');
        } finally {
            Mage::unregister('api_role_permissions');
        }
    });
});
