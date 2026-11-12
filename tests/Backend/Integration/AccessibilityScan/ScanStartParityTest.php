<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

use MahoCLI\Commands\AccessibilityScan;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin dashboard, accessibility:scan and the scheduled scans start a scan through
 * Maho_AccessibilityScan_Service_Scan::start() (#1348). It checks the URL, takes the store from the URL,
 * normalizes the WCAG level and creates the pending scan.
 */

/**
 * @return array<string, mixed> the JSON body of the answer
 */
function scanStartViaAdmin(string $url, string $level): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/admin/accessibilityscan_scan/start', 'POST', ['url' => $url, 'wcag_level' => $level]),
    );
    $response = new Mage_Core_Controller_Response_Http();
    new Maho_AccessibilityScan_Adminhtml_Accessibilityscan_ScanController($request, $response)->startAction();
    return json_decode((string) $response->getBody(), true);
}

/**
 * @return array{status: int, output: string}
 */
function scanStartViaCli(string $url, ?string $level = null): array
{
    $command = new AccessibilityScan('accessibility:scan');
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $input = ['--url' => $url] + ($level === null ? [] : ['--level' => $level]);
    $status = $tester->execute($input, ['interactive' => false]);
    return ['status' => $status, 'output' => trim($tester->getDisplay())];
}

function scanStartCount(): int
{
    return Mage::getResourceModel('accessibilityscan/scan_collection')->getSize();
}

function scanStartStoreUrl(): string
{
    return Mage::app()->getStore(1)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'parity-' . uniqid();
}

afterEach(function () {
    foreach (Mage::getResourceModel('accessibilityscan/scan_collection')->addFieldToFilter('url', ['like' => '%parity-%']) as $scan) {
        $scan->delete();
    }
});

it('refuses a URL that is not valid with the same message on the admin and the CLI', function () {
    $before = scanStartCount();

    $admin = scanStartViaAdmin('not a url', 'AA');
    $cli = scanStartViaCli('not a url');

    expect($admin)->toBe(['error' => true, 'message' => 'Please provide a valid URL to scan'])
        ->and($cli)->toBe(['status' => 2, 'output' => 'Please provide a valid URL to scan'])
        ->and(scanStartCount())->toBe($before);
});

it('refuses a URL outside the store base URLs on the admin, the CLI and the scheduled scans', function () {
    $before = scanStartCount();
    $message = 'The URL to scan must belong to one of the configured store base URLs';
    $store = Mage::app()->getStore();
    $store->setConfig('accessibilityscan/scheduled/enabled', '1');
    $store->setConfig('accessibilityscan/scheduled/urls', 'https://elsewhere.example/parity-cron');
    $schedule = Mage::getModel('cron/schedule');

    $admin = scanStartViaAdmin('https://elsewhere.example/parity-admin', 'AA');
    $cli = scanStartViaCli('https://elsewhere.example/parity-cli');
    Mage::getModel('accessibilityscan/cron')->runScheduledScans($schedule);

    expect($admin)->toBe(['error' => true, 'message' => $message])
        ->and($cli)->toBe(['status' => 2, 'output' => $message])
        ->and($schedule->getMessages())->toBe('Failed URLs: https://elsewhere.example/parity-cron')
        ->and(scanStartCount())->toBe($before);
});

it('creates a pending scan with the store of the URL and a normalized WCAG level', function (string $level, string $expected) {
    Mage::app()->getStore()->setConfig('accessibilityscan/general/wcag_level', 'A');
    $url = scanStartStoreUrl();

    $admin = scanStartViaAdmin($url, $level);

    expect($admin['success'] ?? null)->toBeTrue();
    $scan = Mage::getModel('accessibilityscan/scan')->load($admin['scan_id']);
    expect($scan->getUrl())->toBe($url)
        ->and($scan->getStoreId())->toBe(1)
        ->and($scan->getWcagLevel())->toBe($expected)
        ->and($scan->getTriggeredBy())->toBe(Maho_AccessibilityScan_Model_Scan::TRIGGER_MANUAL)
        ->and($scan->getStatus())->toBe(Maho_AccessibilityScan_Model_Scan::STATUS_PENDING);
})->with([
    'a lower case level' => [' aaa ', 'AAA'],
    'an unknown level' => ['B', 'AA'],
    'no level, which takes the configured default' => ['', 'A'],
]);

it('gives the URL and the level of the CLI to the same service', function () {
    $spy = new class extends Maho_AccessibilityScan_Service_Scan {
        /** @var list<array{0: string, 1: ?string, 2: string}> */
        public array $calls = [];

        #[\Override]
        public function start(string $url, ?string $wcagLevel, string $triggeredBy): Maho_AccessibilityScan_Model_Scan
        {
            $this->calls[] = [$url, $wcagLevel, $triggeredBy];
            throw new Mage_Core_Exception('Stopped before the run');
        }
    };
    Mage::unregister('_service/accessibilityscan/scan');
    Mage::register('_service/accessibilityscan/scan', $spy);

    $cli = scanStartViaCli('https://example.com/parity', 'aaa');

    expect($cli)->toBe(['status' => 2, 'output' => 'Stopped before the run'])
        ->and($spy->calls)->toBe([['https://example.com/parity', 'aaa', Maho_AccessibilityScan_Model_Scan::TRIGGER_CLI]]);
});
