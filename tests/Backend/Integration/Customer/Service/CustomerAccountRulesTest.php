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
 * The rules of a customer account are in Mage_Customer_Service_Customer and Mage_Customer_Service_Authentication,
 * so that the storefront, the admin, the API and the CLI get the same answer (#1348): the active check of each
 * login, the CAPTCHA, the validation of a staff save, the switch of the customer accounts, the confirmation, 2FA
 * and the password.
 */

final class AccountRulesEventRecorder
{
    /** @var list<array<string, mixed>> */
    public static array $events = [];

    public function record(\Maho\Event\Observer $observer): void
    {
        self::$events[] = $observer->getEvent()->getData();
    }
}

/**
 * Turn 2FA on for $customer and return the secret of the authenticator app.
 */
function accountRulesEnableTwofa(Mage_Customer_Model_Customer $customer): string
{
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Authentication::XML_PATH_ALLOW_2FA, '1');
    $secret = Mage::helper('core/security')->generateTotpSecret();
    $customer->setTwofaSecret($secret)->setTwofaEnabled()->save();
    return $secret;
}

const ACCOUNT_RULES_PASSWORD = 'Password123!';

/**
 * Return the 2FA code of $secret. When less than 5 seconds of the code period are left, wait for the next period
 * first. A code from the end of a period fails after a slow step, such as a password check.
 */
function accountRulesTwofaCode(string $secret): string
{
    $totp = \OTPHP\TOTP::createFromSecret($secret);
    if ($totp->expiresIn() < 5) {
        sleep($totp->expiresIn() + 1);
    }
    return $totp->now();
}

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
    foreach (['customer_register', 'confirmation_link', 'change_password', 'customer_login', 'auth_token_ip', 'reset_password'] as $key) {
        $store->setConfig('system/rate_limit/' . $key, '0');
    }
    $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_FLOW_SECURE, '0');
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
    $this->auth = Mage::getService('customer/authentication');
});

afterEach(function () {
    Mage::unregister('_service/customer/customer');
    CaptchaStub::uninstall();
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete($resource->getTableName('customer/flowpassword'), ['email LIKE ?' => 'account-rules-%@example.com']);
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

    expect(fn() => $this->auth->authenticate((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))
        ->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->auth->authenticateInAnyWebsite((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))
        ->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->spy->requireActiveById((int) $customer->getId()))
        ->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and((int) $this->auth->authenticate((string) $active->getEmail(), ACCOUNT_RULES_PASSWORD, 1)->getId())->toBe((int) $active->getId())
        ->and((int) $this->spy->requireActiveById((int) $active->getId())->getId())->toBe((int) $active->getId());
});

it('refuses a wrong password of a customer that is not active with the error of a wrong password', function () {
    $customer = accountRulesCustomer(['is_active' => 0]);

    try {
        $this->auth->authenticate((string) $customer->getEmail(), 'WrongPassword1!', 1);
        $error = null;
    } catch (Mage_Core_Exception_Unauthorized $e) {
        $error = $e->getErrorCode();
    }

    expect($error)->toBe('invalid_credentials');
});

it('checks the CAPTCHA of a registration with the fields of the client', function () {
    $captcha = CaptchaStub::install();

    expect(fn() => $this->spy->register(accountRulesNewCustomer(), 1, []))->toThrow(Mage_Core_Exception_Input::class)
        ->and(fn() => $this->spy->register(accountRulesNewCustomer(), 1, ['captchaToken' => 'wrong']))->toThrow(Mage_Core_Exception_Input::class);

    $api = $this->spy->register(accountRulesNewCustomer(), 1, ['captchaToken' => CaptchaStub::SOLVED]);
    $form = $this->spy->register(accountRulesNewCustomer(), 1, ['maho_captcha' => CaptchaStub::SOLVED]);

    expect((int) $api->getId())->toBeGreaterThan(0)
        ->and((int) $form->getId())->toBeGreaterThan(0)
        ->and($captcha->checks)->toHaveCount(4);
});

