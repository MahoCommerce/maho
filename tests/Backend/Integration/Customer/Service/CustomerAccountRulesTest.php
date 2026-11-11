<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Customer\Api\AddressProcessor;
use Mage\Customer\Api\AddressProvider;
use Mage\Customer\Api\Customer as CustomerDto;
use Mage\Customer\Api\CustomerProcessor;
use Maho\ApiPlatform\Security\ApiUser;
use Mage_Customer_Model_Channel as Channel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CaptchaStub;

uses(Tests\MahoBackendTestCase::class);

/**
 * The rules of a customer account are in Mage_Customer_Service_Customer, so that the storefront, the admin, the
 * API and the CLI get the same answer (#1348): the active check of each login, the CAPTCHA of the API, the
 * validation of a staff save, the switch of the customer accounts, the confirmation, 2FA and the password.
 */

const ACCOUNT_RULES_PASSWORD = 'Password123!';

function accountRulesEmail(): string
{
    return 'account-rules-' . bin2hex(random_bytes(4)) . '@example.com';
}

/**
 * @param array<string, mixed> $data
 */
function accountRulesCustomer(array $data = []): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setGroupId(1)
        ->setEmail(accountRulesEmail())
        ->setFirstname('Account')
        ->setLastname('Rules')
        ->setPassword(ACCOUNT_RULES_PASSWORD);
    $customer->addData($data)->save();

    return Mage::getModel('customer/customer')->load($customer->getId());
}

function accountRulesNewCustomer(): Mage_Customer_Model_Customer
{
    return Mage::getModel('customer/customer')
        ->setEmail(accountRulesEmail())
        ->setFirstname('Account')
        ->setLastname('Rules')
        ->setPassword(ACCOUNT_RULES_PASSWORD);
}

function accountRulesSecurity(ApiUser $user): Security
{
    $storage = new TokenStorage();
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private TokenStorage $storage) {}

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

/**
 * Return the ID of a store view of a website that is not the website 1, or null when the store has one website.
 */
function accountRulesOtherWebsiteStoreId(): ?int
{
    foreach (Mage::app()->getStores() as $store) {
        if ((int) $store->getWebsiteId() !== 1) {
            return (int) $store->getId();
        }
    }
    return null;
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig('system/smtp/enabled', '');
    $store->setConfig('customer/create_account/confirm', '0');
    foreach (['customer_register', 'forgot_password', 'confirmation_link', 'change_password', 'customer_login', 'auth_token_ip', 'reset_password'] as $key) {
        $store->setConfig('system/rate_limit/' . $key, '0');
    }
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    $this->spy = new class extends Mage_Customer_Service_Customer {
        /** @var list<array{string, string}> */
        public array $emails = [];

        #[\Override]
        protected function sendNewAccountEmail(Mage_Customer_Model_Customer $customer, string $type, int $storeId, string $backUrl = ''): void
        {
            $this->emails[] = [$type, (string) $customer->getEmail()];
        }

        #[\Override]
        protected function sendPasswordChangedEmail(Mage_Customer_Model_Customer $customer): void
        {
            $this->emails[] = ['password_changed', (string) $customer->getEmail()];
        }
    };
    Mage::unregister('_service/customer/customer');
    Mage::register('_service/customer/customer', $this->spy);
});

afterEach(function () {
    Mage::unregister('_service/customer/customer');
    Mage::unregister('_helper/apiplatform');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    Mage::register('isSecureArea', true, true);
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'account-rules-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
});

it('refuses the password login and the token refresh of a customer that is not active', function () {
    $customer = accountRulesCustomer(['is_active' => 0]);
    $active = accountRulesCustomer();

    expect(fn() => $this->spy->authenticate((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))
        ->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->spy->authenticateInAnyWebsite((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))
        ->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->spy->requireActiveById((int) $customer->getId()))
        ->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and((int) $this->spy->authenticate((string) $active->getEmail(), ACCOUNT_RULES_PASSWORD, 1)->getId())->toBe((int) $active->getId())
        ->and((int) $this->spy->requireActiveById((int) $active->getId())->getId())->toBe((int) $active->getId());
});

it('refuses a wrong password of a customer that is not active with the error of a wrong password', function () {
    $customer = accountRulesCustomer(['is_active' => 0]);

    try {
        $this->spy->authenticate((string) $customer->getEmail(), 'WrongPassword1!', 1);
        $code = null;
    } catch (Mage_Core_Exception $e) {
        $code = $e->getCode();
    }

    expect($code)->toBe(Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD);
});

