<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Customer\Api\Customer as CustomerDto;
use Mage\Customer\Api\CustomerProcessor;
use Mage\Customer\Api\CustomerProvider;
use Mage\Customer\Api\GraphQL\CustomerQueryHandler;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin, the REST create and the admin GraphQL createCustomer create a customer
 * through Mage_Customer_Service_Customer, which picks the email (#1348). A customer with a random password gets
 * the link to set a password. The CLI command customer:create uses the same method. Each test gives the same input
 * to each transport and compares the outcome.
 */

function staffCreateEmail(): string
{
    return 'staff-create-' . bin2hex(random_bytes(4)) . '@example.com';
}

/**
 * @param array<string, mixed> $account
 * @return array{success: list<string>, errors: list<string>}
 */
function staffCreateViaAdmin(string $email, array $account): array
{
    Mage::unregister('current_customer');
    return AdminApiParity::admin(Mage_Adminhtml_CustomerController::class, 'save', ['account' => $account + [
        'email' => $email,
        'firstname' => 'Staff',
        'lastname' => 'Create',
        'website_id' => 1,
        'group_id' => 1,
    ]]);
}

function staffCreateViaRest(string $email, ?string $password = null): int
{
    $dto = new CustomerDto();
    $dto->email = $email;
    $dto->firstname = 'Staff';
    $dto->lastname = 'Create';
    $dto->password = $password;
    $result = AdminApiParity::api(AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers'), fn() => new CustomerProcessor(AdminApiParity::adminSecurity())->process($dto, new Post()));

    return $result['status'];
}

/**
 * @param array<string, mixed> $input
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function staffCreateViaAdminGraphQl(array $input, array $context = ['store_id' => 1]): array
{
    return new CustomerQueryHandler(new CustomerProvider(AdminApiParity::adminSecurity()))->handleCreateCustomer(['input' => $input], $context);
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->spy = new class extends Mage_Customer_Service_Customer {
        /** @var list<array{string, string, int}> */
        public array $emails = [];

        #[\Override]
        protected function sendNewAccountEmail(Mage_Customer_Model_Customer $customer, string $type, int $storeId, string $backUrl = ''): void
        {
            $this->emails[] = [$type, (string) $customer->getEmail(), $storeId];
        }

        #[\Override]
        protected function sendPasswordLinkEmail(Mage_Customer_Model_Customer $customer, int $storeId): void
        {
            $this->emails[] = ['password_link', (string) $customer->getEmail(), $storeId];
        }
    };
    Mage::unregister('_service/customer/customer');
    Mage::register('_service/customer/customer', $this->spy);
});

afterEach(function () {
    Mage::unregister('_service/customer/customer');
    Mage::unregister('current_customer');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::register('isSecureArea', true, true);
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'staff-create-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
});

it('sends the link to set a password when the password is random, on each transport', function () {
    $emails = [staffCreateEmail(), staffCreateEmail(), staffCreateEmail()];

    $admin = staffCreateViaAdmin($emails[0], ['password' => 'auto', 'sendemail_store_id' => 1]);
    $rest = staffCreateViaRest($emails[1]);
    staffCreateViaAdminGraphQl(['email' => $emails[2], 'firstName' => 'Staff', 'lastName' => 'Create']);

    expect($admin['errors'])->toBe([])
        ->and($rest)->toBe(200)
        ->and($this->spy->emails)->toBe([
            ['password_link', $emails[0], 1],
            ['password_link', $emails[1], 1],
            ['password_link', $emails[2], 1],
        ]);
});

it('sends the welcome email when staff gives the password, in the admin and on REST', function () {
    $emails = [staffCreateEmail(), staffCreateEmail()];

    $admin = staffCreateViaAdmin($emails[0], ['password' => 'Password123!', 'sendemail' => 1, 'sendemail_store_id' => 1]);
    $rest = staffCreateViaRest($emails[1], 'Password123!');

    expect($admin['errors'])->toBe([])
        ->and($rest)->toBe(200)
        ->and($this->spy->emails)->toBe([['registered', $emails[0], 1], ['registered', $emails[1], 1]]);
});

it('creates the customer of the admin GraphQL createCustomer on the store of the request, with a complete default address', function () {
    // The API starts in the admin store: the admin GraphQL createCustomer takes the store of its request, not the current one
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $fashion = Mage::app()->getStore('fashion');
    $email = staffCreateEmail();

    staffCreateViaAdminGraphQl([
        'email' => $email, 'firstName' => 'Staff', 'lastName' => 'Create',
        'street' => '1 Main St', 'city' => 'Los Angeles', 'regionId' => 12, 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
    ], ['store_id' => (int) $fashion->getId()]);

    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) $fashion->getWebsiteId())->loadByEmail($email);
    $address = $customer->getDefaultBillingAddress();
    expect((int) $customer->getWebsiteId())->toBe((int) $fashion->getWebsiteId())
        ->and((int) $customer->getStoreId())->toBe((int) $fashion->getId())
        ->and((int) $customer->getDefaultShipping())->toBe((int) $address->getId())
        ->and($address->getName())->toBe('Staff Create')
        ->and($address->getStreetFull())->toBe('1 Main St')
        ->and($address->getCity())->toBe('Los Angeles')
        ->and($address->getPostcode())->toBe('90210')
        ->and($address->getTelephone())->toBe('5550100')
        ->and($this->spy->emails)->toBe([['password_link', $email, (int) $fashion->getId()]]);
});

