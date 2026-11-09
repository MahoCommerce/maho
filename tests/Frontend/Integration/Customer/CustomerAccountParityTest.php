<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Customer\Api\Address as AddressDto;
use Mage\Customer\Api\AddressProcessor;
use Mage\Customer\Api\Customer as CustomerDto;
use Mage\Customer\Api\CustomerProcessor;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Tests\Helpers\TransportParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront and the API register customers, log them in, change their passwords and save their addresses
 * through Mage_Customer_Service_Customer and Mage_Customer_Service_Address (#1348). Each test gives the same
 * input to the transports and compares the outcome.
 */

const CUSTOMER_PARITY_PASSWORD = 'Password123!';

/**
 * Post $params to $action of a storefront controller of the customer module, as the browser does.
 *
 * @param class-string<Mage_Core_Controller_Front_Action> $controllerClass
 * @return array{success: list<string>, errors: list<string>}
 */
function customerParityWeb(string $controllerClass, string $controller, string $action, array $params): array
{
    $params['form_key'] = Mage::getSingleton('core/session')->getFormKey();
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("/customer/$controller/$action", 'POST', $params));
    $request->setPathInfo("/customer/$controller/$action");
    $request->setRouteName('customer');
    $request->setControllerName($controller);
    $request->setActionName($action);
    $request->setControllerModule('Mage_Customer');
    $request->setDispatched(true);
    Mage::app()->setRequest($request);

    new $controllerClass($request, new Mage_Core_Controller_Response_Http())->{$action . 'Action'}();

    $messages = ['success' => [], 'errors' => []];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

function customerParitySecurity(int $customerId): Security
{
    $storage = new TokenStorage();
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private readonly TokenStorage $storage) {}

        #[\Override]
        public function get(string $id): mixed
        {
            return $id === 'security.authorization_checker'
                ? new AuthorizationChecker($this->storage, new AccessDecisionManager([new RoleVoter()]))
                : $this->storage;
        }

        #[\Override]
        public function has(string $id): bool
        {
            return in_array($id, ['security.token_storage', 'security.authorization_checker'], true);
        }
    });
}

function customerParityEmail(): string
{
    return 'customer-parity-' . bin2hex(random_bytes(4)) . '@example.com';
}

function customerParityCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setEmail(customerParityEmail())
        ->setFirstname('Parity')
        ->setLastname('Customer')
        ->setPassword(CUSTOMER_PARITY_PASSWORD);
    $customer->save();
    return $customer;
}

function customerParityReload(Mage_Customer_Model_Customer $customer): Mage_Customer_Model_Customer
{
    return Mage::getModel('customer/customer')->load($customer->getId());
}

/**
 * @return array<string, mixed>
 */
function customerParityAddress(): array
{
    return [
        'firstname' => 'Parity',
        'lastname' => 'Customer',
        'street' => ['10 Market Street'],
        'city' => 'San Francisco',
        'postcode' => '94105',
        'country_id' => 'US',
        'region_id' => (string) Mage::getModel('directory/region')->loadByCode('CA', 'US')->getId(),
        'telephone' => '555 0100',
    ];
}

function customerParityAddressDto(): AddressDto
{
    $fields = customerParityAddress();
    $dto = new AddressDto();
    $dto->firstname = $fields['firstname'];
    $dto->lastname = $fields['lastname'];
    $dto->street = $fields['street'];
    $dto->city = $fields['city'];
    $dto->postcode = $fields['postcode'];
    $dto->countryId = $fields['country_id'];
    $dto->regionId = (int) $fields['region_id'];
    $dto->telephone = $fields['telephone'];
    return $dto;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();
    // A message that an earlier test left in the session is not an outcome of this test
    Mage::getSingleton('customer/session')->getMessages(true);

    $store = Mage::app()->getStore();
    $store->setConfig('customer/account/enabled_in_frontend', '1');
    $store->setConfig('customer/create_account/confirm', '0');
    $store->setConfig('system/smtp/enabled', '');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
});

afterEach(function () {
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    Mage::getSingleton('customer/session')->logout();
    Mage::register('isSecureArea', true, true);
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'customer-parity-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
});

