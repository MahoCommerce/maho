<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\GraphQl;

use ApiPlatform\GraphQl\Resolver\QueryItemResolverInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\ProviderInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Psr\Container\ContainerInterface;

/**
 * Custom GraphQL Query Resolver
 *
 * API Platform's ReadProvider requires an `id` argument for non-collection queries.
 * Custom queries like `me`, `customerCart`, `storeConfig` etc. don't have an `id` arg,
 * so ReadProvider returns null without calling our state providers.
 *
 * This resolver is called AFTER ReadProvider (by ResolverProvider) and directly
 * invokes the resource's state provider when ReadProvider couldn't resolve the item.
 *
 * Context available: source, args, info (ResolveInfo), root_class, graphql_context.
 * Note: `operation` is NOT in the resolver context — we reconstruct it from resource metadata.
 *
 * GraphQL field names follow the pattern: lcfirst(operationName + resourceShortName)
 * e.g., operation `me` on resource `Customer` → field `meCustomer`
 */
final class CustomQueryResolver implements QueryItemResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $providerLocator,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {}

    #[\Override]
    public function __invoke(?object $item, array $context): object
    {
        // If ReadProvider already resolved the item, return it
        if ($item !== null) {
            return $item;
        }

        $resourceClass = $context['root_class'] ?? null;
        $info = $context['info'] ?? null;
        $fieldName = $info instanceof ResolveInfo ? $info->fieldName : null;

        if (!$resourceClass || !$fieldName) {
            throw new \RuntimeException(sprintf(
                'CustomQueryResolver: missing root_class (%s) or info.fieldName (%s) in context',
                $resourceClass ?? 'null',
                $fieldName ?? 'null',
            ));
        }

        // Find the operation by matching the GraphQL field name against computed field names.
        // API Platform generates field names as: lcfirst(operationName + resourceShortName)
        $operation = null;
        $providerClass = null;
        $resourceMetadata = $this->resourceMetadataCollectionFactory->create($resourceClass);

        foreach ($resourceMetadata as $resource) {
            $shortName = $resource->getShortName() ?? '';
            $providerClass ??= $resource->getProvider();

            foreach ($resource->getGraphQlOperations() ?? [] as $op) {
                $expectedFieldName = lcfirst($op->getName() . $shortName);
                if ($expectedFieldName === $fieldName) {
                    $operation = $op;
                    $providerClass = $op->getProvider() ?? $providerClass;
                    break 2;
                }
            }
        }

        if (!$operation || !$providerClass) {
            throw new \RuntimeException(sprintf(
                'No operation/provider found for field "%s" on resource "%s"',
                $fieldName,
                $resourceClass,
            ));
        }

        // Resolve the provider from the tagged service locator
        $provider = $this->providerLocator->get($providerClass);
        if (!$provider instanceof ProviderInterface) {
            throw new \RuntimeException(sprintf(
                'Provider "%s" does not implement ProviderInterface',
                $providerClass,
            ));
        }

        // Call the provider directly with the resolved operation
        $result = $provider->provide($operation, [], $context);

        // QueryItemResolverInterface requires returning an object
        if ($result === null) {
            return new $resourceClass();
        }

        return $result;
    }
}