it('asks no CAPTCHA of a registration in the checkout or through a sign-in provider', function () {
    $captcha = CaptchaStub::install();

    $checkout = accountRulesNewCustomer();
    $this->spy->registerAtCheckout($checkout, 1, fn() => $checkout->save());
    $provider = $this->spy->registerFromProvider(accountRulesNewCustomer(), 1);

    expect((int) $checkout->getId())->toBeGreaterThan(0)
        ->and((int) $provider->getId())->toBeGreaterThan(0)
        ->and($captcha->checks)->toBe([]);
});

it('checks the CAPTCHA of a password reset email and a confirmation email with the fields of the client, and not for the staff', function () {
    $captcha = CaptchaStub::install();
    $email = accountRulesEmail();

    expect(fn() => $this->auth->sendPasswordResetLink($email, 1, []))->toThrow(Mage_Core_Exception_Input::class)
        ->and(fn() => $this->auth->sendConfirmationLink($email, 1, []))->toThrow(Mage_Core_Exception_Input::class);

    $this->auth->sendPasswordResetLink($email, 1, ['captchaToken' => CaptchaStub::SOLVED]);
    $this->auth->sendConfirmationLink($email, 1, ['maho_captcha' => CaptchaStub::SOLVED]);
    $this->auth->sendPasswordResetLinkByStaff($email, 1);
    $this->auth->sendConfirmationLinkByStaff($email, 1);

    expect($captcha->checks)->toHaveCount(4);
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

    expect(fn() => $this->spy->register(accountRulesNewCustomer(), 1, []))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->auth->authenticate((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->auth->authenticateInAnyWebsite((string) $customer->getEmail(), ACCOUNT_RULES_PASSWORD, 1))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => Mage::getService('sociallogin/identity')->authenticate('google', 'token', 1))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and((int) $this->spy->create(accountRulesNewCustomer(), 1)->getId())->toBeGreaterThan(0);
});

it('sends the confirmed email when a key confirms the account, and one time only', function () {
    $customer = accountRulesCustomer(['confirmation' => 'key-123']);

    expect(fn() => $this->auth->confirm((int) $customer->getId(), 'wrong'))->toThrow(Mage_Core_Exception::class);
    $confirmed = $this->auth->confirm((int) $customer->getId(), 'key-123');
    $again = $this->auth->confirm((int) $customer->getId(), 'key-123');

    expect($confirmed)->toBeTrue()
        ->and($again)->toBeFalse()
        ->and($this->spy->emails)->toBe([['confirmed', (string) $customer->getEmail()]])
        ->and(Mage::getModel('customer/customer')->load($customer->getId())->getConfirmation())->toBeEmpty();
});

it('returns the customer to log in for the key of a pending account only', function () {
    $customer = accountRulesCustomer(['confirmation' => 'key-123']);

    $loggedIn = $this->auth->authenticateByConfirmation((int) $customer->getId(), 'key-123');
    $again = $this->auth->authenticateByConfirmation((int) $customer->getId(), 'key-123');

    expect((int) $loggedIn?->getId())->toBe((int) $customer->getId())
        ->and($again)->toBeNull();
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
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Authentication::XML_PATH_CONFIRMATION_LINK_RATE_LIMIT, '3');

    $this->auth->sendConfirmationLink((string) $pending->getEmail(), 1, []);
    $this->auth->sendConfirmationLink((string) $confirmed->getEmail(), 1, []);
    $this->auth->sendConfirmationLink(accountRulesEmail(), 1, []);

    expect($this->spy->emails)->toBe([['confirmation', (string) $pending->getEmail()]])
        ->and(fn() => $this->auth->sendConfirmationLink((string) $pending->getEmail(), 1, []))->toThrow(Mage_Core_Exception_TooManyRequests::class)
        ->and(fn() => $this->auth->sendConfirmationLink('not-an-email', 1, []))->toThrow(Mage_Core_Exception_Input::class);
});

it('ships a default for the limit of the confirmation link', function () {
    expect((int) Mage::getConfig()->getNode('default/system/rate_limit/confirmation_link'))->toBeGreaterThan(0);
});

