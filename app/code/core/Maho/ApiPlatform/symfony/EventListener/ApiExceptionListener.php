<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use ApiPlatform\Metadata\HttpOperation;
use Maho\ApiPlatform\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException as SerializerUnexpectedValueException;

/**
 * API Exception Listener.
 *
 * Converts API exceptions to standardized JSON error responses.
 */
class ApiExceptionListener implements EventSubscriberInterface
{
    public function __construct(private bool $debug = false) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests (JSON or /api/ path)
        if (!$this->isApiRequest($request)) {
            return;
        }

        $response = $this->createErrorResponse($exception, $request);
        $event->setResponse($response);
    }

    private function isApiRequest(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        // Check if request expects JSON
        if ($request->getPreferredFormat() === 'json') {
            return true;
        }

        // Check if it's an API path
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/api/') || str_starts_with($path, '/rest.php/')) {
            return true;
        }

        // Check Accept header
        $accept = $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return false;
    }

    private function createErrorResponse(\Throwable $exception, ?\Symfony\Component\HttpFoundation\Request $request = null): JsonResponse
    {
        // Handle our custom API exceptions
        if ($exception instanceof ApiException) {
            $data = $exception->toArray();

            if ($this->showDebug() && $exception->getPrevious()) {
                $data['debug'] = [
                    'previous' => $exception->getPrevious()->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            return new JsonResponse($data, $exception->getHttpStatusCode());
        }

        // Handle Symfony Security exceptions - authentication required
        if ($exception instanceof AuthenticationException) {
            $statusCode = 401;
            $data = [
                'error' => 'unauthorized',
                'message' => 'Authentication required',
                'code' => $statusCode,
            ];

            if ($this->showDebug()) {
                $data['debug'] = [
                    'class' => $exception::class,
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            return new JsonResponse($data, $statusCode, ['WWW-Authenticate' => \Mage::helper('apiplatform')->getBearerChallenge()]);
        }

        // Handle Symfony Security exceptions - access denied (authenticated but not authorized)
        if ($exception instanceof AccessDeniedException) {
            // If user is not authenticated at all, return 401
            // Check for Bearer token specifically (Basic auth is site-level, not API auth)
            $hasBearerToken = $request !== null
                && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
            // Use the exception class to recognize "not authenticated" rather
            // than matching on the message string (Symfony has rephrased it
            // before; a security-component upgrade silently flips 401 ↔ 403).
            // For AccessDeniedException, Symfony chains the original
            // InsufficientAuthenticationException as the `previous`.
            $isNotAuthenticated = $exception->getPrevious() instanceof InsufficientAuthenticationException
                || !$hasBearerToken;

            // Honor the operation's exceptionToStatus mapping (e.g. row-level
            // ownership denials mapped to 404 so a foreign id is
            // indistinguishable from a missing one), but only for authenticated
            // callers: an unauthenticated caller must keep the 401 +
            // WWW-Authenticate affordance below. is_a() matching mirrors API
            // Platform's own ErrorListener; the security stage throws a subclass
            // of the mapped Symfony AccessDeniedException.
            if (!$isNotAuthenticated) {
                $operation = $request?->attributes->get('_api_operation');
                if ($operation instanceof HttpOperation) {
                    foreach ($operation->getExceptionToStatus() ?? [] as $class => $mappedStatus) {
                        if (is_a($exception::class, $class, true)) {
                            // A 404-mapped denial must match a missing row's body
                            // (ReadProvider's 'Not Found') or the masking leaks.
                            return new JsonResponse([
                                'error' => $this->getErrorCodeFromStatusCode($mappedStatus),
                                'message' => $mappedStatus === 404 ? 'Not Found' : $this->getDefaultMessageForStatusCode($mappedStatus),
                                'code' => $mappedStatus,
                            ], $mappedStatus);
                        }
                    }
                }
            }

            $statusCode = $isNotAuthenticated ? 401 : 403;
            $error = $isNotAuthenticated ? 'unauthorized' : 'forbidden';
            $message = $isNotAuthenticated ? 'Authentication required' : 'Access denied';

            $data = [
                'error' => $error,
                'message' => $message,
                'code' => $statusCode,
            ];

            if ($this->showDebug()) {
                $data['debug'] = [
                    'class' => $exception::class,
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            $headers = $isNotAuthenticated ? ['WWW-Authenticate' => \Mage::helper('apiplatform')->getBearerChallenge()] : [];
            return new JsonResponse($data, $statusCode, $headers);
        }

        // Handle Symfony HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();

            // Convert 403 → 401 when no Bearer token present
            // (correct HTTP semantics: 401 = "provide credentials", 403 = "credentials insufficient")
            // Basic auth is site-level access (dev/staging), not API authentication
            // Endpoints that need a domain-specific error code (e.g. the OAuth2
            // token endpoint returning invalid_client / unsupported_grant_type)
            // set the X-Api-Error-Code header on the thrown exception. When
            // present it overrides the generic status-derived code, and the
            // exception's own message is preserved (even for 401).
            $customErrorCode = $exception->getHeaders()['X-Api-Error-Code'] ?? null;

            $hasBearerToken = $request !== null
                && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
            // A bare 403 with no Bearer token usually means "authenticate"
            // (Basic auth is site-level, not API auth), so surface it as 401.
            // But an endpoint that deliberately chose 403 — e.g. a public,
            // URL-key-authenticated download rejecting a wrong key — signals that
            // by setting X-Api-Error-Code; honour its status instead of downgrading.
            if ($statusCode === 403 && !$hasBearerToken && $customErrorCode === null) {
                $statusCode = 401;
            }

            $data = [
                'error' => $customErrorCode ?: $this->getErrorCodeFromStatusCode($statusCode),
                'message' => $customErrorCode
                    ? ($exception->getMessage() ?: $this->getDefaultMessageForStatusCode($statusCode))
                    : ($statusCode === 401
                        ? 'Authentication required'
                        : ($exception->getMessage() ?: $this->getDefaultMessageForStatusCode($statusCode))),
                'code' => $statusCode,
            ];

            if ($this->showDebug()) {
                $data['debug'] = [
                    'class' => $exception::class,
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            $headers = $statusCode === 401 ? ['WWW-Authenticate' => \Mage::helper('apiplatform')->getBearerChallenge()] : [];
            return new JsonResponse($data, $statusCode, $headers);
        }

        // Request bodies the Symfony Serializer rejects while deserializing into
        // an input DTO (API Platform's DeserializeProvider) are client errors.
        // NotEncodableValueException = unparseable JSON; the other subclasses
        // (NotNormalizableValueException, PartialDenormalizationException, the
        // base class itself) = parseable JSON that doesn't fit the DTO shape.
        // Their messages are serializer-generated and client-actionable ("The
        // type of the "email" attribute must be ..."), safe to pass through.
        // Processor::parseRequestBody() already maps its own JSON errors to 400;
        // this mirrors that for the DTO path, which otherwise surfaced as 500.
        if ($exception instanceof SerializerUnexpectedValueException) {
            $statusCode = 400;
            $data = [
                'error' => 'bad_request',
                'message' => $exception instanceof NotEncodableValueException
                    ? 'Invalid JSON in request body'
                    : ($exception->getMessage() ?: 'Invalid request body'),
                'code' => $statusCode,
            ];

            if ($this->showDebug()) {
                $data['debug'] = [
                    'class' => $exception::class,
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            return new JsonResponse($data, $statusCode);
        }

        // Mage_Core_Exception is the canonical user-facing validation/business
        // rule signal in Maho models (Mage::throwException()). Treat it as a
        // 422 Unprocessable Entity with the model's message instead of a 500.
        // The trust assumption is that callers of Mage::throwException() pass
        // safe, translated messages, log every occurrence to api.log so
        // anomalous leaks (DB error fragments, internal IDs, file paths) can
        // be detected post-hoc by reviewing the channel.
        if ($exception instanceof \Mage_Core_Exception) {
            $statusCode = 422;
            \Mage::log(
                'API 422 Mage_Core_Exception: ' . $exception->getMessage(),
                \Mage::LOG_INFO,
                'api.log',
            );
            $data = [
                'error' => 'unprocessable_entity',
                'message' => $exception->getMessage(),
                'code' => $statusCode,
            ];

            if ($this->showDebug()) {
                $data['debug'] = [
                    'class' => $exception::class,
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            return new JsonResponse($data, $statusCode);
        }

        // Handle generic exceptions
        $statusCode = 500;
        $data = [
            'error' => 'internal_server_error',
            'message' => $this->showDebug() ? $exception->getMessage() : 'An internal error occurred',
            'code' => $statusCode,
        ];

        if ($this->showDebug()) {
            $data['debug'] = [
                'class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        // Log the exception
        \Mage::logException($exception);

        return new JsonResponse($data, $statusCode);
    }

    /**
     * Only show debug info when both Symfony debug mode AND Maho developer mode are active
     */
    private function showDebug(): bool
    {
        return $this->debug && \Mage::getIsDeveloperMode();
    }

    private function getErrorCodeFromStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            422 => 'unprocessable_entity',
            429 => 'too_many_requests',
            500 => 'internal_server_error',
            502 => 'bad_gateway',
            503 => 'service_unavailable',
            default => 'error',
        };
    }

    private function getDefaultMessageForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad request',
            401 => 'Authentication required',
            403 => 'Access denied',
            404 => 'Resource not found',
            405 => 'Method not allowed',
            409 => 'Conflict with current state',
            422 => 'Unprocessable entity',
            429 => 'Too many requests',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service temporarily unavailable',
            default => 'An error occurred',
        };
    }
}
