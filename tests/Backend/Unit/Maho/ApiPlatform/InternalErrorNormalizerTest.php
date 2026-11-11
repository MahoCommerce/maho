<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Validator\Exception\ValidationException;
use GraphQL\Error\Error;
use GraphQL\Error\UserError;
use Maho\ApiPlatform\GraphQl\InternalErrorNormalizer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationList;

uses(Tests\MahoBackendTestCase::class);

describe('InternalErrorNormalizer', function (): void {

    it('takes a server fault, and a RuntimeException is one', function (Throwable $exception): void {
        expect(new InternalErrorNormalizer()->supportsNormalization(new Error('x', previous: $exception)))->toBeTrue();
    })->with([
        'RuntimeException' => [new RuntimeException('SQLSTATE[42S02]: no such table: secret_table')],
        'LogicException' => [new LogicException('A bug')],
        'TypeError' => [new TypeError('A bug')],
    ]);

    it('leaves a client fault to the other normalizers', function (?Throwable $exception): void {
        expect(new InternalErrorNormalizer()->supportsNormalization(new Error('x', previous: $exception)))->toBeFalse();
    })->with([
        'no exception' => [null],
        'HTTP exception' => [new NotFoundHttpException('Not Found')],
        'validation exception' => [new ValidationException(new ConstraintViolationList())],
        'Mage_Core_Exception' => [new Mage_Core_Exception('Quantity must be greater than zero')],
        'exception that GraphQL marks as safe' => [new UserError('Unknown field')],
    ]);

    it('hides the message of a RuntimeException and logs the exception', function (): void {
        $message = 'no such table: secret_table_' . uniqid();
        $logFile = Mage::getBaseDir('log') . '/exception.log';
        $sizeBefore = is_file($logFile) ? filesize($logFile) : 0;

        // Developer mode turns the log on, whatever the store configuration says
        $developerMode = Mage::getIsDeveloperMode();
        Mage::setIsDeveloperMode(true);
        try {
            $error = new InternalErrorNormalizer()->normalize(new Error('Internal server error', previous: new RuntimeException($message)));
        } finally {
            Mage::setIsDeveloperMode($developerMode);
        }

        clearstatcache();
        expect($error['message'])->toBe('Internal server error')
            ->and(json_encode($error))->not->toContain($message)
            ->and(is_file($logFile))->toBeTrue()
            ->and(file_get_contents($logFile, false, null, $sizeBefore))->toContain($message);
    });

    it('runs before the RuntimeExceptionNormalizer of API Platform', function (): void {
        $kernel = file_get_contents(Mage::getBaseDir('code') . '/core/Maho/ApiPlatform/symfony/Kernel.php');
        $vendor = file_get_contents(Mage::getBaseDir() . '/vendor/api-platform/core/src/Symfony/Bundle/Resources/config/graphql.php');

        preg_match('/InternalErrorNormalizer::class\)\s*->autoconfigure\(false\)\s*->tag\(\'serializer\.normalizer\', \[\'priority\' => (-?\d+)\]/', $kernel, $ours);
        preg_match('/RuntimeExceptionNormalizer::class\)\s*->tag\(\'serializer\.normalizer\', \[\'priority\' => (-?\d+)\]/', $vendor, $theirs);

        expect($ours)->toHaveCount(2)
            ->and($theirs)->toHaveCount(2)
            ->and((int) $ours[1])->toBeGreaterThan((int) $theirs[1]);
    });

});
