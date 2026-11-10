<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Service\JwtService;
use Maho\SocialLogin\Api\SocialAuth;
use Maho\SocialLogin\Api\SocialAuthProcessor;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\SocialLoginJwt;
use Tests\Helpers\TransportParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The sign-in buttons of the storefront and POST /api/rest/v2/customers/social-auth sign in through
 * Maho_SocialLogin_Service_Identity (#1348). Each test gives the same provider token to both transports.
 */

const SOCIAL_PARITY_KID = 'sociallogin-parity-key';
const SOCIAL_PARITY_CLIENT_ID = 'parity-client.apps.googleusercontent.com';

/**
 * Return a token that the API gives to the client, without the JWT secret of the store.
 */
class SocialParityJwtService extends JwtService
{
    #[\Override]
    public function generateCustomerToken(\Mage_Customer_Model_Customer $customer): string
    {
        return 'token-of-customer-' . $customer->getId();
    }
}

function socialParityToken(string $email, string $sub, ?string $nonce = null): string
{
    $claims = ['sub' => $sub, 'email' => $email, 'email_verified' => true, 'given_name' => 'Social', 'family_name' => 'Parity'];
    if ($nonce !== null) {
        $claims['nonce'] = $nonce;
    }
    return SocialLoginJwt::idToken(SOCIAL_PARITY_KID, 'https://accounts.google.com', SOCIAL_PARITY_CLIENT_ID, $claims);
}

/**
 * Post the token to the storefront, with a nonce that the session issued when $token holds one.
 *
 * @return array{status: int, message: string, customer_id: ?int}
 */
function socialViaWeb(string $provider, string $token, ?string $nonce): array
{
    Mage::getSingleton('customer/session')->logout();
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/customer/social/login', 'POST', [
        'provider' => $provider,
        'token' => $token,
        'nonce' => (string) $nonce,
    ]));
    $request->setRouteName('sociallogin')->setControllerName('social')->setActionName('login')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Maho_SocialLogin_SocialController($request, $response)->loginAction();

    $body = (array) json_decode((string) $response->getBody(), true);
    $session = Mage::getSingleton('customer/session');
    $customerId = $session->isLoggedIn() ? (int) $session->getCustomerId() : null;
    if ($customerId) {
        $GLOBALS['socialParityCustomerIds'][] = $customerId;
    }
    return ['status' => $response->getHttpResponseCode(), 'message' => (string) ($body['message'] ?? ''), 'customer_id' => $customerId];
}

/**
 * @return array{status: int, message: string, customer_id: ?int}
 */
function socialViaApi(string $provider, string $token, ?string $nonce, ?string $twofaCode = null): array
{
    $dto = new SocialAuth();
    $dto->provider = $provider;
    $dto->providerToken = $token;
    $dto->nonce = $nonce;
    $dto->twofaCode = $twofaCode;
    $processor = new SocialAuthProcessor(TransportParity::adminSecurity(), new SocialParityJwtService());
    $result = TransportParity::api(fn() => $processor->process($dto, new Post(name: 'social_auth')));

    $customerId = $result['result'] instanceof SocialAuth ? (int) $result['result']->customer['id'] : null;
    if ($customerId) {
        $GLOBALS['socialParityCustomerIds'][] = $customerId;
    }
    return ['status' => $result['status'], 'message' => $result['message'], 'customer_id' => $customerId];
}

/**
 * Post the 2FA code of the challenge that the sign-in started on the storefront. Return the errors of the session.
 *
 * @return list<string>
 */
