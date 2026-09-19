<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Exception\AccessDeniedException as MetadataAccessDeniedException;
use ApiPlatform\Metadata\Get;
use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SymfonyAccessDeniedException;

uses(Tests\MahoBackendTestCase::class);

/**
 * API Platform 5 throws the Symfony security exception with the Metadata one
 * chained; 6.0 throws the Metadata one alone. An operation mapping either to
 * 404 must get the masked "Not Found" body for both shapes.
 */
function accessDeniedResponse(\Throwable $exception, array $exceptionToStatus): ?Response
{
    $request = Request::create('/api/rest/v2/orders/1', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer token']);
    $request->attributes->set('_api_operation', new Get(uriTemplate: '/orders/{id}', exceptionToStatus: $exceptionToStatus));

    $kernel = new class implements HttpKernelInterface {
        #[\Override]
        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
            return new Response();
        }
    };

    $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    (new ApiExceptionListener())->onKernelException($event);

    return $event->getResponse();
}

$mapping = [
    SymfonyAccessDeniedException::class => 404,
    MetadataAccessDeniedException::class => 404,
];

it('masks the API Platform 5 shape (Symfony exception wrapping the Metadata one) as 404', function () use ($mapping): void {
    $problem = new MetadataAccessDeniedException('Access Denied.');
    $response = accessDeniedResponse(new SymfonyAccessDeniedException('Access Denied.', $problem), $mapping);

    expect($response?->getStatusCode())->toBe(404);
    expect(json_decode((string) $response?->getContent(), true))->toBe(['error' => 'not_found', 'message' => 'Not Found', 'code' => 404]);
});

it('masks the bare Metadata exception (API Platform 6 shape) as 404', function () use ($mapping): void {
    $response = accessDeniedResponse(new MetadataAccessDeniedException('Access Denied.'), $mapping);

    expect($response?->getStatusCode())->toBe(404);
    expect(json_decode((string) $response?->getContent(), true))->toBe(['error' => 'not_found', 'message' => 'Not Found', 'code' => 404]);
});

it('answers 403 when the operation carries no mapping', function (): void {
    $response = accessDeniedResponse(new MetadataAccessDeniedException('Access Denied.'), []);

    expect($response?->getStatusCode())->toBe(403);
});
