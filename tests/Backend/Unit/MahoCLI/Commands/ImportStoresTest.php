<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\ImportStores;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

function importStoresTester(): CommandTester
{
    $command = new ImportStores('import:stores');
    $application = new Application();
    $application->addCommand($command);
    return new CommandTester($command);
}

function importStoresCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'import-stores') . '.csv';
    file_put_contents($path, $content);
    return $path;
}

it('validates without writing on --dry-run', function (): void {
    $path = importStoresCsv("website_code,root_category,store_code\nimp_dry,Import Dry Root,imp_dry\n");
    $tester = importStoresTester();
    $tester->execute(['csv' => $path, '--dry-run' => true]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('is valid, nothing written');
    expect(Mage::getModel('core/website')->load('imp_dry', 'code')->getId())->toBeEmpty();
    unlink($path);
});

it('fails with the file name and line on an invalid row', function (): void {
    $path = importStoresCsv("website_code,root_category,store_code\nimp_dry,Import Dry Root,admin\n");
    $tester = importStoresTester();
    $tester->execute(['csv' => $path]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE);
    expect($tester->getDisplay())->toContain(basename($path) . ' line 2');
    expect($tester->getDisplay())->toContain('cannot be admin');
    unlink($path);
});
