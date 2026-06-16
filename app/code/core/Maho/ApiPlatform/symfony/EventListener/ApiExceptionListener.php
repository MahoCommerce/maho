<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

/**
 * API Exception Listener
 *
 * Converts API exceptions to standardized JSON error responses.
 */
class ApiExceptionListener implements EventSubscriberInterface
{
    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

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

            return new JsonResponse($data, $statusCode, ['WWW-Authenticate' => 'Bearer']);
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

            $headers = $isNotAuthenticated ? ['WWW-Authenticate' => 'Bearer'] : [];
            return new JsonResponse($data, $statusCode, $headers);
        }

        // Handle Symfony HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();

            // Convert 403 → 401 when no Bearer token present
            // (correct HTTP semantics: 401 = "provide credentials", 403 = "credentials insufficient")
            // Basic auth is site-level access (dev/staging), not API authentication
            $hasBearerToken = $request !== null
                && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
            if ($statusCode === 403 && !$hasBearerToken) {
                $statusCode = 401;
            }

            $data = [
                'error' => $this->getErrorCodeFromStatusCode($statusCode),
                'message' => $statusCode === 401
                    ? 'Authentication required'
                    : ($exception->getMessage() ?: $this->getDefaultMessageForStatusCode($statusCode)),
                'code' => $statusCode,
            ];

            if ($this->showDebug()) {
                $data['debug'] = [
                    'class' => $exception::class,
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            $headers = $statusCode === 401 ? ['WWW-Authenticate' => 'Bearer'] : [];
            return new JsonResponse($data, $statusCode, $headers);
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
