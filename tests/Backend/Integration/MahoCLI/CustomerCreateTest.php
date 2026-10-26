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

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

const CUSTOMER_CREATE_EMAIL = 'cli-create@example.com';

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
});

afterEach(function (): void {
    customerCreateCleanup();
    $this->store->setConfig(Mage_Customer_Model_Group::XML_PATH_DEFAULT_ID, $this->defaultGroupId);
    Mage::app()->setCurrentStore($this->previousStore);
});

it('saves the customer in the customer group that the user picks', function (string $answer, int $expectedGroupId): void {
    $this->store->setConfig(Mage_Customer_Model_Group::XML_PATH_DEFAULT_ID, '3');
    $command = new CustomerCreate('customer:create');
    new Application()->addCommand($command);
    $result = new CommandTester($command)->run([], [CUSTOMER_CREATE_EMAIL, 'Password123!', 'Cli', 'Create', (string) $this->website->getId(), $answer]);

    $this->assertCommandIsSuccessful($result);

    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $this->website->getId())->loadByEmail(CUSTOMER_CREATE_EMAIL);
    expect((int) $customer->getData('group_id'))->toBe($expectedGroupId);
})->with([
    'a group' => ['2', 2],
    'no answer, default group of the website' => ['', 3],
]);