function socialTwofaViaWeb(string $code): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/customer/account/twofaChallengePost', 'POST', [
        'twofa_verification_code' => $code,
    ]));
    $request->setRouteName('customer')->setControllerName('account')->setActionName('twofaChallengePost')
        ->setControllerModule('Mage_Customer')->setDispatched(true);
    Mage::app()->setRequest($request);
    // A message that an earlier test left in the session is not an outcome of this action
    Mage::getSingleton('customer/session')->getMessages(true);
    new Mage_Customer_AccountController($request, new Mage_Core_Controller_Response_Http())->twofaChallengePostAction();

    $errors = [];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $message) {
        if ($message->getType() === Mage_Core_Model_Message::ERROR) {
            $errors[] = $message->getText();
        }
    }
    return $errors;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    SocialLoginJwt::seedJwksCache(SOCIAL_PARITY_KID, 'sociallogin_jwks_google');
    $store = Mage::app()->getStore();
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_GOOGLE_ENABLED, '1');
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_GOOGLE_CLIENT_ID, SOCIAL_PARITY_CLIENT_ID);
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_APPLE_ENABLED, '0');
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_ALLOW_REGISTRATION, '1');
    $store->setConfig(Maho_SocialLogin_Service_Identity::XML_PATH_RATE_LIMIT, '20');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    $GLOBALS['socialParityCustomerIds'] = [];
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    Mage::register('isSecureArea', true, true);
    foreach (array_unique($GLOBALS['socialParityCustomerIds']) as $customerId) {
        Mage::getModel('customer/customer')->load($customerId)->delete();
    }
    Mage::unregister('isSecureArea');
});

it('signs in the customer of one identity on both transports', function () {
    $email = 'social-parity-' . uniqid() . '@example.com';
    $sub = 'parity-sub-' . uniqid();
    $webNonce = Mage::getModel('sociallogin/nonce')->issue();

    $web = socialViaWeb('google', socialParityToken($email, $sub, $webNonce), $webNonce);
    $api = socialViaApi('google', socialParityToken($email, $sub, 'api-nonce'), 'api-nonce');

    expect($web['status'])->toBe(200)
        ->and($web['customer_id'])->not->toBeNull()
        ->and($api['status'])->toBe(200)
        ->and($api['customer_id'])->toBe($web['customer_id']);
});

it('refuses the same sign-in with the same message on both transports', function (string $provider, string $token, string $message) {
    $nonce = Mage::getModel('sociallogin/nonce')->issue();

    $web = socialViaWeb($provider, $token, $nonce);
    $api = socialViaApi($provider, $token, null);

    expect($web['status'])->toBe(400)
        ->and($web['message'])->toBe($message)
        ->and($api['status'])->toBe(400)
        ->and($api['message'])->toBe($message);
})->with([
    'an unknown provider' => ['myspace', 'irrelevant', 'Unknown sign-in provider.'],
    'a disabled provider' => ['apple', 'irrelevant', 'This sign-in method is not available.'],
    'a token that the provider did not sign' => ['google', 'garbage-token', 'Invalid authentication token.'],
]);

it('refuses a new account on both transports when social registration is disabled', function () {
    Mage::app()->getStore()->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_ALLOW_REGISTRATION, '0');
    $nonce = Mage::getModel('sociallogin/nonce')->issue();

    $web = socialViaWeb('google', socialParityToken('denied-' . uniqid() . '@example.com', 'sub-' . uniqid(), $nonce), $nonce);
    $api = socialViaApi('google', socialParityToken('denied-' . uniqid() . '@example.com', 'sub-' . uniqid()), null);

    $message = 'Creating new accounts with social sign-in is not allowed. Please register first.';
    expect($web['status'])->toBe(400)
        ->and($web['message'])->toBe($message)
        ->and($api['status'])->toBe(400)
        ->and($api['message'])->toBe($message);
});

it('counts every attempt of both transports against one limit', function () {
    Mage::app()->getStore()->setConfig(Maho_SocialLogin_Service_Identity::XML_PATH_RATE_LIMIT, '1');

    expect(socialViaWeb('google', 'garbage-token', null)['status'])->toBe(400);
    $api = socialViaApi('google', 'garbage-token', null);
    $web = socialViaWeb('google', 'garbage-token', null);

    expect($api['status'])->toBe(429)
        ->and($api['message'])->toBe('Too many requests. Please try again later.')
        ->and($web['status'])->toBe(429)
        ->and($web['message'])->toBe($api['message']);
});

describe('kept differences: each transport has its own nonce rule', function () {

    it('requires a nonce that the session issued on the web, and skips a missing nonce on the API', function () {
        $token = socialParityToken('no-nonce-' . uniqid() . '@example.com', 'sub-' . uniqid());

        $web = socialViaWeb('google', $token, null);
        $api = socialViaApi('google', $token, null);

        expect($web['status'])->toBe(400)
            ->and($web['message'])->toBe('Your sign-in attempt has expired. Please try again.')
            ->and($api['status'])->toBe(200);
    });
});

