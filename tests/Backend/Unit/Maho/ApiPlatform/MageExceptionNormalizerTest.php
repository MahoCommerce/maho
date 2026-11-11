<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use GraphQL\Error\Error;
use Maho\ApiPlatform\GraphQl\MageExceptionNormalizer;

uses(Tests\MahoBackendTestCase::class);

describe('MageExceptionNormalizer', function (): void {

    it('gives a GraphQL error the message and the status of the exception', function (Throwable $exception, int $status): void {
        $error = new MageExceptionNormalizer()->normalize(new Error('Internal server error', previous: $exception));

        expect($error['message'])->toBe($exception->getMessage())
            ->and($error['extensions']['status'])->toBe($status);
    })->with([
        'unauthorized' => [new Mage_Core_Exception_Unauthorized('Invalid email or password'), 401],
        'forbidden' => [new Mage_Core_Exception_Forbidden('Guest subscription is not allowed. Please login first.'), 403],
        'no such entity' => [new Mage_Core_Exception_NoSuchEntity('Cart not found'), 404],
        'conflict' => [new Mage_Core_Exception_Conflict('Order cannot be held'), 409],
        'too many requests' => [new Mage_Core_Exception_TooManyRequests('Too many requests. Please try again later.', 3600), 429],
        'other' => [new Mage_Core_Exception('Quantity must be greater than zero'), 422],
    ]);

    it('gives the name of the fault that REST gives', function (Throwable $exception, string $name): void {
        $error = new MageExceptionNormalizer()->normalize(new Error('Internal server error', previous: $exception));

        expect($error['extensions']['error'])->toBe($name);
    })->with([
        'unauthorized' => [new Mage_Core_Exception_Unauthorized('Invalid email or password'), 'unauthorized'],
        'unauthorized with a code' => [new Mage_Core_Exception_Unauthorized('Invalid email or password', 'invalid_credentials'), 'invalid_credentials'],
        'conflict' => [new Mage_Core_Exception_Conflict('Order cannot be held'), 'conflict'],
        'other' => [new Mage_Core_Exception('Quantity must be greater than zero'), 'unprocessable_entity'],
    ]);

    it('gives the errors of a Mage_Core_Exception_Input with the API name of each field', function (): void {
        $exception = new Mage_Core_Exception_Input()
            ->addError('website_ids', 'Select a website.')
            ->addError('name', 'Required.');

        $error = new MageExceptionNormalizer()->normalize(new Error('Internal server error', previous: $exception));

        expect($error['message'])->toBe("Select a website.\nRequired.")
            ->and($error['extensions']['status'])->toBe(422)
            ->and($error['extensions']['details'])->toBe([
                'field' => 'websiteIds',
                'errors' => [
                    ['field' => 'websiteIds', 'message' => 'Select a website.'],
                    ['field' => 'name', 'message' => 'Required.'],
                ],
            ]);
    });

    it('gives the retry delay of a Mage_Core_Exception_TooManyRequests', function (): void {
        $normalizer = new MageExceptionNormalizer();
        $known = $normalizer->normalize(new Error('x', previous: new Mage_Core_Exception_TooManyRequests('Too many requests.', 3600)));
        $unknown = $normalizer->normalize(new Error('x', previous: new Mage_Core_Exception_TooManyRequests('Too many requests.')));

        expect($known['extensions']['retryAfter'])->toBe(3600)
            ->and($unknown['extensions'])->not->toHaveKey('retryAfter');
    });

    it('gives no details and no retry delay for another exception', function (): void {
        $error = new MageExceptionNormalizer()->normalize(new Error('x', previous: new Mage_Core_Exception_Conflict('Order cannot be held')));

        expect($error['extensions'])->not->toHaveKey('details')
            ->and($error['extensions'])->not->toHaveKey('retryAfter');
    });

    it('does not take an error that a Mage_Core_Exception did not cause', function (): void {
        $normalizer = new MageExceptionNormalizer();

        expect($normalizer->supportsNormalization(new Error('x', previous: new RuntimeException('db'))))->toBeFalse()
            ->and($normalizer->supportsNormalization(new Error('x')))->toBeFalse()
            ->and($normalizer->supportsNormalization(new Error('x', previous: new Mage_Core_Exception('y'))))->toBeTrue();
    });

});
