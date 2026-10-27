<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CustomerAddressCreate;
use MahoCLI\Commands\CustomerAddressDelete;
use MahoCLI\Commands\CustomerAddressList;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tester\ExecutionResult;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

const CUSTOMER_ADDRESS_EMAIL = 'cli-address@example.com';

function customerAddressRun(array $input, array $answers = []): ExecutionResult
{
    $command = new CustomerAddressCreate('customer:address:create');
    new Application()->addCommand($command);
    return new CommandTester($command)->run($input, $answers, interactive: $answers !== []);
}

function customerAddressArguments(array $more = []): array
{
    return array_filter([
        'email' => CUSTOMER_ADDRESS_EMAIL,
        'street' => '10 Market Street',
        'city' => 'San Francisco',
        'postcode' => '94105',
        'country' => 'US',
        'telephone' => '555-0100',
        '--region' => 'CA',
        ...$more,
    ], fn($value) => $value !== null);
}

function customerAddressCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore(1)->getWebsiteId())
        ->loadByEmail(CUSTOMER_ADDRESS_EMAIL);
    return Mage::getModel('customer/customer')->load($customer->getId());
}

function customerAddressCleanup(): void
{
    foreach (Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', CUSTOMER_ADDRESS_EMAIL) as $customer) {
        $customer->delete();
    }
}

beforeEach(function (): void {
    customerAddressCleanup();
    // The CLI runs in the admin store, as Mage::app('admin') in initMaho() sets it.
    $this->previousStore = Mage::app()->getStore()->getId();
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $this->adminStore = Mage::app()->getStore();
    $this->statesRequired = $this->adminStore->getConfig(Mage_Directory_Helper_Data::XML_PATH_STATES_REQUIRED);
    $this->adminStore->setConfig(Mage_Directory_Helper_Data::XML_PATH_STATES_REQUIRED, 'US');
    $this->california = (int) Mage::getModel('directory/region')->loadByCode('CA', 'US')->getId();

    Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore(1)->getWebsiteId())
        ->setEmail(CUSTOMER_ADDRESS_EMAIL)
        ->setFirstname('Cli')
        ->setLastname('Address')
        ->save();
});

afterEach(function (): void {
    customerAddressCleanup();
    $this->adminStore->setConfig(Mage_Directory_Helper_Data::XML_PATH_STATES_REQUIRED, $this->statesRequired);
    Mage::app()->setCurrentStore($this->previousStore);
});

it('saves the address of the arguments as the default billing and shipping address of a customer without addresses', function (): void {
    $result = customerAddressRun(customerAddressArguments());

    $this->assertCommandIsSuccessful($result);
    $customer = customerAddressCustomer();
    $address = $customer->getDefaultBillingAddress();
    expect($address)->toBeInstanceOf(Mage_Customer_Model_Address::class)
        ->and($address->getData())->toMatchArray([
            'firstname' => 'Cli',
            'lastname' => 'Address',
            'street' => '10 Market Street',
            'city' => 'San Francisco',
            'postcode' => '94105',
            'country_id' => 'US',
            'telephone' => '555-0100',
        ])
        ->and((int) $address->getRegionId())->toBe($this->california)
        ->and((int) $customer->getData('default_shipping'))->toBe((int) $address->getId());
});

it('makes a second address a default address only with its option', function (array $options, bool $isBilling, bool $isShipping): void {
    $this->assertCommandIsSuccessful(customerAddressRun(customerAddressArguments()));
    $first = (int) customerAddressCustomer()->getData('default_billing');

    $result = customerAddressRun(customerAddressArguments(['street' => '20 Mission Street', ...$options]));

    $this->assertCommandIsSuccessful($result);
    $customer = customerAddressCustomer();
    $second = (int) $customer->getAddressesCollection()->addFieldToFilter('entity_id', ['neq' => $first])->getFirstItem()->getId();
    expect((int) $customer->getData('default_billing'))->toBe($isBilling ? $second : $first)
        ->and((int) $customer->getData('default_shipping'))->toBe($isShipping ? $second : $first);
})->with([
    'no option' => [[], false, false],
    'default billing' => [['--default-billing' => true], true, false],
    'default shipping' => [['--default-shipping' => true], false, true],
]);

it('asks for the country and the state/province in the terminal', function (): void {
    $result = customerAddressRun([], [CUSTOMER_ADDRESS_EMAIL, '10 Market Street', 'San Francisco', '94105', '555-0100', 'US', 'CA']);

    $this->assertCommandIsSuccessful($result);
    $address = customerAddressCustomer()->getDefaultBillingAddress();
    expect($address->getCountryId())->toBe('US')
        ->and((int) $address->getRegionId())->toBe($this->california);
});

it('saves no address that the store cannot accept', function (array $more, string $error): void {
    $result = customerAddressRun(customerAddressArguments($more));

    expect($result->statusCode)->toBe(Command::INVALID)
        ->and($result->getDisplay())->toContain($error)
        ->and(customerAddressCustomer()->getAddresses())->toBe([]);
})->with([
    'unknown country' => [['country' => 'XX'], 'Country not found with code: XX'],
    'state/province of another country' => [['--region' => 'Lombardia'], 'State/province not found in country US: Lombardia'],
    'no state/province where the country requires one' => [['--region' => null], 'Please enter the state/province.'],
    'unknown attribute' => [['--attribute' => ['nosuch=1']], 'Attribute not found: nosuch'],
    'attribute without a value' => [['--attribute' => ['middlename']], 'Give the attribute as code=value: middlename'],
    'attribute with its own argument' => [['--attribute' => ['city=Oakland']], 'Give city with its argument or option, not with --attribute'],
]);

