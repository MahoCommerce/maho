<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CustomerGroupCreate;
use MahoCLI\Commands\CustomerGroupDelete;
use MahoCLI\Commands\CustomerGroupList;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tester\ExecutionResult;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

const CUSTOMER_GROUP_CODE = 'CLI Group';
const CUSTOMER_GROUP_EMAIL = 'cli-group@example.com';

function customerGroupRun(Command $command, array $input = [], array $answers = []): ExecutionResult
{
    new Application()->addCommand($command);
    return new CommandTester($command)->run($input, $answers, interactive: $answers !== []);
}

function customerGroupLoad(): Mage_Customer_Model_Group
{
    return Mage::getModel('customer/group')->load(CUSTOMER_GROUP_CODE, 'customer_group_code');
}

function customerGroupSave(int $taxClassId): Mage_Customer_Model_Group
{
    return Mage::getModel('customer/group')->setCode(CUSTOMER_GROUP_CODE)->setTaxClassId($taxClassId)->save();
}

function customerGroupCustomer(Mage_Customer_Model_Group $group): Mage_Customer_Model_Customer
{
    $store = Mage::app()->getStore(1);
    return Mage::getModel('customer/customer')
        ->setWebsiteId((int) $store->getWebsiteId())
        ->setStoreId((int) $store->getId())
        ->setEmail(CUSTOMER_GROUP_EMAIL)
        ->setFirstname('Cli')
        ->setLastname('Group')
        ->setGroupId((int) $group->getId())
        ->save();
}

function customerGroupOfCustomer(Mage_Customer_Model_Customer $customer): int
{
    return (int) Mage::getModel('customer/customer')->load($customer->getId())->getData('group_id');
}

function customerGroupCleanup(): void
{
    foreach (Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', CUSTOMER_GROUP_EMAIL) as $customer) {
        $customer->delete();
    }
    foreach (Mage::getResourceModel('customer/group_collection')->addFieldToFilter('customer_group_code', CUSTOMER_GROUP_CODE) as $group) {
        $group->delete();
    }
}

beforeEach(function (): void {
    customerGroupCleanup();
    $this->taxClass = Mage::getResourceModel('tax/class_collection')
        ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)
        ->getFirstItem();
});

afterEach(function (): void {
    customerGroupCleanup();
});

it('saves a customer group with the code and the tax class of the arguments', function (): void {
    $result = customerGroupRun(new CustomerGroupCreate('customer:group:create'), ['code' => CUSTOMER_GROUP_CODE, 'tax_class_id' => (string) $this->taxClass->getId()]);

    $this->assertCommandIsSuccessful($result);
    expect((int) customerGroupLoad()->getData('tax_class_id'))->toBe((int) $this->taxClass->getId());
});

it('fails without asking when the tax class is missing', function (): void {
    expect(fn() => customerGroupRun(new CustomerGroupCreate('customer:group:create'), ['code' => CUSTOMER_GROUP_CODE]))
        ->toThrow(Symfony\Component\Console\Exception\RuntimeException::class, 'Not enough arguments (missing: "tax_class_id")');
});

it('fails to create a customer group with a code longer than the model saves', function (): void {
    $code = str_repeat('x', Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH + 1);

    $result = customerGroupRun(new CustomerGroupCreate('customer:group:create'), ['code' => $code, 'tax_class_id' => (string) $this->taxClass->getId()]);

    expect($result->statusCode)->toBe(Command::INVALID);
    expect($result->getDisplay())->toContain('Code cannot be longer than 32 bytes');
    expect(Mage::getModel('customer/group')->load(substr($code, 0, Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH), 'customer_group_code')->getId())->toBeNull();
});

it('asks for the code and the tax class in the terminal', function (): void {
    $result = customerGroupRun(new CustomerGroupCreate('customer:group:create'), [], [CUSTOMER_GROUP_CODE, (string) $this->taxClass->getId()]);

    $this->assertCommandIsSuccessful($result);
    expect((int) customerGroupLoad()->getData('tax_class_id'))->toBe((int) $this->taxClass->getId());
});

it('fails to create a customer group with a tax class that is not a customer tax class', function (): void {
    $productTaxClassId = (int) Mage::getResourceModel('tax/class_collection')
        ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
        ->getFirstItem()
        ->getId();

    $result = customerGroupRun(new CustomerGroupCreate('customer:group:create'), ['code' => CUSTOMER_GROUP_CODE, 'tax_class_id' => (string) $productTaxClassId]);

    expect($result->statusCode)->toBe(Command::INVALID);
    expect($result->getDisplay())->toContain("Customer tax class not found with ID: $productTaxClassId");
    expect(customerGroupLoad()->getId())->toBeNull();
});