it('turns 2FA on and off with the current password and the limit of the password change', function () {
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Customer_Service_Authentication::XML_PATH_ALLOW_2FA, '1');
    $store->setConfig(Mage_Customer_Service_Authentication::XML_PATH_REQUIRE_2FA, '0');
    $customer = accountRulesCustomer();

    $secret = $this->auth->getSecondFactorSecret($customer);
    $code = accountRulesTwofaCode($secret);

    expect(fn() => $this->auth->setSecondFactor($customer, 'WrongPassword1!', true, $code))->toThrow(Mage_Core_Exception::class, 'Current password is incorrect.')
        ->and(fn() => $this->auth->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, true, '000000x'))->toThrow(Mage_Core_Exception::class)
        ->and((bool) Mage::getModel('customer/customer')->load($customer->getId())->getTwofaEnabled())->toBeFalse();

    $this->auth->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, true, $code);
    expect((bool) Mage::getModel('customer/customer')->load($customer->getId())->getTwofaEnabled())->toBeTrue()
        ->and(fn() => $this->auth->getSecondFactorSecret($customer))->toThrow(Mage_Core_Exception_Conflict::class);

    $store->setConfig(Mage_Customer_Service_Authentication::XML_PATH_REQUIRE_2FA, '1');
    expect(fn() => $this->auth->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, false))->toThrow(Mage_Core_Exception_Forbidden::class);

    $store->setConfig(Mage_Customer_Service_Authentication::XML_PATH_REQUIRE_2FA, '0');
    $this->auth->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, false);
    $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
    expect((bool) $reloaded->getTwofaEnabled())->toBeFalse()
        ->and($reloaded->getTwofaSecret())->toBeNull();

    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_CHANGE_PASSWORD_RATE_LIMIT, '1');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    try {
        $this->auth->setSecondFactor($customer, 'WrongPassword1!', false);
    } catch (Mage_Core_Exception) {
    }
    expect(fn() => $this->auth->setSecondFactor($customer, 'WrongPassword1!', false))->toThrow(Mage_Core_Exception_TooManyRequests::class);

    $store->setConfig(Mage_Customer_Service_Authentication::XML_PATH_ALLOW_2FA, '0');
    expect(fn() => $this->auth->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, false))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
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
    $store->setConfig(Mage_Customer_Service_Authentication::XML_PATH_ALLOW_2FA, '1');
    $customer = accountRulesCustomer();
    $secret = $this->auth->getSecondFactorSecret($customer);
    $this->auth->setSecondFactor($customer, ACCOUNT_RULES_PASSWORD, true, accountRulesTwofaCode($secret));
    $token = Mage::helper('core')->getRandomString(32);
    $customer->setRpToken($token)->setRpTokenCreatedAt(Mage_Core_Model_Locale::nowUtc())->save();

    try {
        $this->auth->authenticateByMagicLink($token);
        $error = null;
    } catch (Mage_Customer_Exception_SecondFactorRequired $e) {
        $error = $e->getErrorCode();
    }
    try {
        $this->auth->authenticateByMagicLink($token, '000000x');
        $wrong = null;
    } catch (Mage_Core_Exception_Unauthorized $e) {
        $wrong = $e->getErrorCode();
    }
    $loggedIn = $this->auth->authenticateByMagicLink($token, accountRulesTwofaCode($secret));

    expect($error)->toBe('twofa_required')
        ->and($wrong)->toBe('twofa_invalid')
        ->and((int) $loggedIn->getId())->toBe((int) $customer->getId())
        ->and(fn() => $this->auth->authenticateByMagicLink($token, \OTPHP\TOTP::createFromSecret($secret)->now()))->toThrow(Mage_Core_Exception::class);
});

