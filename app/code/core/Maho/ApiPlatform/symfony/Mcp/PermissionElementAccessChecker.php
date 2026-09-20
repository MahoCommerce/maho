<?php

/**
 * Hides MCP tools the authenticated caller could never call.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Mcp;

use ApiPlatform\Mcp\Security\ElementAccessCheckerInterface;
use ApiPlatform\Metadata\Operation\Factory\OperationMetadataFactoryInterface;
use Maho\ApiPlatform\Security\OperationAccessChecker;

/**
 * Drops from `tools/list` and `resources/list` every element the current token would
 * be refused, so an agent doesn't burn turns discovering that. Not the security
 * boundary, {@see \Maho\ApiPlatform\State\McpDispatchProvider} is.
 *
 * Adds Maho's authentication and admin ACL gates in front of API Platform's own
 * checker, which evaluates the operation `security` expression and stays optimistic
 * when the verdict needs the entity loaded.
 */
final class PermissionElementAccessChecker implements ElementAccessCheckerInterface
{
    public function __construct(
        private readonly ElementAccessCheckerInterface $decorated,
        private readonly OperationMetadataFactoryInterface $operationMetadataFactory,
        private readonly OperationAccessChecker $accessChecker,
    ) {}

    #[\Override]
    public function isGranted(string $operationName): bool
    {
        $operation = $this->operationMetadataFactory->create($operationName);
        if ($operation === null) {
            // Registered by something other than the API Platform loader.
            return true;
        }

        if (OperationAccessChecker::isPublic($operation)) {
            return true;
        }

        if (!$this->accessChecker->isAuthenticated()) {
            return false;
        }

        $resourceClass = $operation->getClass();
        if ($resourceClass === null || $resourceClass === '') {
            return false;
        }

        try {
            $this->accessChecker->checkAdminAcl($resourceClass, $operation);
        } catch (\Throwable) {
            return false;
        }

        return $this->decorated->isGranted($operationName);
    }
}
