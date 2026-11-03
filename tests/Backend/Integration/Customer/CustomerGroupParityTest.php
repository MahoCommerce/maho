<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\Customer\Api\CustomerGroup;
use Mage\Customer\Api\CustomerGroupProcessor;
use MahoCLI\Commands\CustomerGroupCreate;
use MahoCLI\Commands\CustomerGroupDelete;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\TransportParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page, the API and the CLI save and delete a customer group through Mage_Customer_Service_Group (#1348).
 * Each test gives the same input to each transport and compares the outcome.
 */

const GROUP_PARITY_CODE = 'Parity Group';

/**
 * @return array{success: list<string>, errors: list<string>}
 */
function groupParityAdminSave(string $code, int $taxClassId): array
{
    return TransportParity::admin(Mage_Adminhtml_Customer_GroupController::class, 'save', ['code' => $code, 'tax_class' => $taxClassId]);
}

/**
 * @return array{status: int, message: string, fields: list<string>, result: mixed}
 */
function groupParityApiSave(string $code, int $taxClassId): array
{
    $dto = new CustomerGroup();
    $dto->code = $code;
    $dto->taxClassId = $taxClassId;
    return TransportParity::api(fn() => new CustomerGroupProcessor(TransportParity::adminSecurity())
        ->process($dto, new Post(class: CustomerGroup::class)));
}

/**
 * @return array{status: int, output: string}
 */
function groupParityCli(Command $command, array $input): array
{
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $status = $tester->execute($input, ['interactive' => false]);
    return ['status' => $status, 'output' => $tester->getDisplay()];
}

/**
 * @return array{status: int, output: string}
 */
function groupParityCliSave(string $code, int $taxClassId): array
{
    return groupParityCli(new CustomerGroupCreate('customer:group:create'), ['code' => $code, 'tax_class_id' => (string) $taxClassId]);
}

function groupParityCount(): int
{
    return Mage::getResourceModel('customer/group_collection')->addFieldToFilter('customer_group_code', GROUP_PARITY_CODE)->getSize();
}

function groupParityCleanup(): void
{
    foreach (Mage::getResourceModel('customer/group_collection')->addFieldToFilter('customer_group_code', GROUP_PARITY_CODE) as $group) {
        $group->delete();
    }
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    groupParityCleanup();
    $this->taxClassId = (int) Mage::getResourceModel('tax/class_collection')
        ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)
        ->getFirstItem()
        ->getId();
});

afterEach(function (): void {
    groupParityCleanup();
});

it('saves the same group on each transport', function (): void {
    expect(groupParityAdminSave(GROUP_PARITY_CODE, $this->taxClassId)['errors'])->toBe([]);
    $group = Mage::getModel('customer/group')->load(GROUP_PARITY_CODE, 'customer_group_code');
    expect((int) $group->getData('tax_class_id'))->toBe($this->taxClassId);
    groupParityCleanup();

    $api = groupParityApiSave('  ' . GROUP_PARITY_CODE . '  ', $this->taxClassId);
    expect($api['status'])->toBe(200)
        ->and($api['result']->code)->toBe(GROUP_PARITY_CODE);
    groupParityCleanup();

    expect(groupParityCliSave(GROUP_PARITY_CODE, $this->taxClassId)['status'])->toBe(Command::SUCCESS)
        ->and(groupParityCount())->toBe(1);
});

it('rejects the same input with the same messages on each transport', function (Closure $input): void {
    [$code, $taxClassId, $field, $message] = $input($this->taxClassId);

    $admin = groupParityAdminSave($code, $taxClassId);
    $api = groupParityApiSave($code, $taxClassId);
    $cli = groupParityCliSave($code, $taxClassId);

    expect($admin)->toBe(['success' => [], 'errors' => [$message]])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe([$field])
        ->and($api['message'])->toBe($message)
        ->and($cli['status'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message)
        ->and(groupParityCount())->toBe(0);
})->with([
    'no code' => [fn(int $taxClassId): array => ['  ', $taxClassId, 'code', 'Customer group code is required.']],
    'a code that is too long' => [fn(int $taxClassId): array => [str_repeat('x', 33), $taxClassId, 'code', 'Customer group code must not exceed 32 characters.']],
    'no tax class' => [fn(int $taxClassId): array => [GROUP_PARITY_CODE, 0, 'taxClassId', 'Customer tax class is required.']],
    'a product tax class' => [function (int $taxClassId): array {
        $productTaxClassId = (int) Mage::getResourceModel('tax/class_collection')
            ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
            ->getFirstItem()
            ->getId();
        return [GROUP_PARITY_CODE, $productTaxClassId, 'taxClassId', "Customer tax class not found with ID: $productTaxClassId"];
    }],
]);

it('refuses a code that another group uses on each transport', function (): void {
    Mage::getModel('customer/group')->setCode(GROUP_PARITY_CODE)->setTaxClassId($this->taxClassId)->save();

    expect(groupParityAdminSave(GROUP_PARITY_CODE, $this->taxClassId)['errors'])->toBe(['Customer Group already exists.'])
        ->and(groupParityApiSave(GROUP_PARITY_CODE, $this->taxClassId))->toMatchArray(['status' => 409, 'message' => 'Customer Group already exists.'])
        ->and(groupParityCliSave(GROUP_PARITY_CODE, $this->taxClassId)['status'])->toBe(Command::FAILURE)
        ->and(groupParityCount())->toBe(1);
});

it('refuses to rename NOT LOGGED IN, as the admin form does', function (): void {
    $service = Mage::getService('customer/group');
    $group = $service->getById(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    $code = $group->getCode();

    expect(fn() => $service->save($group->setCode(GROUP_PARITY_CODE)))
        ->toThrow(Mage_Core_Exception_Conflict::class, 'The NOT LOGGED IN group cannot be renamed.')
        ->and($service->getById(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)->getCode())->toBe($code);
});

it('refuses to delete the default group and an unknown group on each transport', function (Closure $input): void {
    [$groupId, $status, $message] = $input();

    $admin = TransportParity::admin(Mage_Adminhtml_Customer_GroupController::class, 'delete', ['id' => $groupId]);
    $api = TransportParity::api(fn() => new CustomerGroupProcessor(TransportParity::adminSecurity())
        ->process(new CustomerGroup(), new Delete(class: CustomerGroup::class), ['id' => $groupId]));
    $cli = groupParityCli(new CustomerGroupDelete('customer:group:delete'), ['group_id' => (string) $groupId]);

    expect($admin)->toBe(['success' => [], 'errors' => [$message]])
        ->and($api)->toMatchArray(['status' => $status, 'message' => $message])
        ->and($cli['status'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain($message);
})->with([
    'the default group' => [function (): array {
        $group = Mage::getModel('customer/group')->load(Mage::helper('customer')->getDefaultCustomerGroupId(Mage_Core_Model_App::ADMIN_STORE_ID));
        return [(int) $group->getId(), 409, "The group \"{$group->getCode()}\" cannot be deleted"];
    }],
    'an unknown ID' => [fn(): array => [9999, 404, 'Customer group not found with ID: 9999']],
]);
