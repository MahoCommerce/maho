<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use Mage\Customer\Api\Customer as CustomerDto;
use Mage\Customer\Api\CustomerProcessor;
use Mage\Customer\Api\CustomerProvider;
use Mage\Customer\Api\GraphQL\CustomerQueryHandler;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin, the REST and GraphQL create, the GraphQL quickCreate and the POS createCustomer create a customer
 * through Mage_Customer_Service_Customer, which picks the email (#1348). A customer with a random password gets
 * the link to set a password. Each test gives the same input to each transport and compares the outcome.
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
 */
function staffCreateViaQuickCreate(array $input): int
{
    $result = AdminApiParity::api(AdminApiParity::jsonRequest('POST', '/api/graphql'), fn() => new CustomerProcessor(AdminApiParity::adminSecurity())
        ->process(null, new Mutation(name: 'quickCreate'), [], ['args' => ['input' => $input]]));

    return $result['status'];
}

/**
 * @param array<string, mixed> $input
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function staffCreateViaPos(array $input, array $context = ['store_id' => 1]): array
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
    $emails = [staffCreateEmail(), staffCreateEmail(), staffCreateEmail(), staffCreateEmail()];

    $admin = staffCreateViaAdmin($emails[0], ['password' => 'auto', 'sendemail_store_id' => 1]);
    $rest = staffCreateViaRest($emails[1]);
    $quick = staffCreateViaQuickCreate(['email' => $emails[2], 'firstname' => 'Staff', 'lastname' => 'Create']);
    staffCreateViaPos(['email' => $emails[3], 'firstName' => 'Staff', 'lastName' => 'Create']);

    expect($admin['errors'])->toBe([])
        ->and($rest)->toBe(200)
        ->and($quick)->toBe(200)
        ->and($this->spy->emails)->toBe([
            ['password_link', $emails[0], 1],
            ['password_link', $emails[1], 1],
            ['password_link', $emails[2], 1],
            ['password_link', $emails[3], 1],
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

it('gives the same customer and address to the GraphQL quickCreate and the POS createCustomer', function () {
    // The API starts in the admin store: the POS createCustomer takes the store of its request, not the current one
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $fashion = Mage::app()->getStore('fashion');
    [$quickEmail, $posEmail] = [staffCreateEmail(), staffCreateEmail()];

    Mage::app()->setCurrentStore((int) $fashion->getId());
    staffCreateViaQuickCreate(['email' => $quickEmail, 'firstname' => 'Staff', 'lastname' => 'Create', 'telephone' => '5550100']);
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    staffCreateViaPos(['email' => $posEmail, 'firstName' => 'Staff', 'lastName' => 'Create', 'telephone' => '5550100'], ['store_id' => (int) $fashion->getId()]);

    $shape = function (string $email): array {
        $customer = Mage::getModel('customer/customer')->setWebsiteId((int) Mage::app()->getStore('fashion')->getWebsiteId())->loadByEmail($email);
        $address = $customer->getDefaultBillingAddress();
        return [
            'website_id' => $customer->getWebsiteId(),
            'store_id' => $customer->getStoreId(),
            'shipping' => (int) $customer->getDefaultShipping() === (int) $address?->getId(),
            'name' => $address?->getName(),
            'telephone' => $address?->getTelephone(),
            'street' => $address?->getStreetFull(),
            'city' => (string) $address?->getCity(),
            'postcode' => (string) $address?->getPostcode(),
            'country' => $address?->getCountryId(),
        ];
    };

    expect($shape($posEmail))->toBe($shape($quickEmail))
        ->and($shape($posEmail))->toMatchArray([
            'website_id' => (int) $fashion->getWebsiteId(),
            'store_id' => (int) $fashion->getId(),
            'shipping' => true,
            'name' => 'Staff Create',
            'telephone' => '5550100',
            'city' => '',
            'postcode' => '',
        ])
        ->and($this->spy->emails)->toBe([
            ['password_link', $quickEmail, (int) $fashion->getId()],
            ['password_link', $posEmail, (int) $fashion->getId()],
        ]);
});