it('confirms an account and turns 2FA on through the API operations', function () {
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Authentication::XML_PATH_ALLOW_2FA, '1');
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
    $code = accountRulesTwofaCode((string) $secret['result']?->twofaSecret);
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

it('refuses each login without a password for a customer who uses 2FA and gives no code', function () {
    $store = Mage::app()->getStore();
    $store->setConfig('customer/login/magic_link_enabled', '1');
    $linked = accountRulesCustomer();
    $secret = accountRulesEnableTwofa($linked);
    $token = Mage::helper('core')->getRandomString(32);
    $linked->setRpToken($token)->setRpTokenCreatedAt(Mage_Core_Model_Locale::nowUtc())->save();
    $pending = accountRulesCustomer(['confirmation' => 'key-123']);
    accountRulesEnableTwofa($pending);
    $verified = accountRulesCustomer();
    $verifiedSecret = accountRulesEnableTwofa($verified);

    $required = function (callable $login): ?int {
        try {
            $login();
        } catch (Mage_Customer_Exception_SecondFactorRequired $e) {
            return $e->getCustomerId();
        }
        return null;
    };
    $magicLink = $required(fn() => $this->auth->getByMagicLinkToken($token));
    $confirmation = $required(fn() => $this->auth->authenticateByConfirmation((int) $pending->getId(), 'key-123'));
    $other = $required(fn() => $this->auth->authenticateVerified($verified));
    try {
        $this->auth->authenticateVerified($verified, '000000x');
        $wrong = null;
    } catch (Mage_Core_Exception_Unauthorized $e) {
        $wrong = $e->getErrorCode();
    }

    expect($magicLink)->toBe((int) $linked->getId())
        // The session client used the link, and now asks for the code
        ->and(fn() => $this->auth->getByMagicLinkToken($token))->toThrow(Mage_Core_Exception::class, 'Invalid or expired login link.')
        ->and($confirmation)->toBe((int) $pending->getId())
        ->and(Mage::getModel('customer/customer')->load($pending->getId())->getConfirmation())->toBeEmpty()
        ->and($other)->toBe((int) $verified->getId())
        ->and($wrong)->toBe('twofa_invalid')
        ->and((int) $this->auth->authenticateVerified($verified, accountRulesTwofaCode($verifiedSecret))->getId())->toBe((int) $verified->getId())
        ->and($secret)->not->toBe('');
});

it('refuses a login without a password for a customer that is not active', function () {
    $customer = accountRulesCustomer(['is_active' => 0]);

    expect(fn() => $this->auth->authenticateVerified($customer))->toThrow(Mage_Core_Exception_Forbidden::class);
});

it('does not count the login that stops at the request for the 2FA code', function () {
    $customer = accountRulesCustomer();
    $secret = accountRulesEnableTwofa($customer);
    $email = (string) $customer->getEmail();
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Authentication::XML_PATH_LOGIN_RATE_LIMIT, '1');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    foreach ([1, 2, 3] as $ignored) {
        expect(fn() => $this->auth->authenticate($email, ACCOUNT_RULES_PASSWORD, 1))->toThrow(Mage_Customer_Exception_SecondFactorRequired::class);
    }
    $loggedIn = $this->auth->authenticate($email, ACCOUNT_RULES_PASSWORD, 1, accountRulesTwofaCode($secret));

    expect((int) $loggedIn->getId())->toBe((int) $customer->getId())
        ->and(fn() => $this->auth->authenticate($email, ACCOUNT_RULES_PASSWORD, 1))->toThrow(Mage_Core_Exception_TooManyRequests::class);
});

it('keeps the count of the password reset emails after a cache flush', function () {
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_FLOW_SECURE, (string) Mage_Customer_Model_Config_Forgotpassword::FLOW_EMAIL);
    $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_EMAIL_TIMES, '2');
    $email = accountRulesEmail();

    $this->auth->sendPasswordResetLink($email, 1, []);
    $this->auth->sendPasswordResetLink(strtoupper($email), 1, []);
    Mage::app()->getCacheInstance()->flush();

    expect(fn() => $this->auth->sendPasswordResetLink($email, 1, []))->toThrow(Mage_Core_Exception_TooManyRequests::class)
        ->and(fn() => $this->auth->sendPasswordResetLink(accountRulesEmail(), 1, []))->not->toThrow(Mage_Core_Exception::class);
});

