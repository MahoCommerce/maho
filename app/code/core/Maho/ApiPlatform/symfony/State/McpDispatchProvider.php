<?php

/**
 * Adapts an MCP tool call to the API Platform state pipeline.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;
use Maho\ApiPlatform\Security\OperationAccessChecker;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Outermost decorator of `api_platform.state_provider.main`, so everything below
 * it, the read, the deserialization and the `security:` expression, runs against
 * the real operation and with the caller already vetted.
 *
 * Three jobs, all of them translations between MCP's one-POST-many-operations
 * shape and a pipeline built for one-request-one-operation:
 *
 * - **Gate the caller.** `AdminAclListener` and `DefaultDenyListener` key off the
 *   `_api_resource_class` request attribute, which `/api/mcp` never sets, so both
 *   skip every tool call. Without this an admin token would reach every tool
 *   regardless of its Maho role, and a resource declaring no `security:` would
 *   lose its default-deny.
 * - **Restore the operation.** See {@see SourceOperationResolver}.
 * - **Place the arguments.** REST carries them in the query string (filters and
 *   pagination) or the request body (the entity); MCP carries both in
 *   `params.arguments`. Route them to the same two places.
 *
 * Non-MCP traffic returns on the first line untouched.
 *
 * @implements ProviderInterface<object>
 */
final class McpDispatchProvider implements ProviderInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH'];

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly SourceOperationResolver $operationResolver,
        private readonly OperationAccessChecker $accessChecker,
        private readonly DenormalizerInterface $serializer,
        private readonly SerializerContextBuilderInterface $serializerContextBuilder,
    ) {}

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!isset($context['mcp_request'])) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $this->enforce($operation);
        $operation = $this->operationResolver->resolve($operation);

        /** @var array<string, mixed> $arguments */
        $arguments = $context['mcp_data'] ?? [];

        if ($operation instanceof CollectionOperationInterface && $arguments !== []) {
            $context['filters'] = $arguments;
        }

        $data = $this->decorated->provide($operation, $uriVariables, $context);

        if (!$operation instanceof HttpOperation || !in_array(strtoupper($operation->getMethod()), self::WRITE_METHODS, true)) {
            return $data;
        }

        return $this->denormalizeBody($operation, $uriVariables, $context, $arguments, $data);
    }

    private function enforce(Operation $operation): void
    {
        $resourceClass = $operation->getClass();
        if (!is_string($resourceClass) || $resourceClass === '') {
            throw new AccessDeniedHttpException('This tool is not bound to a resource class.');
        }

        if (!OperationAccessChecker::isPublic($operation) && !$this->accessChecker->isAuthenticated()) {
            throw new InsufficientAuthenticationException(
                'Authentication required: send a Maho API bearer token with the MCP request.',
            );
        }

        $this->accessChecker->checkAdminAcl($resourceClass, $operation);
    }

    /**
     * Build the write payload the way `DeserializeProvider` would, minus the HTTP
     * body: same serializer, same context builder, so groups, name conversion and
     * `OBJECT_TO_POPULATE` behave exactly as they do over REST. `$data` is the
     * entity the read stage loaded, present for PUT/PATCH and null for POST.
     *
     * @param array<string, mixed> $arguments
     */
    private function denormalizeBody(
        HttpOperation $operation,
        array $uriVariables,
        array $context,
        array $arguments,
        object|array|null $data,
    ): object|array|null {
        $resourceClass = $operation->getClass();
        if ($resourceClass === null) {
            return $data;
        }

        $request = $context['request'] ?? null;
        $serializerContext = $request !== null
            ? $this->serializerContextBuilder->createFromRequest($request, false, [
                'resource_class' => $resourceClass,
                'operation' => $operation,
            ])
            : ($operation->getDenormalizationContext() ?? []);

        $serializerContext['uri_variables'] = $uriVariables;
        if ($data !== null) {
            $serializerContext[AbstractNormalizer::OBJECT_TO_POPULATE] = $data;
        }

        return $this->serializer->denormalize(
            $arguments,
            $operation->getInput()['class'] ?? $resourceClass,
            'json',
            $serializerContext,
        );
    }
}
