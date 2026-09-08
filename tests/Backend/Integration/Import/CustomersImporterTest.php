<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Customers;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function customersCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'customers') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function customersCleanup(): void
{
    foreach (Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', ['like' => 'imp-%']) as $customer) {
        $customer->delete();
    }
}

beforeEach(fn() => customersCleanup());
afterEach(fn() => customersCleanup());

it('imports a customer with an address and reruns without duplicates', function (): void {
    $store = Mage::app()->getStore(1);
    $path = customersCsv([
        ['email', '_website', '_store', 'firstname', 'lastname', 'group_id', 'password', '_address_firstname', '_address_lastname', '_address_street', '_address_city', '_address_country_id', '_address_postcode', '_address_telephone', '_address_default_billing_', '_address_default_shipping_'],
        ['imp-one@example.com', $store->getWebsite()->getCode(), $store->getCode(), 'Imp', 'One', '1', 'Password123!', 'Imp', 'One', 'Main 1', 'Town', 'US', '10001', '555', '1', '1'],
        ['imp-two@example.com', $store->getWebsite()->getCode(), $store->getCode(), 'Imp', 'Two', '1', 'Password123!', '', '', '', '', '', '', '', '', ''],
    ]);

    expect((new Customers())->import($path)->created)->toBe(2);
    $customer = Mage::getModel('customer/customer')->setWebsiteId($store->getWebsiteId())->loadByEmail('imp-one@example.com');
    expect($customer->getFirstname())->toBe('Imp');
    expect($customer->getDefaultBillingAddress()->getCity())->toBe('Town');
    $second = Mage::getModel('customer/customer')->setWebsiteId($store->getWebsiteId())->loadByEmail('imp-two@example.com');
    expect($second->getAddressesCollection()->count())->toBe(0);

    (new Customers())->import($path);
    expect(Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', 'imp-one@example.com')->count())->toBe(1);
    unlink($path);
});

it('rejects an unknown website and a bad email before writing', function (): void {
    $importer = new Customers();
    $header = ['email', '_website', 'firstname', 'lastname'];

    $path = customersCsv([$header, ['imp-two@example.com', 'no_such_site', 'Imp', 'Two']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'line 2');
    unlink($path);

    $path = customersCsv([$header, ['not-an-email', Mage::app()->getStore(1)->getWebsite()->getCode(), 'Imp', 'Two']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'line 2');
    unlink($path);

    expect(Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', ['like' => 'imp-%'])->count())->toBe(0);
});
