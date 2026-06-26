<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Kernel;
use Maho\ApiPlatform\Security\ApiPermissionRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Authorization-coverage regression guard for the API platform.
 *
 * Boots the real API kernel in-process (no HTTP server) and asserts that every
 * GraphQL operation the schema exposes resolves to a concrete permission, or is
 * explicitly public. This locks shut the "the field name isn't in the permission
 * map -> no permission required -> any API key reads it" fail-open class, which
 * previously let a key scoped to products/read read every order via GraphQL.
 *
 * The explicit cases use real schema field names so they also catch API Platform
 * changing its field-naming under us; the sweep catches anything new.
 */

/**
 * Test kernel that exposes the (otherwise private) permission registry via a
 * compiler pass, so the assertions below can enumerate every operation through
 * the fully-wired service. The production container is untouched.
 */
final class AuthzCoverageKernel extends Kernel
{
    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        // Preserve the Maho kernel's module-service/resource registration, then
        // expose the registry so the assertions can reach the wired instance.
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition(ApiPermissionRegistry::class)) {
                    $container->getDefinition(ApiPermissionRegistry::class)->setPublic(true);
                }
            }
        });
    }
}

function authzRegistry(): ApiPermissionRegistry
{
    static $registry = null;
    if ($registry === null) {
        Mage::app();
        $kernel = new AuthzCoverageKernel('test', true);
        $kernel->boot();
        $registry = $kernel->getContainer()->get(ApiPermissionRegistry::class);
    }
    return $registry;
}

dataset('sensitive graphql reads', [
    'orders collection'    => ['{ orders { id } }', ['orders/read']],
    'shipments collection' => ['{ shipments { id } }', ['shipments/read']],
    'credit memos'         => ['{ creditMemos { id } }', ['credit-memos/read']],
    'coupons collection'   => ['{ coupons { id } }', ['coupons/read']],
    'stock updates'        => ['{ stockUpdates { id } }', ['inventory/read']],
    'customer orders'      => ['{ customerOrdersOrders { id } }', ['orders/read']],
    'cart by id'           => ['{ cart(id: 1) { id } }', ['carts/read']],
]);

it('requires the matching permission for sensitive GraphQL reads', function (string $query, array $expected): void {
    expect(authzRegistry()->resolveGraphQlPermissions($query))->toEqual($expected);
})->with('sensitive graphql reads');

dataset('privileged graphql mutations', [
    'create coupon' => ['mutation { createCouponCoupon(input: {}) { coupon { id } } }', ['coupons/create']],
    'place order'   => ['mutation { placeOrderOrder(input: {}) { order { id } } }', ['orders/create']],
]);

it('requires a grantable permission for privileged GraphQL mutations', function (string $query, array $expected): void {
    // The permission must be one a role can actually be granted, otherwise the
    // mutation is gated behind a phantom permission and silently always denied.
    $registry = authzRegistry();
    $perms = $registry->resolveGraphQlPermissions($query);
    expect($perms)->toEqual($expected);
    foreach ($perms as $perm) {
        expect($registry->getPermissionIds())->toContain($perm);
    }
})->with('privileged graphql mutations');

it('leaves public catalog/reference GraphQL reads open', function (): void {
    expect(authzRegistry()->resolveGraphQlPermissions('{ products { id } }'))->toBe([]);
    expect(authzRegistry()->resolveGraphQlPermissions('{ countries { id } }'))->toBe([]);
});

it('never leaves a non-public GraphQL field without a permission (no fail-open)', function (): void {
    $registry = authzRegistry();

    // The registry's field index is built from API Platform's resolved metadata,
    // so it covers auto-generated default operations too — the place the original
    // fail-open hid.
    $method = new ReflectionMethod($registry, 'graphQlFieldIndex');
    /** @var array<string, array{resource: string, kind: string, name: string, public: bool}> $index */
    $index = $method->invoke($registry);
    expect($index)->not->toBeEmpty();

    $failOpen = [];
    foreach ($index as $field => $info) {
        if ($info['public']) {
            continue;
        }
        $query = $info['kind'] === 'read' ? "{ {$field} }" : "mutation { {$field} }";
        if ($registry->resolveGraphQlPermissions($query) === []) {
            $failOpen[] = $field;
        }
    }

    expect($failOpen)->toBe([], 'GraphQL fields reachable with no permission required: ' . implode(', ', $failOpen));
});

it('maps nested REST read paths to their own resource, not the parent', function (): void {
    $registry = authzRegistry();
    expect($registry->resolveRestResource('/api/rest/v2/orders'))->toBe('orders');
    expect($registry->resolveRestResource('/api/rest/v2/orders/5/shipments'))->toBe('shipments');
    expect($registry->resolveRestResource('/api/rest/v2/orders/5/invoices'))->toBe('invoices');
    expect($registry->resolveRestResource('/api/rest/v2/orders/5/credit-memos'))->toBe('credit-memos');
    expect($registry->resolveRestResource('/api/rest/v2/customers/5/addresses'))->toBe('addresses');
});
