<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CustomerCreate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tester\ExecutionResult;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

const CUSTOMER_CREATE_EMAIL = 'cli-create@example.com';

function customerCreateRun(array $input, array $answers = []): ExecutionResult
{
    $command = new CustomerCreate('customer:create');
    new Application()->addCommand($command);
    return new CommandTester($command)->run($input, $answers, interactive: $answers !== []);
}

function customerCreateCleanup(): void
{
    foreach (Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', CUSTOMER_CREATE_EMAIL) as $customer) {
        $customer->delete();
    }
}

beforeEach(function (): void {
    customerCreateCleanup();
    // The CLI runs in the admin store, as Mage::app('admin') in initMaho() sets it.
    $this->previousStore = Mage::app()->getStore()->getId();
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $this->website = Mage::app()->getWebsite((int) Mage::app()->getStore(1)->getWebsiteId());
    $this->store = $this->website->getDefaultStore();
    $this->defaultGroupId = $this->store->getConfig(Mage_Customer_Model_Group::XML_PATH_DEFAULT_ID);
    $this->store->setConfig(Mage_Customer_Model_Group::XML_PATH_DEFAULT_ID, '3');
});

afterEach(function (): void {
    customerCreateCleanup();
    $this->store->setConfig(Mage_Customer_Model_Group::XML_PATH_DEFAULT_ID, $this->defaultGroupId);
    Mage::app()->setCurrentStore($this->previousStore);
});

function customerCreateGroupId(Mage_Core_Model_Website $website): int
{
    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $website->getId())->loadByEmail(CUSTOMER_CREATE_EMAIL);
    return (int) $customer->getData('group_id');
}

it('saves the customer in the customer group that the user picks in the terminal', function (string $answer, int $expectedGroupId): void {
    $result = customerCreateRun([], [CUSTOMER_CREATE_EMAIL, 'Password123!', 'Cli', 'Create', $this->website->getCode(), $answer]);

    $this->assertCommandIsSuccessful($result);
    expect(customerCreateGroupId($this->website))->toBe($expectedGroupId);
})->with([
    'a group' => ['2', 2],
    'no answer, default group of the website' => ['', 3],
]);

it('saves the customer in the customer group of the arguments without asking', function (array $group, int $expectedGroupId): void {
    $result = customerCreateRun([
        'email' => CUSTOMER_CREATE_EMAIL,
        'password' => 'Password123!',
        'firstname' => 'Cli',
        'lastname' => 'Create',
        'website' => $this->website->getCode(),
        ...$group,
    ]);

    $this->assertCommandIsSuccessful($result);
    expect(customerCreateGroupId($this->website))->toBe($expectedGroupId);
})->with([
    'a group' => [['group' => '2'], 2],
    'no group, default group of the website' => [[], 3],
]);

it('saves the customer only with the attributes that the store makes required', function (array $more, int $status, ?string $middlename): void {
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', 'middlename');
    $wasRequired = (int) $attribute->getIsRequired();
    $attribute->setIsRequired(1)->save();

    try {
        $result = customerCreateRun([
            'email' => CUSTOMER_CREATE_EMAIL,
            'password' => 'Password123!',
            'firstname' => 'Cli',
            'lastname' => 'Create',
            'website' => $this->website->getCode(),
            ...$more,
        ]);
    } finally {
        $attribute->setIsRequired($wasRequired)->save();
    }

    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $this->website->getId())->loadByEmail(CUSTOMER_CREATE_EMAIL);
    expect($result->statusCode)->toBe($status)
        ->and($customer->getMiddlename())->toBe($middlename);
    if ($status === Symfony\Component\Console\Command\Command::INVALID) {
        expect($result->getDisplay())->toContain('is a required value.');
    }
})->with([
    'no value' => [[], Symfony\Component\Console\Command\Command::INVALID, null],
    'a value' => [['--attribute' => ['middlename=Q']], Symfony\Component\Console\Command\Command::SUCCESS, 'Q'],
]);