it('limits the password reset emails of one IP address when the store counts by IP', function () {
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_FLOW_SECURE, (string) Mage_Customer_Model_Config_Forgotpassword::FLOW_IP);
    $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_IP_TIMES, '1');
    $resource = Mage::getSingleton('core/resource');
    $table = $resource->getTableName('customer/flowpassword');
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
    $_SERVER['REMOTE_ADDR'] = '203.0.113.77';
    // The helper keeps the first address that it read
    Mage::unregister('_helper/core/http');
    $resource->getConnection('core_write')->delete($table, ['ip = ?' => '203.0.113.77']);

    try {
        $this->auth->sendPasswordResetLink(accountRulesEmail(), 1, []);
        expect(fn() => $this->auth->sendPasswordResetLink(accountRulesEmail(), 1, []))->toThrow(Mage_Core_Exception_TooManyRequests::class);
    } finally {
        if ($remoteAddr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        }
        Mage::unregister('_helper/core/http');
        $resource->getConnection('core_write')->delete($table, ['ip = ?' => '203.0.113.77']);
    }
});

it('ships the forgot password limit of 5 requests for each email in 24 hours and for each IP address in one hour', function () {
    expect((int) Mage::getConfig()->getNode('default/admin/security/forgot_password_flow_secure'))->toBe(Mage_Customer_Model_Config_Forgotpassword::FLOW_IP_EMAIL)
        ->and((int) Mage::getConfig()->getNode('default/admin/security/forgot_password_email_times'))->toBe(5)
        ->and((int) Mage::getConfig()->getNode('default/admin/security/forgot_password_ip_times'))->toBe(5);
});

it('counts a registration in the limit only when its input is valid', function () {
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Customer::XML_PATH_REGISTER_RATE_LIMIT, '1');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    foreach ([1, 2] as $ignored) {
        expect(fn() => $this->spy->register(accountRulesNewCustomer()->setFirstname(''), 1, []))->toThrow(Mage_Core_Exception_Input::class);
    }
    $customer = $this->spy->register(accountRulesNewCustomer(), 1, []);

    expect((int) $customer->getId())->toBeGreaterThan(0)
        ->and(fn() => $this->spy->register(accountRulesNewCustomer(), 1, []))->toThrow(Mage_Core_Exception_TooManyRequests::class);
});

it('gives the event of a registration the customer and the data of the caller, one time', function () {
    $app = Mage::app();
    $property = new ReflectionProperty($app, '_events');
    $before = $property->getValue($app);
    $events = $before;
    // Only the recorder observes the event in this test
    $events['global']['customer_register_success'] = ['observers' => [
        'account_rules_test' => ['type' => 'singleton', 'model' => AccountRulesEventRecorder::class, 'method' => 'record', 'args' => []],
    ]];
    foreach (array_keys($events) as $area) {
        if ($area !== 'global') {
            $events[$area]['customer_register_success'] = false;
        }
    }
    $property->setValue($app, $events);
    AccountRulesEventRecorder::$events = [];

    try {
        $caller = new stdClass();
        $customer = $this->spy->register(accountRulesNewCustomer(), 1, [], eventData: ['account_controller' => $caller]);
    } finally {
        $property->setValue($app, $before);
    }

    expect(AccountRulesEventRecorder::$events)->toHaveCount(1)
        ->and(AccountRulesEventRecorder::$events[0]['customer'])->toBe($customer)
        ->and(AccountRulesEventRecorder::$events[0]['account_controller'])->toBe($caller);
});

