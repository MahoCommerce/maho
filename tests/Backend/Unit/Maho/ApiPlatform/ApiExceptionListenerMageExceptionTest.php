<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

uses(Tests\MahoBackendTestCase::class);

function mageExceptionResponse(\Throwable $exception): ?Response
{
    $kernel = new class implements HttpKernelInterface {
        #[\Override]
        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
            return new Response();
        }
    };

    $event = new ExceptionEvent($kernel, Request::create('/api/rest/v2/customers/token', 'POST'), HttpKernelInterface::MAIN_REQUEST, $exception);
    (new ApiExceptionListener())->onKernelException($event);

    return $event->getResponse();
}

describe('ApiExceptionListener with a Mage_Core_Exception', function (): void {

    it('gives each typed exception its status', function (Mage_Core_Exception $exception, int $status): void {
        expect(ApiExceptionListener::mageExceptionStatus($exception))->toBe($status);
    })->with([
        'unauthorized' => [new Mage_Core_Exception_Unauthorized('Invalid email or password'), 401],
        'forbidden' => [new Mage_Core_Exception_Forbidden('Guests cannot subscribe'), 403],
        'no such entity' => [new Mage_Core_Exception_NoSuchEntity('Cart not found'), 404],
        'conflict' => [new Mage_Core_Exception_Conflict('Order cannot be held'), 409],
        'too many requests' => [new Mage_Core_Exception_TooManyRequests('Too many requests.', 60), 429],
        'input' => [new Mage_Core_Exception_Input()->addError('name', 'Required.'), 422],
        'other' => [new Mage_Core_Exception('Quantity must be greater than zero'), 422],
    ]);

    it('answers 401 with the message and a challenge for a Mage_Core_Exception_Unauthorized', function (): void {
        $response = mageExceptionResponse(new Mage_Core_Exception_Unauthorized('Invalid email or password'));

        expect($response?->getStatusCode())->toBe(401)
            ->and(json_decode((string) $response?->getContent(), true))->toBe([
                'error' => 'unauthorized',
                'message' => 'Invalid email or password',
                'code' => 401,
            ])
            ->and($response?->headers->get('WWW-Authenticate'))->toStartWith('Bearer ');
    });

    it('gives the error code of a Mage_Core_Exception_Unauthorized as the name of the fault', function (): void {
        $response = mageExceptionResponse(new Mage_Core_Exception_Unauthorized('Two-factor authentication code is required', 'twofa_required'));

        expect($response?->getStatusCode())->toBe(401)
            ->and(json_decode((string) $response?->getContent(), true)['error'])->toBe('twofa_required');
    });

    it('sends no challenge for another status', function (): void {
        $response = mageExceptionResponse(new Mage_Core_Exception_Forbidden('Guests cannot subscribe'));

        expect($response?->getStatusCode())->toBe(403)
            ->and($response?->headers->has('WWW-Authenticate'))->toBeFalse();
    });

    it('sends the errors of a Mage_Core_Exception_Input and the delay of a Mage_Core_Exception_TooManyRequests', function (): void {
        $input = mageExceptionResponse(new Mage_Core_Exception_Input()->addError('website_ids', 'Select a website.'));
        $limit = mageExceptionResponse(new Mage_Core_Exception_TooManyRequests('Too many requests.', 3600));

        expect(json_decode((string) $input?->getContent(), true)['details'])->toBe([
            'field' => 'websiteIds',
            'errors' => [['field' => 'websiteIds', 'message' => 'Select a website.']],
        ])
            ->and($limit?->getStatusCode())->toBe(429)
            ->and($limit?->headers->get('Retry-After'))->toBe('3600');
    });

});