describe('registration', function () {
    it('creates the account on the storefront and the API, and counts both against one registration limit', function () {
        Mage::app()->getStore()->setConfig(Mage_Customer_Service_Customer::XML_PATH_REGISTER_RATE_LIMIT, '1');

        $email = customerParityEmail();
        $web = customerParityWeb(Mage_Customer_AccountController::class, 'account', 'createPost', [
            'firstname' => 'Parity',
            'lastname' => 'Customer',
            'email' => $email,
            'password' => CUSTOMER_PARITY_PASSWORD,
            'confirmation' => CUSTOMER_PARITY_PASSWORD,
        ]);
        $customer = Mage::getService('customer/customer')->getByEmail($email);

        $dto = new CustomerDto();
        $dto->email = customerParityEmail();
        $dto->firstname = 'Parity';
        $dto->lastname = 'Customer';
        $dto->password = CUSTOMER_PARITY_PASSWORD;
        $api = TransportParity::api(fn() => new CustomerProcessor()->process($dto, new Post()));

        expect($web['errors'])->toBe([])
            ->and($customer?->getData('password_created_at'))->not->toBeNull()
            ->and($api['status'])->toBe(429)
            ->and(Mage::getService('customer/customer')->getByEmail($dto->email))->toBeNull();
    });

    it('refuses a customer without a last name on the storefront and the API', function () {
        $email = customerParityEmail();
        $web = customerParityWeb(Mage_Customer_AccountController::class, 'account', 'createPost', [
            'firstname' => 'Parity',
            'email' => $email,
            'password' => CUSTOMER_PARITY_PASSWORD,
            'confirmation' => CUSTOMER_PARITY_PASSWORD,
        ]);

        $dto = new CustomerDto();
        $dto->email = customerParityEmail();
        $dto->firstname = 'Parity';
        $dto->password = CUSTOMER_PARITY_PASSWORD;
        $api = TransportParity::api(fn() => new CustomerProcessor()->process($dto, new Post()));

        expect($web['errors'])->not->toBe([])
            ->and(Mage::getService('customer/customer')->getByEmail($email))->toBeNull()
            ->and($api['status'])->toBe(422)
            ->and($api['message'])->toContain('The last name cannot be empty.')
            ->and(Mage::getService('customer/customer')->getByEmail($dto->email))->toBeNull();
    });
});

