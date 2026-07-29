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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operation\Factory\OperationMetadataFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\Symfony\Security\ObjectVariableCheckerInterface;
use Maho\ApiPlatform\Security\OperationAccessChecker;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Drops from `tools/list` every tool the current token would be refused, so an agent
 * doesn't burn turns discovering that. Not the security boundary,
 * {@see \Maho\ApiPlatform\State\McpDispatchProvider} is.
 *
 * Optimistic on purpose: a tool stays listed when the verdict needs the entity
 * loaded, e.g. a security expression inspecting `object`.
 *
 * @implements RequestHandlerInterface<ListToolsResult>
 */
final class PermissionFilteredListHandler implements RequestHandlerInterface
{
    /**
     * @param RequestHandlerInterface<mixed> $decorated
     */
    public function __construct(
        private readonly RequestHandlerInterface $decorated,
        private readonly OperationMetadataFactoryInterface $operationMetadataFactory,
        private readonly OperationAccessChecker $accessChecker,
        private readonly ResourceAccessCheckerInterface $resourceAccessChecker,
        private readonly RequestStack $requestStack,
    ) {}

    #[\Override]
    public function supports(Request $request): bool
    {
        return $this->decorated->supports($request);
    }

    #[\Override]
    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        $response = $this->decorated->handle($request, $session);

        if (!$response instanceof Response || !$response->result instanceof ListToolsResult) {
            return $response;
        }

        $allowed = array_filter(
            $response->result->tools,
            fn(Tool $tool): bool => $this->isCallable($tool->name),
        );

        if (\count($allowed) === \count($response->result->tools)) {
            return $response;
        }

        return new Response($response->id, new ListToolsResult($allowed, $response->result->nextCursor));
    }

    private function isCallable(string $toolName): bool
    {
        $operation = $this->operationMetadataFactory->create($toolName);
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

        return $this->isGranted($resourceClass, $operation);
    }

    private function isGranted(string $resourceClass, Operation $operation): bool
    {
        $security = $operation->getSecurity();
        if ($security === null) {
            return true;
        }
        $security = (string) $security;

        $variables = ['object' => null, 'previous_object' => null];
        if (($request = $this->requestStack->getCurrentRequest()) !== null) {
            $variables['request'] = $request;
        }

        if (
            $this->resourceAccessChecker instanceof ObjectVariableCheckerInterface
            && $this->resourceAccessChecker->usesObjectVariable($security, $variables)
        ) {
            return true;
        }

        try {
            return $this->resourceAccessChecker->isGranted($resourceClass, $security, $variables);
        } catch (\Throwable) {
            return true;
        }
    }
}
