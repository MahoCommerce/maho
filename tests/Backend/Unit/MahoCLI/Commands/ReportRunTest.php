<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\ReportRun;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

it('runs a private saved report of any administrator', function () {
    $admin = AdminRoleFixture::login(['report']);
    $service = Mage::getService('reports/savedReport');
    $report = $service->save($service->newReport()->setName('Private')->setIsShared(false)
        ->setRequestData(['dataset' => 'sales_orders', 'metrics' => ['orders']]), (int) $admin->getId(), Mage_Reports_Model_Explorer_Access::full());
    $command = new ReportRun('report:run');
    new Application()->addCommand($command);
    $tester = new CommandTester($command);

    try {
        $tester->execute(['--saved' => (string) $report->getId(), '--format' => 'json']);
        expect($tester->getStatusCode())->toBe(0)
            ->and(json_decode($tester->getDisplay(), true)['request']['dataset'])->toBe('sales_orders');
    } finally {
        $service->delete($report, (int) $admin->getId());
        AdminRoleFixture::delete($admin);
    }
});