describe('login', function () {
    it('counts the storefront and the GraphQL login against one limit for each email', function () {
        $customer = customerParityCustomer();
        Mage::app()->getStore()->setConfig(Mage_Customer_Service_Customer::XML_PATH_LOGIN_RATE_LIMIT, '1');

        $web = customerParityWeb(Mage_Customer_AccountController::class, 'account', 'loginPost', [
            'login' => ['username' => $customer->getEmail(), 'password' => 'wrong-password'],
        ]);
        $api = TransportParity::api(fn() => new CustomerProcessor()->process(null, new Mutation(name: 'login'), [], [
            'args' => ['input' => ['email' => $customer->getEmail(), 'password' => CUSTOMER_PARITY_PASSWORD]],
        ]));

        expect($web['errors'])->toBe([Mage::helper('customer')->__('Invalid login or password.')])
            ->and($api['status'])->toBe(429);
    });

    it('answers a wrong password with an error on the storefront and a 401 on GraphQL', function () {
        $customer = customerParityCustomer();

        $web = customerParityWeb(Mage_Customer_AccountController::class, 'account', 'loginPost', [
            'login' => ['username' => $customer->getEmail(), 'password' => 'wrong-password'],
        ]);
        $api = TransportParity::api(fn() => new CustomerProcessor()->process(null, new Mutation(name: 'login'), [], [
            'args' => ['input' => ['email' => $customer->getEmail(), 'password' => 'wrong-password']],
        ]));

        expect($web['errors'])->toBe([Mage::helper('customer')->__('Invalid login or password.')])
            ->and(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse()
            ->and($api['status'])->toBe(401);
    });
});

describe('password change', function () {
    it('ends the other sessions and invalidates the reset link on the storefront and the API', function (string $transport) {
        $customer = customerParityCustomer();
        $customer->setRpToken('parity-token')->setRpTokenCreatedAt(Mage_Core_Model_Locale::nowUtc())->setPasswordCreatedAt(1)->save();
        $newPassword = 'NewPassword456!';

        if ($transport === 'web') {
            Mage::getSingleton('customer/session')->setCustomerAsLoggedIn(customerParityReload($customer));
            $result = customerParityWeb(Mage_Customer_AccountController::class, 'account', 'editPost', [
                'firstname' => 'Parity',
                'lastname' => 'Customer',
                'email' => $customer->getEmail(),
                'current_password' => CUSTOMER_PARITY_PASSWORD,
                'change_password' => '1',
                'password' => $newPassword,
                'confirmation' => $newPassword,
            ]);
            expect($result['errors'])->toBe([]);
        } else {
            $dto = new CustomerDto();
            $dto->currentPassword = CUSTOMER_PARITY_PASSWORD;
            $dto->newPassword = $newPassword;
            $result = TransportParity::api(fn() => new CustomerProcessor(customerParitySecurity((int) $customer->getId()))
                ->process($dto, new Post(name: 'change_password')));
            expect($result['status'])->toBe(200);
        }

        $reloaded = customerParityReload($customer);
        expect($reloaded->validatePassword($newPassword))->toBeTrue()
            ->and((int) $reloaded->getData('password_created_at'))->toBeGreaterThan(1)
            ->and($reloaded->getRpToken())->toBeNull();
    })->with(['web', 'api']);

    it('refuses a wrong current password on the storefront and the API', function () {
        $customer = customerParityCustomer();

        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn(customerParityReload($customer));
        $web = customerParityWeb(Mage_Customer_AccountController::class, 'account', 'editPost', [
            'firstname' => 'Parity',
            'lastname' => 'Customer',
            'email' => $customer->getEmail(),
            'current_password' => 'wrong-password',
        ]);

        $dto = new CustomerDto();
        $dto->currentPassword = 'wrong-password';
        $dto->newPassword = 'NewPassword456!';
        $api = TransportParity::api(fn() => new CustomerProcessor(customerParitySecurity((int) $customer->getId()))
            ->process($dto, new Post(name: 'change_password')));

        expect($web['errors'])->toBe(['Current password is incorrect.'])
            ->and($api['status'])->toBe(422)
            ->and($api['message'])->toBe('Current password is incorrect.');
    });
});

describe('email change', function () {
    it('needs the current password on the API, as the storefront form does', function () {
        $customer = customerParityCustomer();
        $oldEmail = $customer->getEmail();
        $update = fn(?string $currentPassword) => TransportParity::api(function () use ($customer, $currentPassword) {
            $dto = new CustomerDto();
            $dto->email = 'changed-' . $customer->getEmail();
            $dto->currentPassword = $currentPassword;
            return new CustomerProcessor(customerParitySecurity((int) $customer->getId()))
                ->process($dto, new Put(name: 'update_profile'));
        });

        $refused = $update(null);
        expect($refused['status'])->toBe(422)
            ->and($refused['fields'])->toBe(['currentPassword'])
            ->and(customerParityReload($customer)->getEmail())->toBe($oldEmail);

        expect($update(CUSTOMER_PARITY_PASSWORD)['status'])->toBe(200)
            ->and(customerParityReload($customer)->getEmail())->toBe('changed-' . $oldEmail);
    });
});

describe('addresses', function () {
    it('makes the first address of a customer the default billing and shipping address', function (string $transport) {
        $customer = customerParityCustomer();

        if ($transport === 'web') {
            Mage::getSingleton('customer/session')->setCustomerAsLoggedIn(customerParityReload($customer));
            $result = customerParityWeb(Mage_Customer_AddressController::class, 'address', 'formPost', customerParityAddress());
            expect($result['errors'])->toBe([]);
        } else {
            $result = TransportParity::api(fn() => new AddressProcessor(customerParitySecurity((int) $customer->getId()))
                ->process(customerParityAddressDto(), new Post(name: 'create_me_address')));
            expect($result['status'])->toBe(200);
        }

        $reloaded = customerParityReload($customer);
        $addressIds = array_values(array_map(fn($address) => (int) $address->getId(), $reloaded->getAddresses()));
        expect($addressIds)->toHaveCount(1)
            ->and((int) $reloaded->getDefaultBilling())->toBe($addressIds[0])
            ->and((int) $reloaded->getDefaultShipping())->toBe($addressIds[0]);
    })->with(['web', 'api']);

    it('answers for the address of another customer as for an address that does not exist', function () {
        $owner = customerParityCustomer();
        $address = Mage::getModel('customer/address')->addData(customerParityAddress())->setCustomerId((int) $owner->getId());
        Mage::getService('customer/address')->save($address);
        $other = customerParityCustomer();

        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn(customerParityReload($other));
        $web = customerParityWeb(Mage_Customer_AddressController::class, 'address', 'delete', ['id' => $address->getId()]);
        $api = TransportParity::api(fn() => new AddressProcessor(customerParitySecurity((int) $other->getId()))
            ->process(null, new Delete(name: 'delete_me_address'), ['id' => (int) $address->getId()]));

        expect($web['errors'])->toBe(['Address not found'])
            ->and($api['status'])->toBe(404)
            ->and($api['message'])->toBe('Address not found')
            ->and(Mage::getModel('customer/address')->load($address->getId())->getId())->not->toBeNull();
    });

    it('removes a deleted default address from the customer on the storefront and the API', function (string $transport) {
        $customer = customerParityCustomer();
        $address = Mage::getModel('customer/address')->addData(customerParityAddress())->setCustomerId((int) $customer->getId());
        Mage::getService('customer/address')->save($address);

        if ($transport === 'web') {
            Mage::getSingleton('customer/session')->setCustomerAsLoggedIn(customerParityReload($customer));
            $result = customerParityWeb(Mage_Customer_AddressController::class, 'address', 'delete', ['id' => $address->getId()]);
            expect($result['errors'])->toBe([]);
        } else {
            $result = TransportParity::api(fn() => new AddressProcessor(customerParitySecurity((int) $customer->getId()))
                ->process(null, new Delete(name: 'delete_me_address'), ['id' => (int) $address->getId()]));
            expect($result['status'])->toBe(200);
        }

        $reloaded = customerParityReload($customer);
        expect($reloaded->getData('default_billing'))->toBeNull()
            ->and($reloaded->getData('default_shipping'))->toBeNull();
    })->with(['web', 'api']);
});
