<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('preserves the database client output and exit status', function (int $diagnosticBytes, int $exitCode): void {
    $root = dirname(__DIR__, 5);
    $process = new Process([
        PHP_BINARY, __DIR__ . '/Fixtures/DBConnectProcessWorker.php.fixture',
        'command', $root, (string) $diagnosticBytes, (string) $exitCode,
    ], $root, timeout: 5);
    $process->run();

    expect($process->getOutput())->toBe("native-client-output\n")
        ->and($process->getErrorOutput())->toBe(str_repeat('D', $diagnosticBytes))
        ->and($process->getExitCode())->toBe($exitCode);
})->with([
    'successful client' => [0, 0],
    'successful client with diagnostics' => [128, 0],
    'failed client with diagnostics' => [128, 7],
]);
