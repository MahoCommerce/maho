<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Maho\ApiPlatform\Kernel;
use Maho\Config\ApiResource as MahoApiResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * SelfResolvingWriteResourceMetadataCollectionFactory coverage: a resource
 * declaring `mahoSelfResolvingWrites: true` (Cart) gets `read: false` on its
 * item-scoped HTTP writes and GraphQL mutations without repeating the flag
 * per operation, while creates and unflagged resources keep the default.
 */

final class SelfResolvingWritesKernel extends Kernel
{
    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                $id = ResourceMetadataCollectionFactoryInterface::class;
                if ($container->hasAlias($id)) {
                    $container->getAlias($id)->setPublic(true);
                } elseif ($container->hasDefinition($id)) {
                    $container->getDefinition($id)->setPublic(true);
                }
            }
        });
    }
}

/** @return MahoApiResource[] */
function selfResolvingWritesMetadata(string $resourceClass): array
{
    static $factory = null;
    if ($factory === null) {
        Mage::app();
        $kernel = new SelfResolvingWritesKernel('test', true);
        $kernel->boot();
        $factory = $kernel->getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);
    }

    $resources = [];
    foreach ($factory->create($resourceClass) as $resource) {
        if ($resource instanceof MahoApiResource) {
            $resources[] = $resource;
        }
    }
    return $resources;
}

describe('self-resolving write read defaults', function (): void {

    it('defaults read to false on Cart item-scoped HTTP writes', function (): void {
        $writes = 0;
        foreach (selfResolvingWritesMetadata(Mage\Checkout\Api\Cart::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if (!$operation instanceof HttpOperation
                    || !in_array($operation->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                    || !$operation->getUriVariables()
                ) {
                    continue;
                }
                $writes++;
                expect($operation->canRead())
                    ->toBeFalse("{$operation->getMethod()} {$operation->getUriTemplate()} should not run the provider read pass");
            }
        }

        expect($writes)->toBeGreaterThan(20);
    });

    it('defaults read to false on Cart mutations', function (): void {
        $mutations = 0;
        foreach (selfResolvingWritesMetadata(Mage\Checkout\Api\Cart::class) as $resource) {
            foreach ($resource->getGraphQlOperations() ?? [] as $operation) {
                if (!$operation instanceof Mutation) {
                    continue;
                }
                $mutations++;
                expect($operation->canRead())
                    ->toBeFalse("mutation {$operation->getName()} should not run the provider read pass");
            }
        }

        expect($mutations)->toBeGreaterThan(10);
    });

    it('leaves the Cart create operation on the default read handling', function (): void {
        $found = false;
        foreach (selfResolvingWritesMetadata(Mage\Checkout\Api\Cart::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if ($operation instanceof HttpOperation
                    && $operation->getMethod() === 'POST'
                    && $operation->getUriTemplate() === '/carts'
                ) {
                    $found = true;
                    expect($operation->canRead())->toBeNull();
                }
            }
        }

        expect($found)->toBeTrue();
    });

    it('leaves resources without the flag untouched', function (): void {
        $writes = 0;
        foreach (selfResolvingWritesMetadata(Mage\Sales\Api\Order::class) as $resource) {
            expect($resource->mahoSelfResolvingWrites)->toBeFalse();
            foreach ($resource->getOperations() ?? [] as $operation) {
                if (!$operation instanceof HttpOperation
                    || !in_array($operation->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                ) {
                    continue;
                }
                $writes++;
                expect($operation->canRead())->toBeNull();
            }
        }

        expect($writes)->toBeGreaterThan(0);
    });

});
