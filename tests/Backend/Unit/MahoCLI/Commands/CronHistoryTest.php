<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CronHistory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

it('labels the creation time column created_at', function () {
    $command = new CronHistory('cron:history');
    $application = new Application();
    $application->addCommand($command);
    $tester = new CommandTester($command);
    $tester->execute([]);

    expect($tester->getDisplay())->toContain('created_at')
        ->and(substr_count($tester->getDisplay(), 'messages'))->toBe(1);
});
