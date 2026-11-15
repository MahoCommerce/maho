<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Customer\Api\Customer as CustomerDto;
use Mage\Customer\Api\CustomerProcessor;
use Maho\ApiPlatform\Api\AuthTokenProcessor;
use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\TokenBlacklist;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\TransportParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront and the API log in a customer without a password, confirm an account and reset a password through
 * Mage_Customer_Service_Authentication (#1348). So no transport logs in a customer who uses 2FA without the code,
 * and both count the requests for a password reset email in the same table.
 */

const PASSWORDLESS_PARITY_PASSWORD = 'Password123!';

/**
 * Return a token that the API gives to the client, without the JWT secret of the store.
 */
class PasswordlessParityJwtService extends JwtService
{
    #[\Override]
    public function generateCustomerToken(\Mage_Customer_Model_Customer $customer): string
    {
        return 'token-of-customer-' . $customer->getId();
    }
}

final class PasswordlessParityEventRecorder
{
    /** @var list<array<string, mixed>> */
    public static array $events = [];

    public function record(\Maho\Event\Observer $observer): void
    {
        self::$events[] = $observer->getEvent()->getData();
    }
}

function passwordlessParityEmail(): string
{
    return 'passwordless-parity-' . bin2hex(random_bytes(4)) . '@example.com';
}

/**
 * Save a customer. With $twofa, the customer uses 2FA: the second entry is the secret of the authenticator app.
 *
 * @return array{Mage_Customer_Model_Customer, string}
 */
function passwordlessParityCustomer(bool $twofa = false): array
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setEmail(passwordlessParityEmail())
        ->setFirstname('Passwordless')
        ->setLastname('Parity')
        ->setPassword(PASSWORDLESS_PARITY_PASSWORD);
    $secret = '';
    if ($twofa) {
        $secret = Mage::helper('core/security')->generateTotpSecret();
        $customer->setTwofaSecret($secret)->setTwofaEnabled();
    }
    $customer->save();

    return [$customer, $secret];
}

function passwordlessParityMagicLink(Mage_Customer_Model_Customer $customer): string
{
    $token = $customer->generateMagicLinkToken();
    $customer->changeResetPasswordLinkToken($token);
    return $token;
}

/**
 * Call $action of the storefront account controller, as the browser does. Return the messages of the session.
 *
 * @param array<string, mixed> $params
 * @return array{success: list<string>, errors: list<string>}
 */
function passwordlessParityWeb(string $method, string $action, array $params): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/customer/account/' . $action, $method, $params));
    $request->setRouteName('customer')->setControllerName('account')->setActionName($action)
        ->setControllerModule('Mage_Customer')->setDispatched(true);
    Mage::app()->setRequest($request);
    // A message that an earlier action left in the session is not an outcome of this action
    Mage::getSingleton('customer/session')->getMessages(true);

    // getQuery() of the request reads $_GET, as in a real request
    $query = $_GET;
    $_GET = $method === 'GET' ? $params : [];
    try {
        new Mage_Customer_AccountController($request, new Mage_Core_Controller_Response_Http())->{$action . 'Action'}();
    } finally {
        $_GET = $query;
    }

    $messages = ['success' => [], 'errors' => []];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

/**
 * Ask POST /auth/token for a customer token with the body $body.
 *
 * @param array<string, mixed> $body
 * @return array{status: int, message: string, fields: list<string>, result: mixed}
 */
function passwordlessParityToken(array $body): array
{
    $request = SymfonyRequest::create('/api/rest/v2/auth/token', 'POST', content: (string) json_encode($body));
    $processor = new AuthTokenProcessor(TransportParity::adminSecurity(), new PasswordlessParityJwtService(), new TokenBlacklist());

    return TransportParity::api(fn() => $processor->process(null, new Post(name: 'get_token'), [], ['request' => $request]));
}

/**
 * Call the operation $operation of the customer API without a token.
 *
 * @param array<string, mixed> $input
 * @return array{status: int, message: string, fields: list<string>, result: mixed}
 */
