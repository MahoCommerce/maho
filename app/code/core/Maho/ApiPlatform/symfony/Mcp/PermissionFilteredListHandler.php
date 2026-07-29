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
 * Decorates `api_platform.mcp.list_handler` and drops from `tools/list` every
 * tool whose operation the current token is denied. Not a security boundary,
 * {@see \Maho\ApiPlatform\State\McpDispatchProvider} and the operation's own
 * `security:` expression are; this only stops an agent burning turns on tools it
 * will be refused.
 *
 * Filtering is deliberately optimistic. A tool stays in the list when the verdict
 * can't be reached without running the operation, in particular when the security
 * expression inspects `object` (customer-scoped resources compare the loaded
 * entity against the token), or when evaluating it throws.
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