it('saves the option that the user picks when two options have the same label', function (): void {
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', 'gender');
    $wasRequired = (int) $attribute->getIsRequired();
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $optionTable = $resource->getTableName('eav/attribute_option');
    $adapter->insert($optionTable, ['attribute_id' => (int) $attribute->getId(), 'sort_order' => 99]);
    $optionId = (int) $adapter->lastInsertId($optionTable);
    $adapter->insert($resource->getTableName('eav/attribute_option_value'), ['option_id' => $optionId, 'store_id' => 0, 'value' => 'Female']);
    $attribute->setIsRequired(1)->save();

    try {
        $result = customerCreateRun([], [CUSTOMER_CREATE_EMAIL, 'Password123!', 'Cli', 'Create', $this->website->getCode(), '2', "Female ($optionId)"]);
    } finally {
        $adapter->delete($resource->getTableName('eav/attribute_option_value'), ['option_id = ?' => $optionId]);
        $adapter->delete($optionTable, ['option_id = ?' => $optionId]);
        $attribute->setIsRequired($wasRequired)->save();
    }

    $this->assertCommandIsSuccessful($result);
    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $this->website->getId())->loadByEmail(CUSTOMER_CREATE_EMAIL);
    expect((int) $customer->getGender())->toBe($optionId);
});

it('refuses an attribute that an argument of the command gives', function (string $code): void {
    $result = customerCreateRun([
        'email' => CUSTOMER_CREATE_EMAIL,
        'password' => 'Password123!',
        'firstname' => 'Cli',
        'lastname' => 'Create',
        'website' => $this->website->getCode(),
        '--attribute' => ["$code=other"],
    ]);

    expect($result->statusCode)->toBe(Symfony\Component\Console\Command\Command::INVALID)
        ->and($result->getDisplay())->toContain("Give $code with its argument or option, not with --attribute");
})->with(['email', 'firstname', 'lastname', 'website_id', 'group_id']);

it('asks for the attributes that the store makes required in the terminal', function (): void {
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', 'middlename');
    $wasRequired = (int) $attribute->getIsRequired();
    $attribute->setIsRequired(1)->save();

    try {
        $result = customerCreateRun([], [CUSTOMER_CREATE_EMAIL, 'Password123!', 'Cli', 'Create', $this->website->getCode(), '2', 'Q']);
    } finally {
        $attribute->setIsRequired($wasRequired)->save();
    }

    $this->assertCommandIsSuccessful($result);
    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $this->website->getId())->loadByEmail(CUSTOMER_CREATE_EMAIL);
    expect($customer->getMiddlename())->toBe('Q');
});

it('saves the option that the user picks for a required attribute with options', function (): void {
    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', 'gender');
    $wasRequired = (int) $attribute->getIsRequired();
    $attribute->setIsRequired(1)->save();

    try {
        $result = customerCreateRun([], [CUSTOMER_CREATE_EMAIL, 'Password123!', 'Cli', 'Create', $this->website->getCode(), '2', 'Female']);
    } finally {
        $attribute->setIsRequired($wasRequired)->save();
    }

    $this->assertCommandIsSuccessful($result);
    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $this->website->getId())->loadByEmail(CUSTOMER_CREATE_EMAIL);
    expect($customer->getAttribute('gender')->getSource()->getOptionText($customer->getGender()))->toBe('Female');
});

it('fails without asking when a required argument is missing', function (): void {
    $command = new CustomerCreate('customer:create');
    new Application()->addCommand($command);

    expect(fn() => new CommandTester($command)->run(['email' => CUSTOMER_CREATE_EMAIL], interactive: false))
        ->toThrow(Symfony\Component\Console\Exception\RuntimeException::class, 'Not enough arguments (missing: "password, firstname, lastname")');
});

function customerCommandsSave(Mage_Core_Model_Website $website): Mage_Customer_Model_Customer
{
    return Mage::getModel('customer/customer')
        ->setWebsiteId((int) $website->getId())
        ->setEmail(CUSTOMER_CREATE_EMAIL)
        ->setPassword('Password123!')
        ->setFirstname('Cli')
        ->setLastname('Create')
        ->save();
}

it('changes the password of the customer that the arguments name', function (): void {
    $customer = customerCommandsSave($this->website);
    $command = new MahoCLI\Commands\CustomerChangepassword('customer:change-password');
    new Application()->addCommand($command);

    $result = new CommandTester($command)->run(['email' => CUSTOMER_CREATE_EMAIL, 'password' => 'Changed456!', '--website' => $this->website->getCode()], interactive: false);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('Password changed for customer ' . CUSTOMER_CREATE_EMAIL);
    expect(Mage::getModel('customer/customer')->load($customer->getId())->validatePassword('Changed456!'))->toBeTrue();
});

it('deletes the customer that the argument names', function (): void {
    $customer = customerCommandsSave($this->website);
    $command = new MahoCLI\Commands\CustomerDelete('customer:delete');
    new Application()->addCommand($command);

    $result = new CommandTester($command)->run(['email' => CUSTOMER_CREATE_EMAIL], interactive: false);

    $this->assertCommandIsSuccessful($result);
    expect(Mage::getModel('customer/customer')->load($customer->getId())->getId())->toBeNull();
});
