<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Maho\ApiPlatform\Kernel;
use Maho\Config\ApiResource as MahoApiResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Row-scoping coverage guard for customer-scoped API resources (issue #1212).
 *
 * Ownership of a row is enforced declaratively: every item read operation a
 * plain customer can reach on a `mahoCustomerScoped` resource must carry an
 * `is_owner(object, '<property>')` clause in its security expression. The
 * clause defers evaluation to after the provider has loaded the row, and the
 * denial is shaped like a missing row (REST/MCP: the operation's
 * `AccessDeniedException => 404` mapping; GraphQL: OwnershipDenialProvider
 * converts it to a null result). This test makes the pattern mandatory: a new
 * customer-reachable item read without the clause, the 404 mapping, or a
 * matching DTO property fails the build.
 */

final class CustomerScopeKernel extends Kernel
{
    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                $expose = [
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

function customerScopeContainer(): \Psr\Container\ContainerInterface
{
    static $container = null;
    if ($container === null) {
        Mage::app();
        $kernel = new CustomerScopeKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
    }
    return $container;
}

/**
 * Every read ITEM operation on a mahoCustomerScoped resource that a plain
 * customer can reach, i.e. whose security expression is not the public 'true'.
 * Collections are excluded by design: they are identity-scoped at the query
 * (post-read filtering of a paginator would corrupt totalItems).
 *
 * @return list<array{label: string, resourceClass: class-string, operation: object, security: string}>
 */
function customerScopeItemReads(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }

    $container = customerScopeContainer();
    /** @var ResourceNameCollectionFactoryInterface $nameFactory */
    $nameFactory = $container->get(ResourceNameCollectionFactoryInterface::class);
    /** @var ResourceMetadataCollectionFactoryInterface $metadataFactory */
    $metadataFactory = $container->get(ResourceMetadataCollectionFactoryInterface::class);

    $rows = [];
    foreach ($nameFactory->create() as $resourceClass) {
        if (str_starts_with($resourceClass, 'ApiPlatform\\')) {
            continue;
        }

        foreach ($metadataFactory->create($resourceClass) as $metadata) {
            if (!$metadata instanceof MahoApiResource || !$metadata->mahoCustomerScoped) {
                continue;
            }
            $shortName = $metadata->getShortName() ?? $resourceClass;

            // Cart is exempt as a whole: its item reads resolve guest carts by
            // masked id and are ownership-gated inside
            // CartService::verifyCartAccess() (shared with the write
            // processors), which already answers 404 for foreign rows.
            if ($shortName === 'Cart') {
                continue;
            }

            $operations = [];
            foreach ($metadata->getOperations() ?? [] as $name => $operation) {
                if ($operation instanceof Get) {
                    $operations["REST {$shortName}::{$name}"] = $operation;
                }
            }
            foreach ($metadata->getGraphQlOperations() ?? [] as $name => $operation) {
                if ($operation instanceof Query && !$operation instanceof QueryCollection) {
                    $operations["GraphQL {$shortName}::{$name}"] = $operation;
                }
            }

            foreach ($operations as $label => $operation) {
                $security = trim((string) $operation->getSecurity());

                // 'true' marks the guest/public surface (masked ids, one-time
                // tokens, public reviews) - another party's row is the contract.
                if ($security === '' || $security === 'true') {
                    continue;
                }

                // Admin/service-locked operations have no customer to scope to.
                if (!str_contains($security, 'ROLE_CUSTOMER') && !str_contains($security, 'is_owner')) {
                    continue;
                }

                // Newsletter status reads accept no identifier: the subscriber
                // is derived from the JWT inside the provider, so there is no
                // id to probe and nothing for is_owner to compare.
                if (in_array($operation->getName(), ['status', 'status_rest'], true)) {
                    continue;
                }

                $rows[] = [
                    'label' => $label,
                    'resourceClass' => $resourceClass,
                    'operation' => $operation,
                    'security' => $security,
                ];
            }
        }
    }

    return $rows;
}

it('finds the customer-reachable item reads (vacuous-pass guard)', function (): void {
    // Order (REST+GraphQL), Address (2 REST + GraphQL), WishlistItem (GraphQL),
    // RevocationRequest (REST+GraphQL) = 8 today. Growth is fine; silence is not.
    expect(count(customerScopeItemReads()))->toBeGreaterThanOrEqual(8);
});

it('carries an is_owner clause on every customer-reachable item read', function (): void {
    $missing = [];
    foreach (customerScopeItemReads() as $row) {
        if (!preg_match("/is_owner\\(object,\\s*'[A-Za-z0-9_]+'\\)/", $row['security'])) {
            $missing[] = "{$row['label']} -> {$row['security']}";
        }
    }

    expect($missing)->toBe(
        [],
        'Customer-reachable item reads without a row-ownership clause (any customer can read any row): '
        . implode(', ', $missing),
    );
});

it('maps the ownership denial to 404 on every REST item read carrying the clause', function (): void {
    // The security stage throws ApiPlatform's AccessDeniedException subclass;
    // the mapping must catch it (ApiExceptionListener matches with is_a()).
    $thrownClass = \ApiPlatform\Symfony\Security\Exception\AccessDeniedException::class;

    $unmapped = [];
    foreach (customerScopeItemReads() as $row) {
        $operation = $row['operation'];
        if (!$operation instanceof HttpOperation || !str_contains($row['security'], 'is_owner')) {
            continue;
        }

        $mapsTo404 = false;
        foreach ($operation->getExceptionToStatus() ?? [] as $class => $status) {
            if ($status === 404 && is_a($thrownClass, $class, true)) {
                $mapsTo404 = true;
                break;
            }
        }
        if (!$mapsTo404) {
            $unmapped[] = $row['label'];
        }
    }

    expect($unmapped)->toBe(
        [],
        'REST item reads whose ownership denial answers 403 instead of 404 (id oracle): ' . implode(', ', $unmapped),
    );
});

it('names an existing DTO property in every is_owner clause', function (): void {
    $broken = [];
    foreach (customerScopeItemReads() as $row) {
        if (!preg_match("/is_owner\\(object,\\s*'([A-Za-z0-9_]+)'\\)/", $row['security'], $m)) {
            continue;
        }
        $property = $m[1];
        if (!(new ReflectionClass($row['resourceClass']))->hasProperty($property)) {
            $broken[] = "{$row['label']} -> {$property}";
        }
    }

    expect($broken)->toBe(
        [],
        'is_owner clauses naming a property the DTO does not have (is_owner fails closed, the owner is locked out too): '
        . implode(', ', $broken),
    );
});