it('refuses a customer group that does not exist, a gender that is not an option and a date of birth in the future', function () {
    $id = (int) accountRulesCustomer()->getId();
    // A clone of a customer has no ID, so it is a new customer that needs a password
    $stored = fn(): Mage_Customer_Model_Customer => Mage::getModel('customer/customer')->load($id);

    $fields = function (Mage_Customer_Model_Customer $customer): array {
        try {
            $this->spy->save($customer);
        } catch (Mage_Core_Exception_Input $e) {
            return array_column($e->getErrors(), 'field');
        }
        return [];
    };
    $future = new DateTimeImmutable('+2 days')->format('Y-m-d 00:00:00');

    expect($fields($stored()->setGroupId(987654)))->toBe(['group_id'])
        ->and($fields($stored()->setData('gender', 987654)))->toBe(['gender'])
        ->and($fields($stored()->setDob($future)))->toBe(['dob'])
        ->and($fields($stored()->setEmail('not-an-email')))->toBe(['email'])
        ->and($fields($stored()->setDob('1990-05-17 00:00:00')))->toBe([])
        ->and($stored()->setDob($future)->validate())->toContain('The Date of Birth cannot be in the future.');
});

it('refuses a new website for a saved customer', function () {
    $customer = accountRulesCustomer();

    try {
        $this->spy->save($customer->setWebsiteId(987));
        $fields = [];
    } catch (Mage_Core_Exception_Input $e) {
        $fields = array_column($e->getErrors(), 'field');
    }

    expect($fields)->toBe(['website_id']);
});

it('makes a new customer active when the caller sets no state', function () {
    $created = $this->spy->create(accountRulesNewCustomer(), 1);
    $inactive = $this->spy->create(accountRulesNewCustomer()->setData('is_active', 0), 1);

    expect((int) Mage::getModel('customer/customer')->load($created->getId())->getIsActive())->toBe(1)
        ->and((int) Mage::getModel('customer/customer')->load($inactive->getId())->getIsActive())->toBe(0);
});

it('tells the admin form that another customer of the website has the email', function () {
    $customer = accountRulesCustomer();
    $probe = fn(?int $id): Mage_Customer_Model_Customer => Mage::getModel('customer/customer')->setId($id)->setWebsiteId(1)->setEmail((string) $customer->getEmail());

    expect(fn() => $this->spy->assertEmailNotInUse($probe(null)))->toThrow(Mage_Core_Exception_Conflict::class)
        ->and(fn() => $this->spy->assertEmailNotInUse($probe((int) $customer->getId())))->not->toThrow(Mage_Core_Exception::class);
});

it('copies a magic link limit of a store to its new setting, and keeps a new setting that the store has', function () {
    $setup = new Mage_Customer_Model_Resource_Setup('customer_setup');
    $connection = $setup->getConnection();
    $table = $setup->getTable('core/config_data');
    $paths = [
        'customer/login/magic_link_rate_limit_email',
        'customer/login/magic_link_rate_limit_ip',
        'system/rate_limit/magic_link_email',
        'system/rate_limit/magic_link_ip',
    ];
    $value = fn(string $path, string $scope, int $scopeId): string|false => $connection->fetchOne(
        $connection->select()->from($table, ['value'])->where('path = ?', $path)->where('scope = ?', $scope)->where('scope_id = ?', $scopeId),
    );
    $connection->beginTransaction();
    try {
        $connection->delete($table, ['path IN (?)' => $paths]);
        $connection->insert($table, ['scope' => 'default', 'scope_id' => 0, 'path' => $paths[0], 'value' => '7']);
        $connection->insert($table, ['scope' => 'websites', 'scope_id' => 1, 'path' => $paths[0], 'value' => '0']);
        $connection->insert($table, ['scope' => 'default', 'scope_id' => 0, 'path' => $paths[1], 'value' => '25']);
        $connection->insert($table, ['scope' => 'default', 'scope_id' => 0, 'path' => $paths[3], 'value' => '40']);

        $script = Mage::getModuleDir('', 'Mage_Customer') . '/data/customer_setup/data-upgrade-2.0.5-2.0.6.php';
        (function () use ($script): void {
            include $script;
        })->call($setup);

        $copied = [$value($paths[2], 'default', 0), $value($paths[2], 'websites', 1), $value($paths[3], 'default', 0)];
    } finally {
        $connection->rollBack();
    }

    expect($copied)->toBe(['7', false, '40']);
});