function passwordlessParityApi(string $operation, array $input): array
{
    return TransportParity::api(fn() => new CustomerProcessor()->process(new CustomerDto(), new Post(name: $operation), [], ['args' => ['input' => $input]]));
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    $customerSession = Mage::getSingleton('customer/session');
    $customerSession->logout();
    $customerSession->unsTwofaPendingCustomerId();
    $customerSession->getMessages(true);

    $store = Mage::app()->getStore();
    $store->setConfig('customer/account/enabled_in_frontend', '1');
    $store->setConfig('customer/create_account/confirm', '0');
    $store->setConfig('customer/login/magic_link_enabled', '1');
    $store->setConfig('customer/password/allow_2fa', '1');
    $store->setConfig('system/smtp/enabled', '');
    $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_FLOW_SECURE, '0');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
});

afterEach(function () {
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $customerSession = Mage::getSingleton('customer/session');
    $customerSession->logout();
    $customerSession->unsTwofaPendingCustomerId();
    $customerSession->unsetData(Mage_Customer_AccountController::TOKEN_SESSION_NAME)
        ->unsetData(Mage_Customer_AccountController::CUSTOMER_ID_SESSION_NAME);
    Mage::register('isSecureArea', true, true);
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'passwordless-parity-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete($resource->getTableName('customer/flowpassword'), ['email LIKE ?' => 'passwordless-parity-%@example.com']);
});

describe('magic link', function () {
    it('logs in the customer of the link on the storefront and on the customer grant', function () {
        [$webCustomer] = passwordlessParityCustomer();
        [$apiCustomer] = passwordlessParityCustomer();

        $web = passwordlessParityWeb('GET', 'magicLinkLogin', ['token' => passwordlessParityMagicLink($webCustomer)]);
        $session = Mage::getSingleton('customer/session');
        $api = passwordlessParityToken(['grant_type' => 'magic_link', 'token' => passwordlessParityMagicLink($apiCustomer)]);

        expect($web['errors'])->toBe([])
            ->and($session->isLoggedIn())->toBeTrue()
            ->and((int) $session->getCustomerId())->toBe((int) $webCustomer->getId())
            ->and($api['status'])->toBe(200)
            ->and($api['result']->customer['id'])->toBe((int) $apiCustomer->getId());
    });

    it('logs in no customer who uses 2FA without the code', function () {
        [$webCustomer, $webSecret] = passwordlessParityCustomer(twofa: true);
        [$apiCustomer, $apiSecret] = passwordlessParityCustomer(twofa: true);
        $apiToken = passwordlessParityMagicLink($apiCustomer);
        $session = Mage::getSingleton('customer/session');

        $web = passwordlessParityWeb('GET', 'magicLinkLogin', ['token' => passwordlessParityMagicLink($webCustomer)]);
        $webState = [$session->isLoggedIn(), $session->getTwofaPendingCustomerId()];
        $webWrongCode = passwordlessParityWeb('POST', 'twofaChallengePost', ['twofa_verification_code' => '000000x']);
        $isLoggedInAfterWrongCode = $session->isLoggedIn();
        $webCode = passwordlessParityWeb('POST', 'twofaChallengePost', ['twofa_verification_code' => \OTPHP\TOTP::createFromSecret($webSecret)->now()]);

        $apiNoCode = passwordlessParityToken(['grant_type' => 'magic_link', 'token' => $apiToken]);
        $apiWrongCode = passwordlessParityToken(['grant_type' => 'magic_link', 'token' => $apiToken, 'twofa_code' => '000000x']);
        $apiCode = passwordlessParityToken(['grant_type' => 'magic_link', 'token' => $apiToken, 'twofa_code' => \OTPHP\TOTP::createFromSecret($apiSecret)->now()]);

        expect($web['errors'])->toBe([])
            ->and($webState)->toBe([false, (int) $webCustomer->getId()])
            ->and($webWrongCode['errors'])->toBe(['2FA verification code is invalid.'])
            ->and($isLoggedInAfterWrongCode)->toBeFalse()
            ->and($webCode['errors'])->toBe([])
            ->and($session->isLoggedIn())->toBeTrue()
            ->and((int) $session->getCustomerId())->toBe((int) $webCustomer->getId())
            ->and($apiNoCode['status'])->toBe(401)
            ->and($apiNoCode['message'])->toBe('Two-factor authentication code is required.')
            ->and($apiWrongCode['status'])->toBe(401)
            ->and($apiWrongCode['message'])->toBe('2FA verification code is invalid.')
            ->and($apiCode['status'])->toBe(200)
            ->and($apiCode['result']->customer['id'])->toBe((int) $apiCustomer->getId());
    });
});

