<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

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

        $response = $this->createErrorResponse($exception);
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

    private function createErrorResponse(\Throwable $exception): JsonResponse
    {
        // Handle our custom API exceptions
        if ($exception instanceof ApiException) {
            $data = $exception->toArray();

            if ($this->debug && $exception->getPrevious()) {
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

            if ($this->debug) {
                $data['debug'] = [
                    'class' => get_class($exception),
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            return new JsonResponse($data, $statusCode, ['WWW-Authenticate' => 'Bearer']);
        }

        // Handle Symfony Security exceptions - access denied (authenticated but not authorized)
        if ($exception instanceof AccessDeniedException) {
            // If user is not authenticated at all, return 401
            // AccessDeniedException message contains "not appropriately authenticated" for unauthenticated users
            $isNotAuthenticated = str_contains($exception->getMessage(), 'not appropriately authenticated');
            $statusCode = $isNotAuthenticated ? 401 : 403;
            $error = $isNotAuthenticated ? 'unauthorized' : 'forbidden';
            $message = $isNotAuthenticated ? 'Authentication required' : 'Access denied';

            $data = [
                'error' => $error,
                'message' => $message,
                'code' => $statusCode,
            ];

            if ($this->debug) {
                $data['debug'] = [
                    'class' => get_class($exception),
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            $headers = $isNotAuthenticated ? ['WWW-Authenticate' => 'Bearer'] : [];
            return new JsonResponse($data, $statusCode, $headers);
        }

        // Handle Symfony HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $data = [
                'error' => $this->getErrorCodeFromStatusCode($statusCode),
                'message' => $exception->getMessage() ?: $this->getDefaultMessageForStatusCode($statusCode),
                'code' => $statusCode,
            ];

            if ($this->debug) {
                $data['debug'] = [
                    'class' => get_class($exception),
                    'trace' => $exception->getTraceAsString(),
                ];
            }

            return new JsonResponse($data, $statusCode);
        }

        // Handle generic exceptions
        $statusCode = 500;
        $data = [
            'error' => 'internal_server_error',
            'message' => $this->debug ? $exception->getMessage() : 'An internal error occurred',
            'code' => $statusCode,
        ];

        if ($this->debug) {
            $data['debug'] = [
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        // Log the exception
        \Mage::logException($exception);

        return new JsonResponse($data, $statusCode);
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
