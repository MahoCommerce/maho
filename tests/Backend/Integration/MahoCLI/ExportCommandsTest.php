<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use Maho\Import\Importer\Customers;
use MahoCLI\Commands\ExportCategories;
use MahoCLI\Commands\ExportCustomers;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

function exportTester(Command $command): CommandTester
{
    new Application()->addCommand($command);
    return new CommandTester($command);
}

function exportCustomer(#[\SensitiveParameter]
    string $email, string $firstname): Mage_Customer_Model_Customer
{
    $store = Mage::app()->getStore(1);
    return Mage::getModel('customer/customer')
        ->setWebsiteId((int) $store->getWebsiteId())
        ->setStoreId((int) $store->getId())
        ->setEmail($email)
        ->setFirstname($firstname)
        ->setLastname('Export')
        ->save();
}

function exportCustomersCleanup(): void
{
    foreach (Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', ['like' => 'exp-%']) as $customer) {
        $customer->delete();
    }
}

/**
 * @return list<array<string, string>>
 */
function exportRows(string $path): array
{
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle, escape: '\\');
    $rows = [];
    while (($row = fgetcsv($handle, escape: '\\')) !== false) {
        $rows[] = array_combine($header, $row);
    }
    fclose($handle);
    return $rows;
}

function exportHeader(string $path): array
{
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle, escape: '\\');
    fclose($handle);
    return $header;
}

beforeEach(function (): void {
    exportCustomersCleanup();
    $this->csv = sys_get_temp_dir() . '/export-' . bin2hex(random_bytes(6)) . '.csv';
});

afterEach(function (): void {
    exportCustomersCleanup();
    foreach ([$this->csv, $this->csv . '.part'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('exports customers to a file that the customer importer reads back', function (): void {
    exportCustomer('exp-one@example.com', 'Exporta');
    exportCustomer('exp-two@example.com', 'Exportb');

    $result = exportTester(new ExportCustomers('export:customers'))->run(['csv' => $this->csv, '--filter' => ['email=exp-']]);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain($this->csv . ': 2 rows');
    expect(array_column(exportRows($this->csv), 'email'))->toEqualCanonicalizing(['exp-one@example.com', 'exp-two@example.com']);

    exportCustomersCleanup();
    expect(new Customers()->import($this->csv)->created)->toBe(2);
    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) Mage::app()->getStore(1)->getWebsiteId())->loadByEmail('exp-one@example.com');
    expect($customer->getFirstname())->toBe('Exporta');
});

it('writes the columns of each entity when one process exports two entities', function (): void {
    exportCustomer('exp-one@example.com', 'Exporta');
    $customersCsv = $this->csv . '-customers.csv';

    $this->assertCommandIsSuccessful(exportTester(new ExportCustomers('export:customers'))->run(['csv' => $customersCsv, '--filter' => ['email=exp-']]));
    $this->assertCommandIsSuccessful(exportTester(new ExportCategories('export:categories'))->run(['csv' => $this->csv]));

    expect(exportHeader($this->csv))->toContain('url_key')->not->toContain('email');
    unlink($customersCsv);
});

it('leaves a skipped attribute out of the file', function (): void {
    exportCustomer('exp-one@example.com', 'Exporta');

    $result = exportTester(new ExportCustomers('export:customers'))->run(['csv' => $this->csv, '--filter' => ['email=exp-'], '--skip' => ['firstname']]);

    $this->assertCommandIsSuccessful($result);
    expect(exportHeader($this->csv))->toContain('lastname')->not->toContain('firstname');
});

it('fails on a filter that the export cannot apply and writes no file', function (array $filter, string $message): void {
    exportCustomer('exp-one@example.com', 'Exporta');

    $result = exportTester(new ExportCustomers('export:customers'))->run(['csv' => $this->csv, '--filter' => $filter]);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain($message);
    expect($this->csv)->not->toBeFile();
})->with([
    'unknown attribute' => [['nope=1'], "The export has no attribute 'nope'"],
    'no value' => [['email'], "Write the filter 'email' as code=value"],
    'date without range' => [['created_at=2026-01-01'], 'created_at=from..to'],
    'bad date' => [['created_at=yesterdayish..'], "'yesterdayish' is not a valid date"],
]);

it('fails when no row matches and leaves no partial file', function (): void {
    $result = exportTester(new ExportCustomers('export:customers'))->run(['csv' => $this->csv, '--filter' => ['email=exp-nobody']]);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain('There is no data for export');
    expect($this->csv)->not->toBeFile();
    expect($this->csv . '.part')->not->toBeFile();
});

it('filters a date attribute by range', function (): void {
    exportCustomer('exp-one@example.com', 'Exporta');

    $tester = exportTester(new ExportCustomers('export:customers'));
    $this->assertCommandIsSuccessful($tester->run(['csv' => $this->csv, '--filter' => ['email=exp-', 'created_at=2000-01-01..']]));
    expect(array_column(exportRows($this->csv), 'email'))->toBe(['exp-one@example.com']);

    $this->assertCommandFailed($tester->run(['csv' => $this->csv, '--filter' => ['email=exp-', 'created_at=..2000-01-01']]));
});
