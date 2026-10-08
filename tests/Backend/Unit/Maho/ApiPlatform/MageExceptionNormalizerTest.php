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
        'no such entity' => [new Mage_Core_Exception_NoSuchEntity('Cart not found'), 404],
        'conflict' => [new Mage_Core_Exception_Conflict('Order cannot be held'), 409],
        'other' => [new Mage_Core_Exception('Quantity must be greater than zero'), 422],
    ]);

    it('does not take an error that a Mage_Core_Exception did not cause', function (): void {
        $normalizer = new MageExceptionNormalizer();

        expect($normalizer->supportsNormalization(new Error('x', previous: new RuntimeException('db'))))->toBeFalse()
            ->and($normalizer->supportsNormalization(new Error('x')))->toBeFalse()
            ->and($normalizer->supportsNormalization(new Error('x', previous: new Mage_Core_Exception('y'))))->toBeTrue();
    });

});
