<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Maho\ApiPlatform\Kernel;
use Maho\ApiPlatform\Security\ApiPermissionRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Authorization-coverage regression guard for the API platform.
 *
 * Authorization is enforced entirely by each operation's own `security:`
 * expression: API Platform's access checker evaluates it for BOTH REST and
 * GraphQL and routes any `resource/operation` attribute to ApiUserVoter. There
 * is no separate permission listener or runtime field index anymore.
 *
 * That makes the `security:` expression the ONLY gate, so this test boots the
 * real API kernel in-process (no HTTP server), enumerates every operation the
 * metadata exposes, and asserts two invariants that together close the
 * fail-open / phantom-permission classes of bug:
 *
 *   1. Every operation declares a non-empty security expression. A null/empty
 *      one means the access checker never runs and the operation is open to any
 *      authenticated caller — the exact hole a key scoped to products/read could
 *      have used to read every order.
 *   2. Every `resource/operation` permission an expression references is a real,
 *      grantable permission id (present in ApiPermissionRegistry). A typo'd or
 *      phantom permission can never be granted, so the operation would be
 *      silently always-denied — a latent availability bug.
 */

/**
 * Test kernel that exposes API Platform's (private) metadata factories and the
 * permission registry via a compiler pass, so the assertions below can
 * enumerate every operation through the fully-wired services. The production
 * container is untouched.
 */
final class AuthzCoverageKernel extends Kernel
{
    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        // Preserve the Maho kernel's module-service/resource registration, then
        // expose the services the assertions need to reach.
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                $expose = [
                    ApiPermissionRegistry::class,
                    ResourceNameCollectionFactoryInterface::class,
                    ResourceMetadataCollectionFactoryInterface::class,
                ];
                foreach ($expose as $id) {
                    if ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);
                    } elseif ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        });
    }
}

/**
 * Boot the API kernel once and return its container.
 */
function authzContainer(): \Psr\Container\ContainerInterface
{
    static $container = null;
    if ($container === null) {
        Mage::app();
        $kernel = new AuthzCoverageKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
    }
    return $container;
}

/**
 * Enumerate every REST + GraphQL operation as
 * ['label' => string, 'security' => ?string] rows, read from API Platform's
 * fully-resolved metadata (so auto-generated operations are included too).
 *
 * @return list<array{label: string, security: ?string}>
 */
function authzOperations(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }

    $container = authzContainer();
    /** @var ResourceNameCollectionFactoryInterface $nameFactory */
    $nameFactory = $container->get(ResourceNameCollectionFactoryInterface::class);
    /** @var ResourceMetadataCollectionFactoryInterface $metadataFactory */
    $metadataFactory = $container->get(ResourceMetadataCollectionFactoryInterface::class);

    $rows = [];
    foreach ($nameFactory->create() as $resourceClass) {
        // Skip API Platform's own framework resources (Error, ConstraintViolation,
        // …): they model error/validation RESPONSE shapes, carry no application
        // data, and legitimately have no security. We audit Maho data resources.
        if (str_starts_with($resourceClass, 'ApiPlatform\\')) {
            continue;
        }

        foreach ($metadataFactory->create($resourceClass) as $metadata) {
            $shortName = $metadata->getShortName() ?? $resourceClass;

            foreach ($metadata->getOperations() ?? [] as $name => $operation) {
                $rows[] = [
                    'label' => "REST {$shortName}::{$name}",
                    'security' => $operation->getSecurity(),
                ];
            }

            foreach ($metadata->getGraphQlOperations() ?? [] as $name => $operation) {
                $rows[] = [
                    'label' => "GraphQL {$shortName}::{$name}",
                    'security' => $operation->getSecurity(),
                ];
            }
        }
    }

    return $rows;
}

/**
 * Extract every `resource/operation` permission referenced by an `is_granted()`
 * call in a security expression. Role attributes (ROLE_ADMIN, ROLE_CUSTOMER, …)
 * carry no slash and are ignored here — they are not registry permissions.
 *
 * @return list<string>
 */
function authzReferencedPermissions(string $security): array
{
    if (preg_match_all("/is_granted\\(\\s*['\"]([^'\"]+)['\"]/", $security, $matches) === false) {
        return [];
    }
    return array_values(array_filter($matches[1], static fn(string $attr): bool => str_contains($attr, '/')));
}

it('boots the kernel and enumerates a non-trivial set of operations', function (): void {
    // Guards against the sweep silently passing because it found nothing to check
    // (e.g. metadata factory wiring changed and returned an empty set).
    expect(count(authzOperations()))->toBeGreaterThan(100);
});

it('declares a security expression on every REST and GraphQL operation (no fail-open)', function (): void {
    $unsecured = [];
    foreach (authzOperations() as $op) {
        $security = $op['security'];
        if (!is_string($security) || trim($security) === '') {
            $unsecured[] = $op['label'];
        }
    }

    expect($unsecured)->toBe(
        [],
        'Operations with no security expression are open to any authenticated caller: ' . implode(', ', $unsecured),
    );
});

it('references only real, grantable permissions in every security expression (no phantom gates)', function (): void {
    $registry = authzContainer()->get(ApiPermissionRegistry::class);
    $valid = $registry->getPermissionIds();

    $phantom = [];
    foreach (authzOperations() as $op) {
        $security = $op['security'];
        if (!is_string($security)) {
            continue;
        }
        foreach (authzReferencedPermissions($security) as $permission) {
            if (!in_array($permission, $valid, true)) {
                $phantom[] = "{$op['label']} -> {$permission}";
            }
        }
    }

    expect($phantom)->toBe(
        [],
        'Security expressions referencing non-grantable permissions (never satisfiable): ' . implode(', ', $phantom),
    );
});