it('fails when a customer group with the same code exists', function (): void {
    $input = ['code' => CUSTOMER_GROUP_CODE, 'tax_class_id' => (string) $this->taxClass->getId()];
    $this->assertCommandIsSuccessful(customerGroupRun(new CustomerGroupCreate('customer:group:create'), $input));

    $result = customerGroupRun(new CustomerGroupCreate('customer:group:create'), $input);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain('Customer Group already exists');
    expect(Mage::getResourceModel('customer/group_collection')->addFieldToFilter('customer_group_code', CUSTOMER_GROUP_CODE)->getSize())->toBe(1);
});

it('lists every customer group with its tax class', function (): void {
    customerGroupSave((int) $this->taxClass->getId());

    $result = customerGroupRun(new CustomerGroupList('customer:group:list'));

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('NOT LOGGED IN')
        ->toMatch('/' . customerGroupLoad()->getId() . '\s*\|\s*' . CUSTOMER_GROUP_CODE . '\s*\|\s*' . $this->taxClass->getId() . '\s*\|\s*' . preg_quote($this->taxClass->getClassName(), '/') . '/');
});

it('deletes a customer group that has no customers', function (): void {
    $group = customerGroupSave((int) $this->taxClass->getId());

    $result = customerGroupRun(new CustomerGroupDelete('customer:group:delete'), ['group_id' => (string) $group->getId()]);

    $this->assertCommandIsSuccessful($result);
    expect(customerGroupLoad()->getId())->toBeNull();
});

it('fails to delete a customer group that has customers without --move-customers', function (): void {
    $group = customerGroupSave((int) $this->taxClass->getId());
    $customer = customerGroupCustomer($group);

    $result = customerGroupRun(new CustomerGroupDelete('customer:group:delete'), ['group_id' => (string) $group->getId()]);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain('because it has customers: 1');
    expect(customerGroupLoad()->getId())->not->toBeNull();
    expect(customerGroupOfCustomer($customer))->toBe((int) $group->getId());
});

it('deletes a customer group and moves its customers to the default group with --move-customers', function (): void {
    $group = customerGroupSave((int) $this->taxClass->getId());
    $customer = customerGroupCustomer($group);

    $result = customerGroupRun(new CustomerGroupDelete('customer:group:delete'), ['group_id' => (string) $group->getId(), '--move-customers' => true]);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('Customers moved to the default group: 1.');
    expect(customerGroupLoad()->getId())->toBeNull();
    expect(customerGroupOfCustomer($customer))->toBe(Mage::helper('customer')->getDefaultCustomerGroupId(Mage::app()->getStore(1)));
});

it('asks for the group and for moving its customers in the terminal', function (): void {
    $group = customerGroupSave((int) $this->taxClass->getId());
    $customer = customerGroupCustomer($group);

    $result = customerGroupRun(new CustomerGroupDelete('customer:group:delete'), [], [(string) $group->getId(), 'yes']);

    $this->assertCommandIsSuccessful($result);
    expect(customerGroupLoad()->getId())->toBeNull();
    expect(customerGroupOfCustomer($customer))->toBe(Mage::helper('customer')->getDefaultCustomerGroupId(Mage::app()->getStore(1)));
});

it('fails to delete a customer group that the store needs or that does not exist', function (Closure $groupId, string $message): void {
    $groupId = $groupId();

    $result = customerGroupRun(new CustomerGroupDelete('customer:group:delete'), ['group_id' => (string) $groupId]);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain($message);
    if ($groupId !== 999999) {
        expect(Mage::getModel('customer/group')->load($groupId)->getId())->not->toBeNull();
    }
})->with([
    'the default group' => [fn(): int => Mage::helper('customer')->getDefaultCustomerGroupId(Mage_Core_Model_App::ADMIN_STORE_ID), 'because it is the Default Group of a scope'],
    'NOT LOGGED IN' => [fn(): int => Mage_Customer_Model_Group::NOT_LOGGED_IN_ID, 'because the store uses it for guests'],
    'an unknown ID' => [fn(): int => 999999, 'Customer group not found with ID: 999999'],
]);