it('saves the state/province as text for a country without a list of states/provinces', function (): void {
    $countriesWithRegions = Mage::getResourceModel('directory/region_collection')->getColumnValues('country_id');
    $country = array_find(
        array_keys(Mage::getResourceModel('directory/country_collection')->loadByStore()->toOptionHash()),
        fn(string $code): bool => !in_array($code, $countriesWithRegions, true)
            && Mage::helper('customer/address')->getPostcodeFormatError($code, '94105') === null,
    );

    $result = customerAddressRun(customerAddressArguments(['country' => $country, '--region' => 'Some County']));

    $this->assertCommandIsSuccessful($result);
    expect(customerAddressCustomer()->getDefaultBillingAddress()->getRegion())->toBe('Some County');
});

it('saves the address only with the attributes that the store makes required', function (array $more, int $status, ?string $middlename): void {
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', 'middlename');
    $wasRequired = (int) $attribute->getIsRequired();
    $attribute->setIsRequired(1)->save();

    try {
        $result = customerAddressRun(customerAddressArguments($more));
    } finally {
        $attribute->setIsRequired($wasRequired)->save();
    }

    expect($result->statusCode)->toBe($status)
        ->and((customerAddressCustomer()->getDefaultBillingAddress() ?: null)?->getMiddlename())->toBe($middlename);
    if ($status === Command::INVALID) {
        expect($result->getDisplay())->toContain('is a required value.');
    }
})->with([
    'no value' => [[], Command::INVALID, null],
    'a value' => [['--attribute' => ['middlename=Q']], Command::SUCCESS, 'Q'],
]);

it('asks for the attributes that the store makes required in the terminal', function (): void {
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', 'middlename');
    $wasRequired = (int) $attribute->getIsRequired();
    $attribute->setIsRequired(1)->save();

    try {
        $result = customerAddressRun(['country' => 'US', '--region' => 'CA'], [CUSTOMER_ADDRESS_EMAIL, '10 Market Street', 'San Francisco', '94105', '555-0100', 'Q']);
    } finally {
        $attribute->setIsRequired($wasRequired)->save();
    }

    $this->assertCommandIsSuccessful($result);
    expect(customerAddressCustomer()->getDefaultBillingAddress()->getMiddlename())->toBe('Q');
});

function customerAddressCommandRun(string $class, string $name, array $input, array $answers = []): ExecutionResult
{
    $command = new $class($name);
    new Application()->addCommand($command);
    return new CommandTester($command)->run($input, $answers, interactive: $answers !== []);
}

it('lists the addresses of a customer, with the default addresses', function (): void {
    $this->assertCommandIsSuccessful(customerAddressRun(customerAddressArguments()));
    $this->assertCommandIsSuccessful(customerAddressRun(customerAddressArguments(['street' => '20 Mission Street'])));

    $result = customerAddressCommandRun(CustomerAddressList::class, 'customer:address:list', ['email' => CUSTOMER_ADDRESS_EMAIL]);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toMatch('/10 Market Street.*\byes\s*\|\s*yes\b/')
        ->and($result->getDisplay())->toMatch('/20 Mission Street.*\bno\s*\|\s*no\b/');
});

it('lists no addresses for a customer without addresses', function (): void {
    $result = customerAddressCommandRun(CustomerAddressList::class, 'customer:address:list', ['email' => CUSTOMER_ADDRESS_EMAIL]);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('No addresses found.');
});

it('deletes the address that the arguments name, and removes it from the default addresses', function (): void {
    $this->assertCommandIsSuccessful(customerAddressRun(customerAddressArguments()));
    $this->assertCommandIsSuccessful(customerAddressRun(customerAddressArguments(['street' => '20 Mission Street'])));
    $default = (int) customerAddressCustomer()->getData('default_billing');

    $result = customerAddressCommandRun(CustomerAddressDelete::class, 'customer:address:delete', ['address_id' => (string) $default]);

    $this->assertCommandIsSuccessful($result);
    $customer = customerAddressCustomer();
    expect(array_map(fn($address) => $address->getStreetFull(), array_values($customer->getAddresses())))->toBe(['20 Mission Street'])
        ->and($customer->getData('default_billing'))->toBeNull()
        ->and($customer->getData('default_shipping'))->toBeNull();
});

it('deletes no address with an unknown ID', function (string $addressId): void {
    $result = customerAddressCommandRun(CustomerAddressDelete::class, 'customer:address:delete', ['address_id' => $addressId]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and($result->getDisplay())->toContain("Address not found with ID: $addressId");
})->with([
    'zero' => ['0'],
    'no address' => ['2147483647'],
    'above the largest INTEGER' => ['99999999999'],
]);

it('asks for the address to delete in the terminal', function (): void {
    $this->assertCommandIsSuccessful(customerAddressRun(customerAddressArguments()));
    $addressId = (int) customerAddressCustomer()->getData('default_billing');

    $result = customerAddressCommandRun(CustomerAddressDelete::class, 'customer:address:delete', [], [CUSTOMER_ADDRESS_EMAIL, (string) $addressId]);

    $this->assertCommandIsSuccessful($result);
    expect(customerAddressCustomer()->getAddresses())->toBe([]);
});