describe('account confirmation', function () {
    it('confirms the account on the storefront and the API, and only the storefront logs the customer in', function () {
        [$webCustomer] = passwordlessParityCustomer();
        [$apiCustomer] = passwordlessParityCustomer();
        $webCustomer->setConfirmation('web-key')->save();
        $apiCustomer->setConfirmation('api-key')->save();
        $session = Mage::getSingleton('customer/session');

        $web = passwordlessParityWeb('GET', 'confirm', ['id' => $webCustomer->getId(), 'key' => 'web-key']);
        $api = passwordlessParityApi('confirm_rest', ['customerId' => (int) $apiCustomer->getId(), 'key' => 'api-key']);

        expect($web['errors'])->toBe([])
            ->and($session->isLoggedIn())->toBeTrue()
            ->and((int) $session->getCustomerId())->toBe((int) $webCustomer->getId())
            ->and($api['status'])->toBe(200)
            ->and($api['result']->id)->toBe((int) $apiCustomer->getId())
            ->and(Mage::getModel('customer/customer')->load($webCustomer->getId())->getConfirmation())->toBeNull()
            ->and(Mage::getModel('customer/customer')->load($apiCustomer->getId())->getConfirmation())->toBeNull();
    });

    it('confirms the account of a customer who uses 2FA, and logs in only with the code', function () {
        [$customer, $secret] = passwordlessParityCustomer(twofa: true);
        $customer->setConfirmation('web-key')->save();
        $session = Mage::getSingleton('customer/session');

        $web = passwordlessParityWeb('GET', 'confirm', ['id' => $customer->getId(), 'key' => 'web-key']);
        $webState = [$session->isLoggedIn(), $session->getTwofaPendingCustomerId()];
        $again = passwordlessParityWeb('GET', 'confirm', ['id' => $customer->getId(), 'key' => 'web-key']);
        $isLoggedInAfterSecondLink = $session->isLoggedIn();
        $totp = \OTPHP\TOTP::createFromSecret($secret);
        // The code must stay valid until the request checks it, so it must not come from the end of a period
        if ($totp->expiresIn() < 5) {
            sleep($totp->expiresIn() + 1);
        }
        $code = passwordlessParityWeb('POST', 'twofaChallengePost', ['twofa_verification_code' => $totp->now()]);

        expect($web['errors'])->toBe([])
            ->and($webState)->toBe([false, (int) $customer->getId()])
            ->and(Mage::getModel('customer/customer')->load($customer->getId())->getConfirmation())->toBeNull()
            ->and($again['errors'])->toBe([])
            ->and($isLoggedInAfterSecondLink)->toBeFalse()
            ->and($code['errors'])->toBe([])
            ->and($session->isLoggedIn())->toBeTrue();
    });

    it('refuses a wrong key on the storefront and the API', function () {
        [$customer] = passwordlessParityCustomer();
        $customer->setConfirmation('right-key')->save();

        $web = passwordlessParityWeb('GET', 'confirm', ['id' => $customer->getId(), 'key' => 'wrong-key']);
        $api = passwordlessParityApi('confirm_rest', ['customerId' => (int) $customer->getId(), 'key' => 'wrong-key']);

        expect($web['errors'])->toBe(['Wrong confirmation key.'])
            ->and(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse()
            ->and($api['status'])->toBe(422)
            ->and($api['message'])->toBe($web['errors'][0])
            ->and(Mage::getModel('customer/customer')->load($customer->getId())->getConfirmation())->toBe('right-key');
    });
});

describe('password reset', function () {
    it('counts the requests of the storefront and the API in one limit that a cache flush does not reset', function () {
        $store = Mage::app()->getStore();
        $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_FLOW_SECURE, (string) Mage_Customer_Model_Config_Forgotpassword::FLOW_EMAIL);
        $store->setConfig(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_FORGOT_PASSWORD_EMAIL_TIMES, '2');
        [$customer] = passwordlessParityCustomer();
        $email = (string) $customer->getEmail();

        $web = passwordlessParityWeb('POST', 'forgotPasswordPost', ['email' => $email]);
        $api = passwordlessParityApi('forgot_password_rest', ['email' => $email]);
        Mage::app()->getCacheInstance()->flush();
        $webOverLimit = passwordlessParityWeb('POST', 'forgotPasswordPost', ['email' => $email]);
        $apiOverLimit = passwordlessParityApi('forgot_password_rest', ['email' => $email]);

        expect($web['errors'])->toBe([])
            ->and($web['success'])->toHaveCount(1)
            ->and($api['status'])->toBe(200)
            ->and($webOverLimit['errors'])->toBe(['You have exceeded requests to times per 24 hours from 1 e-mail.'])
            ->and($apiOverLimit['status'])->toBe(429)
            ->and($apiOverLimit['message'])->toBe($webOverLimit['errors'][0]);
    });

    it('sets the new password with the reset link on the storefront and the API', function () {
        [$webCustomer] = passwordlessParityCustomer();
        [$apiCustomer] = passwordlessParityCustomer();
        $helper = Mage::helper('customer');
        $webCustomer->changeResetPasswordLinkToken($webToken = $helper->generateResetPasswordLinkToken());
        $apiCustomer->changeResetPasswordLinkToken($apiToken = $helper->generateResetPasswordLinkToken());

        passwordlessParityWeb('GET', 'resetPassword', ['id' => $webCustomer->getId(), 'token' => $webToken]);
        $web = passwordlessParityWeb('POST', 'resetPasswordPost', ['password' => 'NewPassword456!', 'confirmation' => 'NewPassword456!']);
        $api = passwordlessParityApi('reset_password_rest', ['email' => $apiCustomer->getEmail(), 'resetToken' => $apiToken, 'newPassword' => 'NewPassword456!']);

        $webReloaded = Mage::getModel('customer/customer')->load($webCustomer->getId());
        $apiReloaded = Mage::getModel('customer/customer')->load($apiCustomer->getId());
        expect($web['errors'])->toBe([])
            ->and($api['status'])->toBe(200)
            ->and($webReloaded->validatePassword('NewPassword456!'))->toBeTrue()
            ->and($apiReloaded->validatePassword('NewPassword456!'))->toBeTrue()
            ->and($webReloaded->getRpToken())->toBeEmpty()
            ->and($apiReloaded->getRpToken())->toBeEmpty()
            // A password reset logs nobody in
            ->and(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse();
    });

    it('refuses a wrong reset token on the storefront and the API', function () {
        [$customer] = passwordlessParityCustomer();
        $customer->changeResetPasswordLinkToken(Mage::helper('customer')->generateResetPasswordLinkToken());

        $web = passwordlessParityWeb('GET', 'resetPassword', ['id' => $customer->getId(), 'token' => 'wrong-token']);
        $api = passwordlessParityApi('reset_password_rest', ['email' => $customer->getEmail(), 'resetToken' => 'wrong-token', 'newPassword' => 'NewPassword456!']);

        expect($web['errors'])->toBe(['Your password reset link has expired.'])
            ->and($api['status'])->toBe(422)
            ->and(Mage::getModel('customer/customer')->load($customer->getId())->validatePassword(PASSWORDLESS_PARITY_PASSWORD))->toBeTrue();
    });
});

describe('registration', function () {
    it('gives the observers of customer_register_success the account controller of the storefront, one time', function () {
        $app = Mage::app();
        $property = new ReflectionProperty($app, '_events');
        $before = $property->getValue($app);
        $events = $before;
        // Only the recorder observes the event in this test
        $events['global']['customer_register_success'] = ['observers' => [
            'passwordless_parity_test' => ['type' => 'singleton', 'model' => PasswordlessParityEventRecorder::class, 'method' => 'record', 'args' => []],
        ]];
        foreach (array_keys($events) as $area) {
            if ($area !== 'global') {
                $events[$area]['customer_register_success'] = false;
            }
        }
        $property->setValue($app, $events);
        PasswordlessParityEventRecorder::$events = [];
        $email = passwordlessParityEmail();

        try {
            $web = passwordlessParityWeb('POST', 'createPost', [
                'firstname' => 'Passwordless',
                'lastname' => 'Parity',
                'email' => $email,
                'password' => PASSWORDLESS_PARITY_PASSWORD,
                'confirmation' => PASSWORDLESS_PARITY_PASSWORD,
            ]);
        } finally {
            $property->setValue($app, $before);
        }

        expect($web['errors'])->toBe([])
            ->and(PasswordlessParityEventRecorder::$events)->toHaveCount(1)
            ->and(PasswordlessParityEventRecorder::$events[0]['account_controller'])->toBeInstanceOf(Mage_Customer_AccountController::class)
            ->and(PasswordlessParityEventRecorder::$events[0]['customer']->getEmail())->toBe($email);
    });
});