it('asks the CAPTCHA for a registration of the API channel only', function () {
    $captcha = CaptchaStub::install();

    $missing = fn() => $this->spy->register(accountRulesNewCustomer(), 1, channel: Channel::Api);
    expect($missing)->toThrow(Mage_Core_Exception_Input::class);

    $api = $this->spy->register(accountRulesNewCustomer(), 1, channel: Channel::Api, captchaToken: CaptchaStub::SOLVED);
    $checks = count($captcha->checks);
    $web = $this->spy->register(accountRulesNewCustomer(), 1);

    expect((int) $api->getId())->toBeGreaterThan(0)
        ->and((int) $web->getId())->toBeGreaterThan(0)
        ->and($checks)->toBe(2)
        ->and(count($captcha->checks))->toBe(2);
});

it('asks the CAPTCHA for a password reset email and a confirmation email of the API channel only', function () {
    $captcha = CaptchaStub::install();
    $email = accountRulesEmail();

    expect(fn() => $this->spy->sendPasswordResetLink($email, 1, Channel::Api))->toThrow(Mage_Core_Exception_Input::class)
        ->and(fn() => $this->spy->sendConfirmationLink($email, 1, Channel::Api))->toThrow(Mage_Core_Exception_Input::class);

    $this->spy->sendPasswordResetLink($email, 1, Channel::Api, CaptchaStub::SOLVED);
    $this->spy->sendConfirmationLink($email, 1, Channel::Api, CaptchaStub::SOLVED);
    $this->spy->sendPasswordResetLink($email, 1, Channel::Admin);
    $this->spy->sendConfirmationLink($email, 1, Channel::Admin);
    $this->spy->sendPasswordResetLink($email, 1);
    $this->spy->sendConfirmationLink($email, 1);

    expect(count($captcha->checks))->toBe(4);
});