it('refuses an address that is not complete in the admin GraphQL createCustomer, and creates no customer', function () {
    $email = staffCreateEmail();

    expect(fn() => staffCreateViaAdminGraphQl(['email' => $email, 'firstName' => 'Staff', 'lastName' => 'Create', 'telephone' => '5550100']))
        ->toThrow(Mage_Core_Exception_Input::class);

    $customer = Mage::getModel('customer/customer')->setWebsiteId((int) Mage::app()->getStore(1)->getWebsiteId())->loadByEmail($email);
    expect($customer->getId())->toBeNull()
        ->and($this->spy->emails)->toBe([]);
});

it('refuses a customer group that does not exist in the admin, on REST and in the CLI', function () {
    $emails = [staffCreateEmail(), staffCreateEmail(), staffCreateEmail()];
    $message = Mage::helper('customer')->__('Customer group not found with ID: %s', 987654);

    $admin = staffCreateViaAdmin($emails[0], ['password' => 'Password123!', 'group_id' => 987654]);

    $dto = new CustomerDto();
    $dto->email = $emails[1];
    $dto->firstname = 'Staff';
    $dto->lastname = 'Create';
    $dto->password = 'Password123!';
    $dto->groupId = 987654;
    $rest = AdminApiParity::api(AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers'), fn() => new CustomerProcessor(AdminApiParity::adminSecurity())->process($dto, new Post()));

    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $command = new MahoCLI\Commands\CustomerCreate('customer:create');
    new Symfony\Component\Console\Application()->addCommand($command);
    $cli = new Symfony\Component\Console\Tester\CommandTester($command)->run([
        'email' => $emails[2],
        'password' => 'Password123!',
        'firstname' => 'Staff',
        'lastname' => 'Create',
        'website' => Mage::app()->getWebsite(1)->getCode(),
        'group' => '987654',
    ], interactive: false);

    $saved = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['in' => $emails])->getSize();
    expect($admin['errors'])->toBe([$message])
        ->and($rest['status'])->toBe(422)
        ->and($rest['errors'])->toBe([$message])
        ->and($rest['fields'])->toBe(['groupId'])
        ->and($cli->statusCode)->toBe(Symfony\Component\Console\Command\Command::INVALID)
        ->and($cli->getDisplay())->toContain($message)
        ->and($saved)->toBe(0);
});

it('sets the password that the admin gives for a saved customer, and emails the customer one time', function () {
    $email = staffCreateEmail();
    staffCreateViaAdmin($email, ['password' => 'Password123!']);
    $customer = Mage::getModel('customer/customer')->setWebsiteId(1)->loadByEmail($email);
    $spy = new class extends Mage_Customer_Service_Customer {
        /** @var list<string> */
        public array $notified = [];

        #[\Override]
        protected function sendPasswordChangedEmail(Mage_Customer_Model_Customer $customer): void
        {
            $this->notified[] = (string) $customer->getEmail();
        }
    };
    Mage::unregister('_service/customer/customer');
    Mage::register('_service/customer/customer', $spy);
    Mage::unregister('current_customer');

    // The test has no admin user in the session, so the store must not ask for the password of the admin
    $path = Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_REQUIRE_ADMIN_USER_TO_CHANGE_USER_PASSWORD;
    $required = Mage::getStoreConfig($path);
    Mage::app()->getStore()->setConfig($path, '0');
    try {
        $admin = AdminApiParity::admin(Mage_Adminhtml_CustomerController::class, 'save', [
            'customer_id' => (int) $customer->getId(),
            'account' => ['email' => $email, 'firstname' => 'Staff', 'lastname' => 'Changed', 'website_id' => 1, 'group_id' => 1, 'new_password' => 'Changed456!'],
        ]);
    } finally {
        Mage::app()->getStore()->setConfig($path, $required);
    }

    $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
    expect($admin['errors'])->toBe([])
        ->and($reloaded->getLastname())->toBe('Changed')
        ->and($reloaded->validatePassword('Changed456!'))->toBeTrue()
        ->and($spy->notified)->toBe([$email]);
});

it('tells the admin form that another customer has the email, with the rule of a save', function () {
    $email = staffCreateEmail();
    staffCreateViaAdmin($email, ['password' => 'Password123!']);
    /** @var Mage_Customer_Service_Customer $service */
    $service = Mage::getService('customer/customer');
    $probe = Mage::getModel('customer/customer')->setWebsiteId(1)->setEmail(strtoupper($email));

    $conflict = AdminApiParity::api(AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers'), fn() => $service->assertEmailNotInUse($probe));
    $second = staffCreateViaAdmin($email, ['password' => 'Password123!']);

    expect($conflict['status'])->toBe(409)
        ->and($second['errors'])->toBe($conflict['errors']);
});
