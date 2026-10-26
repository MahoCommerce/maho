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

function customerGroupRun(Command $command, array $inputs = [], array $options = []): ExecutionResult
{
    new Application()->addCommand($command);
    return new CommandTester($command)->run($options, $inputs);
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

it('saves a customer group with the code and the tax class that the user gives', function (): void {
    $result = customerGroupRun(new CustomerGroupCreate('customer-group:create'), [CUSTOMER_GROUP_CODE, (string) $this->taxClass->getId()]);

    $this->assertCommandIsSuccessful($result);
    $group = customerGroupLoad();
    expect($group->getId())->not->toBeNull()
        ->and((int) $group->getData('tax_class_id'))->toBe((int) $this->taxClass->getId());
});

it('fails when a customer group with the same code exists', function (): void {
    $inputs = [CUSTOMER_GROUP_CODE, (string) $this->taxClass->getId()];
    $this->assertCommandIsSuccessful(customerGroupRun(new CustomerGroupCreate('customer-group:create'), $inputs));

    $result = customerGroupRun(new CustomerGroupCreate('customer-group:create'), $inputs);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain('Customer Group already exists');
    expect(Mage::getResourceModel('customer/group_collection')->addFieldToFilter('customer_group_code', CUSTOMER_GROUP_CODE)->getSize())->toBe(1);
});

it('lists every customer group with its tax class', function (): void {
    customerGroupSave((int) $this->taxClass->getId());

    $result = customerGroupRun(new CustomerGroupList('customer-group:list'));

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('NOT LOGGED IN')
        ->toMatch('/' . customerGroupLoad()->getId() . '\s*\|\s*' . CUSTOMER_GROUP_CODE . '\s*\|\s*' . preg_quote($this->taxClass->getClassName(), '/') . '/');
});

it('deletes a customer group that has no customers', function (): void {
    $group = customerGroupSave((int) $this->taxClass->getId());

    $result = customerGroupRun(new CustomerGroupDelete('customer-group:delete'), [(string) $group->getId()]);

    $this->assertCommandIsSuccessful($result);
    expect(customerGroupLoad()->getId())->toBeNull();
});

it('fails to delete a customer group that has customers without --move-customers', function (): void {
    $group = customerGroupSave((int) $this->taxClass->getId());
    $customer = customerGroupCustomer($group);

    $result = customerGroupRun(new CustomerGroupDelete('customer-group:delete'), [(string) $group->getId()]);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain('because it has customers: 1');
    expect(customerGroupLoad()->getId())->not->toBeNull();
    expect(customerGroupOfCustomer($customer))->toBe((int) $group->getId());
});

it('deletes a customer group and moves its customers to the default group with --move-customers', function (): void {
    $group = customerGroupSave((int) $this->taxClass->getId());
    $customer = customerGroupCustomer($group);

    $result = customerGroupRun(new CustomerGroupDelete('customer-group:delete'), [(string) $group->getId()], ['--move-customers' => true]);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('Customers moved to the default group: 1.');
    expect(customerGroupLoad()->getId())->toBeNull();
    expect(customerGroupOfCustomer($customer))->toBe(Mage::helper('customer')->getDefaultCustomerGroupId(Mage::app()->getStore(1)));
});

it('fails to delete the customer group that a scope uses as the default group', function (): void {
    $defaultGroupId = Mage::helper('customer')->getDefaultCustomerGroupId(Mage_Core_Model_App::ADMIN_STORE_ID);

    $result = customerGroupRun(new CustomerGroupDelete('customer-group:delete'), [(string) $defaultGroupId]);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain('because it is the Default Group of a scope');
    expect(Mage::getModel('customer/group')->load($defaultGroupId)->getId())->not->toBeNull();
});
