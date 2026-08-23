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
use Symfony\Component\HttpFoundation\Request;
use Maho\ApiPlatform\EventListener\McpAuthChallengeListener;
use Maho\ApiPlatform\Mcp\OperationRequestFactory;
use Maho\ApiPlatform\Mcp\SourceOperationResolver;
use Maho\ApiPlatform\Security\OperationAccessChecker;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Outermost decorator of `api_platform.state_provider.main`, so the read, the
 * deserialization and the `security:` expression all see a vetted caller and the
 * real operation. Three translations from MCP's one-POST-many-operations shape:
 *
 * - **Gate the caller.** `AdminAclListener` and `DefaultDenyListener` key off the
 *   `_api_resource_class` request attribute, which `/api/mcp` never sets, so both
 *   skip every tool call.
 * - **Restore the operation.** See {@see SourceOperationResolver}.
 * - **Place the arguments.** REST splits them between query string and body; MCP
 *   sends both in `params.arguments`.
 * - **Give the operation its own URI.** Providers read plain path parameters off
 *   `$request->getPathInfo()`, because API Platform only populates `uriVariables`
 *   declared as `Link` objects. Under MCP every path is `/api/mcp`, so those reads
 *   come back empty.
 *
 * @implements ProviderInterface<object>
 */
final class McpDispatchProvider implements ProviderInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH'];

    /** Attributes the read stage records on the request; the MCP handler reads them back. */
    private const CARRIED_ATTRIBUTES = ['data', 'read_data', 'previous_data', 'mapped_data'];

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly SourceOperationResolver $operationResolver,
        private readonly OperationRequestFactory $requestFactory,
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

        $this->enforce($operation, $context['request'] ?? null);
        $operation = $this->operationResolver->resolve($operation);

        /** @var array<string, mixed> $arguments */
        $arguments = $context['mcp_data'] ?? [];

        if ($arguments !== []) {
            // Both keys: providers read request input from one, the other, or the merge
            // of the two, depending on whether they were written with REST or GraphQL in
            // mind. Nothing branches on which key is present.
            $context['args'] = $arguments;
            if ($operation instanceof CollectionOperationInterface) {
                $context['filters'] = $arguments;
            }
        }

        $original = $context['request'] ?? null;
        $substituted = $operation instanceof HttpOperation && $original instanceof Request
            ? $this->requestFactory->create($operation, $uriVariables, $arguments, $original)
            : null;
        if ($substituted !== null) {
            $context['request'] = $substituted;
        }

        try {
            $data = $this->decorated->provide($operation, $uriVariables, $context);
        } catch (AccessDeniedException $e) {
            // MCP errors never reach kernel.exception (the MCP server answers
            // from its own request loop), so the operation's exceptionToStatus
            // mapping is honored here instead of in ApiExceptionListener. A
            // row-level denial mapped to 404 must be byte-identical to what
            // ReadProvider throws for a genuinely missing row.
            if ($operation instanceof HttpOperation) {
                foreach ($operation->getExceptionToStatus() ?? [] as $class => $status) {
                    if ($status === 404 && is_a($e::class, $class, true)) {
                        throw new NotFoundHttpException('Not Found', $e);
                    }
                }
            }

            throw $e;
        }

        if ($substituted !== null && $original instanceof Request) {
            // The read stage wrote these onto the request it was handed; the MCP handler
            // reads them off the one it holds, which is the original.
            foreach (self::CARRIED_ATTRIBUTES as $attribute) {
                $original->attributes->set($attribute, $substituted->attributes->get($attribute));
            }
        }

        if (!$operation instanceof HttpOperation || !in_array(strtoupper($operation->getMethod()), self::WRITE_METHODS, true)) {
            return $data;
        }

        return $this->denormalizeBody($operation, $uriVariables, $context, $arguments, $data);
    }

    private function enforce(Operation $operation, mixed $request): void
    {
        $resourceClass = $operation->getClass();
        if (!is_string($resourceClass) || $resourceClass === '') {
            throw new AccessDeniedHttpException('This tool is not bound to a resource class.');
        }

        if (!OperationAccessChecker::isPublic($operation) && !$this->accessChecker->isAuthenticated()) {
            // The MCP server answers from its own loop at HTTP 200, so the reply
            // would say "send a token" with nothing a client can act on.
            // McpAuthChallengeListener reads this and turns the reply into the
            // 401 challenge that starts discovery.
            if ($request instanceof Request) {
                $request->attributes->set(McpAuthChallengeListener::ATTRIBUTE_AUTH_REQUIRED, true);
            }

            throw new InsufficientAuthenticationException(
                'Authentication required: send a Maho API bearer token with the MCP request.',
            );
        }

        $this->accessChecker->checkAdminAcl($resourceClass, $operation);
    }

    /**
     * What `DeserializeProvider` does, minus the HTTP body: same serializer and
     * context builder, so groups and `OBJECT_TO_POPULATE` match REST. `$data` is the
     * entity the read stage loaded, null for a create.
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
            // Everything the URI carries is dropped: REST puts path parameters in the
            // path and only the rest in the body, and an identifier the resource types
            // differently (a guest cart's masked id against an int `id`) is mangled if
            // it reaches the denormalizer.
            array_diff_key($arguments, $this->requestFactory->uriVariableNames($operation)),
            $operation->getInput()['class'] ?? $resourceClass,
            'json',
            $serializerContext,
        );
    }
}
