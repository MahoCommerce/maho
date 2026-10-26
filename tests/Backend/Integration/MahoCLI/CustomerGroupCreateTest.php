<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CustomerGroupCreate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tester\ExecutionResult;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

const CUSTOMER_GROUP_CREATE_CODE = 'CLI Group';

function customerGroupCreateRun(string $code, string $taxClassId): ExecutionResult
{
    $command = new CustomerGroupCreate('customer:group:create');
    new Application()->addCommand($command);
    return new CommandTester($command)->run([], [$code, $taxClassId]);
}

function customerGroupCreateCleanup(): void
{
    foreach (Mage::getResourceModel('customer/group_collection')->addFieldToFilter('customer_group_code', CUSTOMER_GROUP_CREATE_CODE) as $group) {
        $group->delete();
    }
}

beforeEach(function (): void {
    customerGroupCreateCleanup();
    $this->taxClassId = (int) Mage::getResourceModel('tax/class_collection')
        ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)
        ->getFirstItem()
        ->getId();
});

afterEach(function (): void {
    customerGroupCreateCleanup();
});

it('saves a customer group with the code and the tax class that the user gives', function (): void {
    $result = customerGroupCreateRun(CUSTOMER_GROUP_CREATE_CODE, (string) $this->taxClassId);

    $this->assertCommandIsSuccessful($result);
    $group = Mage::getModel('customer/group')->load(CUSTOMER_GROUP_CREATE_CODE, 'customer_group_code');
    expect($group->getId())->not->toBeNull()
        ->and((int) $group->getData('tax_class_id'))->toBe($this->taxClassId);
});

it('fails when a customer group with the same code exists', function (): void {
    $this->assertCommandIsSuccessful(customerGroupCreateRun(CUSTOMER_GROUP_CREATE_CODE, (string) $this->taxClassId));

    $result = customerGroupCreateRun(CUSTOMER_GROUP_CREATE_CODE, (string) $this->taxClassId);

    $this->assertCommandFailed($result);
    expect($result->getDisplay())->toContain('Customer Group already exists');
    expect(Mage::getResourceModel('customer/group_collection')->addFieldToFilter('customer_group_code', CUSTOMER_GROUP_CREATE_CODE)->getSize())->toBe(1);
});