it('asks the CAPTCHA on POST /customers and on the forgot password operation, and not for an admin token', function () {
    CaptchaStub::install();
    $register = function (?Security $security, ?string $captchaToken): int {
        $dto = new CustomerDto();
        $dto->email = accountRulesEmail();
        $dto->firstname = 'Account';
        $dto->lastname = 'Rules';
        $dto->password = ACCOUNT_RULES_PASSWORD;
        $dto->captchaToken = $captchaToken;
        return AdminApiParity::api(AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers'), fn() => new CustomerProcessor($security)->process($dto, new Post()))['status'];
    };
    $forgot = function (?Security $security, array $input): array {
        return AdminApiParity::api(
            AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers/forgot-password'),
            fn() => new CustomerProcessor($security)->process(new CustomerDto(), new Post(name: 'forgot_password_rest'), [], ['args' => ['input' => $input]]),
        );
    };
    $email = accountRulesEmail();

    $refused = $forgot(null, ['email' => $email]);

    expect($register(null, null))->toBe(422)
        ->and($register(null, CaptchaStub::SOLVED))->toBe(200)
        ->and($register(AdminApiParity::adminSecurity(), null))->toBe(200)
        ->and($refused['status'])->toBe(422)
        ->and($refused['fields'])->toBe(['captchaToken'])
        ->and($forgot(null, ['email' => $email, 'captchaToken' => CaptchaStub::SOLVED])['status'])->toBe(200)
        ->and($forgot(AdminApiParity::adminSecurity(), ['email' => $email])['status'])->toBe(200);
});

it('validates the customer that staff saves', function () {
    $customer = accountRulesCustomer();

    $empty = fn() => $this->spy->save($customer->setFirstname(''));
    expect($empty)->toThrow(Mage_Core_Exception_Input::class);

    $dto = new CustomerDto();
    $dto->firstname = '';
    $rest = AdminApiParity::api(
        AdminApiParity::jsonRequest('PUT', '/api/rest/v2/customers/' . $customer->getId()),
        fn() => new CustomerProcessor(AdminApiParity::adminSecurity())->process($dto, new Put(), ['id' => (int) $customer->getId()]),
    );

    expect($rest['status'])->toBe(422)
        ->and(Mage::getModel('customer/customer')->load($customer->getId())->getFirstname())->toBe('Account');
});

it('changes the group of a customer whose stored profile is not valid when the caller skips the profile check', function () {
    $customer = accountRulesCustomer();
    $customer->setData('firstname', '')->getResource()->saveAttribute($customer, 'firstname');
    $customer = Mage::getModel('customer/customer')->load($customer->getId());

    expect(fn() => $this->spy->save($customer->setGroupId(2)))->toThrow(Mage_Core_Exception_Input::class);
    $this->spy->save($customer->setGroupId(2), validateProfile: false);

    expect((int) Mage::getModel('customer/customer')->load($customer->getId())->getGroupId())->toBe(2);
});

it('applies the switch of the customer accounts to a registration and a login, and not to staff', function () {
    $customer = accountRulesCustomer();
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Customer::XML_PATH_ACCOUNTS_ENABLED, '0');

    expect(fn() => $this->spy->register(accountRulesNewCustomer(), 1))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->spy->authenticate((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->spy->authenticateInAnyWebsite((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => Mage::getService('sociallogin/identity')->authenticate('google', 'token', 1, Maho_SocialLogin_Model_Channel::Api))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and((int) $this->spy->create(accountRulesNewCustomer(), 1)->getId())->toBeGreaterThan(0);
});

it('sends the confirmed email when a key confirms the account, and one time only', function () {
    $customer = accountRulesCustomer(['confirmation' => 'key-123']);

    expect(fn() => $this->spy->confirm((int) $customer->getId(), 'wrong'))->toThrow(Mage_Core_Exception::class);
    $confirmed = $this->spy->confirm((int) $customer->getId(), 'key-123');
    $again = $this->spy->confirm((int) $customer->getId(), 'key-123');

    expect($confirmed->getIsJustConfirmed())->toBeTrue()
        ->and($again->getIsJustConfirmed())->not->toBeTrue()
        ->and($this->spy->emails)->toBe([['confirmed', (string) $customer->getEmail()]])
        ->and(Mage::getModel('customer/customer')->load($customer->getId())->getConfirmation())->toBeEmpty();
});

it('sends the confirmed email of a staff save only when the save confirms the account', function () {
    $pending = accountRulesCustomer(['confirmation' => 'key-123']);
    $confirmed = accountRulesCustomer();

    $this->spy->save($confirmed->setLastname('Changed'), 1);
    $this->spy->save($pending->setConfirmation(null));
    $pending = accountRulesCustomer(['confirmation' => 'key-456']);
    $this->spy->save($pending->setConfirmation(null), 1);

    expect($this->spy->emails)->toBe([['confirmed', (string) $pending->getEmail()]]);
});

it('sends the confirmation link again with the same answer for each address, and with a limit', function () {
    $pending = accountRulesCustomer(['confirmation' => 'key-123']);
    $confirmed = accountRulesCustomer();
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Customer::XML_PATH_CONFIRMATION_LINK_RATE_LIMIT, '3');

    $this->spy->sendConfirmationLink((string) $pending->getEmail(), 1);
    $this->spy->sendConfirmationLink((string) $confirmed->getEmail(), 1);
    $this->spy->sendConfirmationLink(accountRulesEmail(), 1);

    expect($this->spy->emails)->toBe([['confirmation', (string) $pending->getEmail()]])
        ->and(fn() => $this->spy->sendConfirmationLink((string) $pending->getEmail(), 1))->toThrow(Mage_Core_Exception_TooManyRequests::class)
        ->and(fn() => $this->spy->sendConfirmationLink('not-an-email', 1))->toThrow(Mage_Core_Exception_Input::class);
});

it('ships a default for the limit of the confirmation link', function () {
    expect((int) Mage::getConfig()->getNode('default/system/rate_limit/confirmation_link'))->toBeGreaterThan(0);
});

it('turns 2FA on and off with the current password and the limit of the password change', function () {
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_ALLOW_2FA, '1');
    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_REQUIRE_2FA, '0');
    $customer = accountRulesCustomer();

    $secret = $this->spy->getSecondFactorSecret($customer);
    $code = \OTPHP\TOTP::createFromSecret($secret)->now();

    expect(fn() => $this->spy->setSecondFactor($customer, 'WrongPassword1!', true, $code))->toThrow(Mage_Core_Exception::class, 'Current password is incorrect.')
        ->and(fn() => $this->spy->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, true, '000000x'))->toThrow(Mage_Core_Exception::class)
        ->and((bool) Mage::getModel('customer/customer')->load($customer->getId())->getTwofaEnabled())->toBeFalse();

    $this->spy->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, true, $code);
    expect((bool) Mage::getModel('customer/customer')->load($customer->getId())->getTwofaEnabled())->toBeTrue()
        ->and(fn() => $this->spy->getSecondFactorSecret($customer))->toThrow(Mage_Core_Exception_Conflict::class);

    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_REQUIRE_2FA, '1');
    expect(fn() => $this->spy->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, false))->toThrow(Mage_Core_Exception_Forbidden::class);

    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_REQUIRE_2FA, '0');
    $this->spy->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, false);
    $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
    expect((bool) $reloaded->getTwofaEnabled())->toBeFalse()
        ->and($reloaded->getTwofaSecret())->toBeNull();

    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_CHANGE_PASSWORD_RATE_LIMIT, '1');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    try {
        $this->spy->setSecondFactor($customer, 'WrongPassword1!', false);
    } catch (Mage_Core_Exception) {
    }
    expect(fn() => $this->spy->setSecondFactor($customer, 'WrongPassword1!', false))->toThrow(Mage_Core_Exception_TooManyRequests::class);

    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_ALLOW_2FA, '0');
    expect(fn() => $this->spy->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, false))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
});

