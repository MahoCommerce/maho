<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\GraphQl;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Security\OperationAccessChecker;

/**
 * Apply the admin ACL of OperationAccessChecker to each GraphQL operation, before the read.
 *
 * AdminAclListener checks a REST request through its `_api_resource_class` attribute. The
 * GraphQL entry point has no such attribute, and one request runs many operations, so this
 * provider checks each operation that the resolver runs, nested resource fields included.
 *
 * @implements ProviderInterface<object>
 */
final class AdminAclProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $inner
     */
    public function __construct(
        private readonly ProviderInterface $inner,
        private readonly OperationAccessChecker $accessChecker,
    ) {}

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();
        if (is_string($resourceClass) && $resourceClass !== '') {
            $this->accessChecker->checkAdminAcl($resourceClass, $operation);
        }

        return $this->inner->provide($operation, $uriVariables, $context);
    }
}