it('registers a new account through the customer service on both transports, with the rules of a social sign-up', function () {
    $store = Mage::app()->getStore();
    $store->setConfig('customer/create_account/confirm', '1');
    $store->setConfig('system/smtp/enabled', '');
    $spy = new class extends Mage_Customer_Service_Customer {
        /** @var list<array{string, string}> */
        public array $emails = [];

        #[\Override]
        protected function sendNewAccountEmail(Mage_Customer_Model_Customer $customer, string $type, int $storeId, string $backUrl = ''): void
        {
            $this->emails[] = [$type, (string) $customer->getEmail()];
        }
    };
    Mage::unregister('_service/customer/customer');
    Mage::register('_service/customer/customer', $spy);
    // The provider gives no date of birth, so a store that requires it still gets the account
    $dob = Mage::getModel('customer/attribute')->loadByCode('customer', 'dob');
    $wasRequired = (int) $dob->getIsRequired();
    $dob->setIsRequired(1)->save();

    try {
        $webEmail = 'social-register-' . uniqid() . '@example.com';
        $apiEmail = 'social-register-' . uniqid() . '@example.com';
        $webNonce = Mage::getModel('sociallogin/nonce')->issue();
        $web = socialViaWeb('google', socialParityToken($webEmail, 'sub-' . uniqid(), $webNonce), $webNonce);
        $api = socialViaApi('google', socialParityToken($apiEmail, 'sub-' . uniqid()), null);
    } finally {
        $dob->setIsRequired($wasRequired)->save();
        Mage::unregister('_service/customer/customer');
    }

    $webCustomer = Mage::getModel('customer/customer')->load($web['customer_id']);
    $apiCustomer = Mage::getModel('customer/customer')->load($api['customer_id']);
    expect($web['status'])->toBe(200)
        ->and($api['status'])->toBe(200)
        ->and($webCustomer->getEmail())->toBe($webEmail)
        ->and($apiCustomer->getEmail())->toBe($apiEmail)
        ->and($webCustomer->getConfirmation())->toBeNull()
        ->and($apiCustomer->getConfirmation())->toBeNull()
        ->and($spy->emails)->toBe([['registered', $webEmail], ['registered', $apiEmail]]);
});

it('counts each 2FA code of both transports against the login limit of the email', function () {
    $store = Mage::app()->getStore();
    $store->setConfig('customer/password/allow_2fa', '1');
    $store->setConfig(Mage_Customer_Service_Customer::XML_PATH_LOGIN_RATE_LIMIT, '2');
    $email = 'social-twofa-' . uniqid() . '@example.com';
    $sub = 'twofa-sub-' . uniqid();
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) $store->getWebsiteId())
        ->setStoreId((int) $store->getId())
        ->setEmail($email)
        ->setFirstname('Social')
        ->setLastname('Parity')
        ->setPassword('Password123!')
        ->setTwofaSecret(Mage::helper('core/security')->generateTotpSecret())
        ->setTwofaEnabled();
    $customer->save();
    $GLOBALS['socialParityCustomerIds'][] = (int) $customer->getId();

    $webNonce = Mage::getModel('sociallogin/nonce')->issue();
    $signIn = socialViaWeb('google', socialParityToken($email, $sub, $webNonce), $webNonce);
    $web = socialTwofaViaWeb('000000');
    $api = socialViaApi('google', socialParityToken($email, $sub), null, '000000');
    $webOverLimit = socialTwofaViaWeb('000000');
    $apiOverLimit = socialViaApi('google', socialParityToken($email, $sub), null, '000000');

    expect($signIn['status'])->toBe(200)
        ->and($signIn['customer_id'])->toBeNull()
        ->and($web)->toBe(['2FA verification code is invalid.'])
        ->and($api['status'])->toBe(401)
        ->and($webOverLimit)->toBe(['Too many requests. Please try again later.'])
        ->and($apiOverLimit['status'])->toBe(429)
        ->and(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse();
});