it('emails the customer about a password of staff only with notify', function () {
    $customer = accountRulesCustomer();

    $this->spy->setPassword($customer, 'NewPassword123!');
    $silent = $this->spy->emails;
    $this->spy->setPassword($customer, 'OtherPassword123!', notify: true);

    expect($silent)->toBe([])
        ->and($this->spy->emails)->toBe([['password_changed', (string) $customer->getEmail()]]);
});

it('checks the current password of an account edit when the transport requires it', function () {
    $customer = accountRulesCustomer();

    $this->spy->updateAccount($customer->setFirstname('Api'), null, requirePassword: false);
    $required = fn() => $this->spy->updateAccount($customer->setFirstname('Storefront'), '', requirePassword: true);
    expect($required)->toThrow(Mage_Core_Exception::class, 'Current password is incorrect.');
    $given = fn() => $this->spy->updateAccount($customer->setFirstname('Given'), 'WrongPassword1!');
    expect($given)->toThrow(Mage_Core_Exception::class, 'Current password is incorrect.');
    $email = fn() => $this->spy->updateAccount($customer->setFirstname('Api')->setEmail(accountRulesEmail()));
    expect($email)->toThrow(Mage_Core_Exception_Input::class);

    $this->spy->updateAccount($customer->setEmail((string) $customer->getOrigData('email'))->setFirstname('Storefront'), ACCOUNT_RULES_PASSWORD, requirePassword: true);

    expect(Mage::getModel('customer/customer')->load($customer->getId())->getFirstname())->toBe('Storefront');
});

it('gives a service token the addresses of a customer of its websites only', function () {
    $otherStoreId = accountRulesOtherWebsiteStoreId();
    if ($otherStoreId === null) {
        $this->markTestSkipped('The store has one website.');
    }
    $customer = accountRulesCustomer();
    $address = Mage::getModel('customer/address')
        ->setCustomerId((int) $customer->getId())
        ->setFirstname('Account')->setLastname('Rules')
        ->setStreet(['1 Main Street'])->setCity('Springfield')->setPostcode('12345')
        ->setCountryId('US')->setRegionId(12)->setTelephone('5550100')
        ->save();
    $permissions = ['addresses/read', 'addresses/write'];
    $allowed = accountRulesSecurity(new ApiUser('service', ['ROLE_API_USER'], apiUserId: 1, permissions: $permissions, allowedStoreIds: [1]));
    $restricted = accountRulesSecurity(new ApiUser('service', ['ROLE_API_USER'], apiUserId: 1, permissions: $permissions, allowedStoreIds: [$otherStoreId]));
    $uri = '/api/rest/v2/customers/' . $customer->getId() . '/addresses';
    // Without a bearer token, the API answers 401 for each refused request
    $request = function (string $method, string $uri): \Symfony\Component\HttpFoundation\Request {
        $request = AdminApiParity::jsonRequest($method, $uri);
        $request->headers->set('Authorization', 'Bearer test');
        return $request;
    };
    $list = fn(Security $security): array => AdminApiParity::api(
        $request('GET', $uri),
        fn() => new AddressProvider($security)->provide(new GetCollection(name: 'get_customer_addresses'), ['customerId' => (int) $customer->getId()]),
    );
    $item = fn(Security $security): array => AdminApiParity::api(
        $request('GET', '/api/rest/v2/addresses/' . $address->getId()),
        fn() => new AddressProvider($security)->provide(new Get(name: 'get_address'), ['id' => (int) $address->getId()]),
    );
    $delete = fn(Security $security): array => AdminApiParity::api(
        $request('DELETE', '/api/rest/v2/addresses/' . $address->getId()),
        fn() => new AddressProcessor($security)->process(null, new \ApiPlatform\Metadata\Delete(name: 'delete_address'), ['id' => (int) $address->getId()]),
    );

    expect($list($allowed)['status'])->toBe(200)
        ->and($item($allowed)['status'])->toBe(200)
        ->and($list($restricted)['status'])->toBe(403)
        ->and($item($restricted)['status'])->toBe(403)
        ->and($delete($restricted)['status'])->toBe(403)
        ->and($delete($allowed)['status'])->toBe(200);
});

it('logs in with a magic link one time, and keeps the link when the 2FA code is missing', function () {
    $store = Mage::app()->getStore();
    $store->setConfig('customer/login/magic_link_enabled', '1');
    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_ALLOW_2FA, '1');
    $customer = accountRulesCustomer();
    $secret = $this->spy->getSecondFactorSecret($customer);
    $this->spy->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, true, \OTPHP\TOTP::createFromSecret($secret)->now());
    $token = Mage::helper('core')->getRandomString(32);
    $customer->setRpToken($token)->setRpTokenCreatedAt(Mage_Core_Model_Locale::nowUtc())->save();

    try {
        $this->spy->authenticateByMagicLink($token);
        $code = null;
    } catch (Mage_Core_Exception $e) {
        $code = $e->getCode();
    }
    $loggedIn = $this->spy->authenticateByMagicLink($token, \OTPHP\TOTP::createFromSecret($secret)->now());

    expect($code)->toBe(Mage_Customer_Model_Customer::EXCEPTION_2FA_INVALID)
        ->and((int) $loggedIn->getId())->toBe((int) $customer->getId())
        ->and(fn() => $this->spy->authenticateByMagicLink($token, \OTPHP\TOTP::createFromSecret($secret)->now()))->toThrow(Mage_Core_Exception::class);
});

it('confirms an account and turns 2FA on through the API operations', function () {
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Customer::XML_PATH_ALLOW_2FA, '1');
    $pending = accountRulesCustomer(['confirmation' => 'key-123']);
    $customer = accountRulesCustomer();
    $security = accountRulesSecurity(new ApiUser('customer', ['ROLE_CUSTOMER'], (int) $customer->getId()));
    $call = fn(?Security $security, string $operation, array $input): array => AdminApiParity::api(
        AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers/' . $operation),
        fn() => new CustomerProcessor($security)->process(new CustomerDto(), new Post(name: $operation), [], ['args' => ['input' => $input]]),
    );

    $wrongKey = $call(null, 'confirm_rest', ['customerId' => (int) $pending->getId(), 'key' => 'wrong']);
    $confirmed = $call(null, 'confirm_rest', ['customerId' => (int) $pending->getId(), 'key' => 'key-123']);
    // The key of an account that is already confirmed is not checked, so the answer has no customer data
    $other = $call(null, 'confirm_rest', ['customerId' => (int) $customer->getId(), 'key' => 'any']);
    $secret = $call($security, 'two_factor_secret_rest', []);
    $code = \OTPHP\TOTP::createFromSecret((string) $secret['result']?->twofaSecret)->now();
    $wrongPassword = $call($security, 'two_factor_rest', ['currentPassword' => 'WrongPassword1!', 'twofaEnabled' => true, 'twofaCode' => $code]);
    $enabled = $call($security, 'two_factor_rest', ['currentPassword' => ACCOUNT_RULES_PASSWORD, 'twofaEnabled' => true, 'twofaCode' => $code]);

    expect($wrongKey['status'])->toBe(422)
        ->and($confirmed['status'])->toBe(200)
        ->and($confirmed['result']->isConfirmed)->toBeTrue()
        ->and($confirmed['result']->email)->toBe('')
        ->and($other['result']->email)->toBe('')
        ->and($other['result']->firstname)->toBeNull()
        ->and($this->spy->emails)->toBe([['confirmed', (string) $pending->getEmail()]])
        ->and($secret['status'])->toBe(200)
        ->and($wrongPassword['status'])->toBe(422)
        ->and($enabled['status'])->toBe(200)
        ->and((bool) Mage::getModel('customer/customer')->load($customer->getId())->getTwofaEnabled())->toBeTrue()
        ->and($call(null, 'two_factor_rest', ['currentPassword' => ACCOUNT_RULES_PASSWORD, 'twofaEnabled' => false])['status'])->toBe(401);
});
