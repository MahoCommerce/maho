<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\SocialLoginJwt;

uses(Tests\MahoFrontendTestCase::class);

const SLF_KID = 'sociallogin-front-test-key';
const SLF_CLIENT_ID = 'front-test-client.apps.googleusercontent.com';

function slfGoogleToken(string $email, string $sub, ?string $nonce = null): string
{
    $claims = [
        'sub' => $sub,
        'email' => $email,
        'email_verified' => true,
        'given_name' => 'Social',
        'family_name' => 'Tester',
    ];
    if ($nonce !== null) {
        $claims['nonce'] = $nonce;
    }
    return SocialLoginJwt::idToken(SLF_KID, 'https://accounts.google.com', SLF_CLIENT_ID, $claims);
}

function slfDispatchLogin(array $post): Mage_Core_Controller_Response_Http
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sociallogin/auth/login', 'POST', $post),
    );
    $request->setRouteName('sociallogin')
        ->setControllerName('auth')
        ->setActionName('login')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $response = new Mage_Core_Controller_Response_Http();
    (new Maho_SocialLogin_AuthController($request, $response))->loginAction();
    return $response;
}

function slfResponseJson(Mage_Core_Controller_Response_Http $response): array
{
    return (array) json_decode((string) $response->getBody(), true);
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();

    SocialLoginJwt::seedJwksCache(SLF_KID, 'sociallogin_jwks_google');

    $store = Mage::app()->getStore();
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_GOOGLE_ENABLED, '1');
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_GOOGLE_CLIENT_ID, SLF_CLIENT_ID);
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_ALLOW_REGISTRATION, '1');

    $this->formKey = Mage::getSingleton('core/session')->getFormKey();
    $this->createdCustomerIds = [];
});

afterEach(function () {
    if ($this->createdCustomerIds !== []) {
        Mage::register('isSecureArea', true, true);
        foreach ($this->createdCustomerIds as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            if ($customer->getId()) {
                $customer->delete();
            }
        }
        Mage::unregister('isSecureArea');
    }
    Mage::getSingleton('customer/session')->logout();
});

it('rejects a login request without a valid form key', function () {
    $response = slfDispatchLogin([
        'provider' => 'google',
        'token' => 'irrelevant',
    ]);
    expect($response->getHttpResponseCode())->toBe(403);
    expect(slfResponseJson($response)['error'])->toBeTrue();
});

it('rejects an unknown provider', function () {
    $nonce = Mage::getModel('sociallogin/nonce')->issue();
    $response = slfDispatchLogin([
        'form_key' => $this->formKey,
        'provider' => 'myspace',
        'token' => 'irrelevant',
        'nonce' => $nonce,
    ]);
    expect($response->getHttpResponseCode())->toBe(400);
});

it('logs in with a valid Google token, creates the customer, and links the identity', function () {
    $email = 'social-' . uniqid() . '@example.com';
    $sub = 'front-sub-' . uniqid();
    $nonce = Mage::getModel('sociallogin/nonce')->issue();

    $response = slfDispatchLogin([
        'form_key' => $this->formKey,
        'provider' => 'google',
        'token' => slfGoogleToken($email, $sub, $nonce),
        'nonce' => $nonce,
    ]);

    $json = slfResponseJson($response);
    expect($json['success'] ?? false)->toBeTrue();

    $session = Mage::getSingleton('customer/session');
    expect($session->isLoggedIn())->toBeTrue();
    $customerId = (int) $session->getCustomerId();
    $this->createdCustomerIds[] = $customerId;

    $customer = Mage::getModel('customer/customer')->load($customerId);
    expect($customer->getEmail())->toBe($email)
        ->and($customer->getFirstname())->toBe('Social');

    $identity = Mage::getModel('sociallogin/identity')->loadByProviderIdentity('google', $sub, null);
    expect($identity->getId())->not->toBeNull()
        ->and($identity->getCustomerId())->toBe($customerId);
});

it('rejects a reused nonce', function () {
    $email = 'social-' . uniqid() . '@example.com';
    $sub = 'front-sub-' . uniqid();
    $nonce = Mage::getModel('sociallogin/nonce')->issue();

    $first = slfDispatchLogin([
        'form_key' => $this->formKey,
        'provider' => 'google',
        'token' => slfGoogleToken($email, $sub, $nonce),
        'nonce' => $nonce,
    ]);
    expect(slfResponseJson($first)['success'] ?? false)->toBeTrue();
    $this->createdCustomerIds[] = (int) Mage::getSingleton('customer/session')->getCustomerId();
    Mage::getSingleton('customer/session')->logout();

    // The first login renewed the form key
    $second = slfDispatchLogin([
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        'provider' => 'google',
        'token' => slfGoogleToken($email, $sub, $nonce),
        'nonce' => $nonce,
    ]);
    expect($second->getHttpResponseCode())->toBe(400);
    expect(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse();
});

it('auto-links to an existing customer with the same verified email', function () {
    $email = 'social-link-' . uniqid() . '@example.com';
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail($email)
        ->setFirstname('Existing')
        ->setLastname('Customer')
        ->setPassword('SomePassword123!');
    $customer->save();
    $existingId = (int) $customer->getId();
    $this->createdCustomerIds[] = $existingId;

    $sub = 'front-sub-' . uniqid();
    $nonce = Mage::getModel('sociallogin/nonce')->issue();
    $response = slfDispatchLogin([
        'form_key' => $this->formKey,
        'provider' => 'google',
        'token' => slfGoogleToken($email, $sub, $nonce),
        'nonce' => $nonce,
    ]);

    expect(slfResponseJson($response)['success'] ?? false)->toBeTrue();
    $session = Mage::getSingleton('customer/session');
    expect($session->isLoggedIn())->toBeTrue()
        ->and((int) $session->getCustomerId())->toBe($existingId);

    $identity = Mage::getModel('sociallogin/identity')->loadByProviderIdentity('google', $sub, null);
    expect($identity->getCustomerId())->toBe($existingId);
    // The existing customer record was reused, not duplicated
    expect($session->getCustomer()->getFirstname())->toBe('Existing');
});

it('redirects a new customer to the account edit page when a required profile field is empty', function () {
    $entityType = Mage::getSingleton('eav/config')->getEntityType('customer');
    // A visible registration-form attribute: the check skips hidden ones, which
    // the customer could not fill in on the edit page anyway
    $attribute = Mage::getModel('customer/attribute')->loadByCode($entityType, 'middlename');
    $wasRequired = (int) $attribute->getIsRequired();
    $attribute->setIsRequired(1)->save();

    try {
        $email = 'social-profile-' . uniqid() . '@example.com';
        $nonce = Mage::getModel('sociallogin/nonce')->issue();
        $response = slfDispatchLogin([
            'form_key' => $this->formKey,
            'provider' => 'google',
            'token' => slfGoogleToken($email, 'front-sub-' . uniqid(), $nonce),
            'nonce' => $nonce,
        ]);

        $json = slfResponseJson($response);
        expect($json['success'] ?? false)->toBeTrue()
            ->and($json['redirect'])->toContain('customer/account/edit');
        $this->createdCustomerIds[] = (int) Mage::getSingleton('customer/session')->getCustomerId();
    } finally {
        $attribute->setIsRequired($wasRequired)->save();
    }
});

it('redirects to the session before-auth URL and ignores a client-posted redirect', function () {
    $checkoutUrl = Mage::getUrl('checkout/onepage');
    Mage::getSingleton('customer/session')->setBeforeAuthUrl($checkoutUrl);

    $email = 'social-redirect-' . uniqid() . '@example.com';
    $nonce = Mage::getModel('sociallogin/nonce')->issue();
    $response = slfDispatchLogin([
        'form_key' => $this->formKey,
        'provider' => 'google',
        'token' => slfGoogleToken($email, 'front-sub-' . uniqid(), $nonce),
        'nonce' => $nonce,
        'redirect' => '/evil-client-path',
    ]);

    $json = slfResponseJson($response);
    expect($json['success'] ?? false)->toBeTrue()
        ->and($json['redirect'])->toContain('checkout/onepage');
    $this->createdCustomerIds[] = (int) Mage::getSingleton('customer/session')->getCustomerId();
    // The before-auth URL is consumed on use
    expect(Mage::getSingleton('customer/session')->getBeforeAuthUrl())->toBe('');
});

it('challenges a 2FA-enabled customer instead of logging the session in', function () {
    Mage::app()->getStore()->setConfig('customer/password/allow_2fa', '1');

    $email = 'social-twofa-' . uniqid() . '@example.com';
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail($email)
        ->setFirstname('Twofa')
        ->setLastname('Customer')
        ->setPassword('SomePassword123!')
        ->setTwofaEnabled(true);
    $customer->setTwofaSecret('JBSWY3DPEHPK3PXP');
    $customer->save();
    $this->createdCustomerIds[] = (int) $customer->getId();

    $nonce = Mage::getModel('sociallogin/nonce')->issue();
    $response = slfDispatchLogin([
        'form_key' => $this->formKey,
        'provider' => 'google',
        'token' => slfGoogleToken($email, 'front-sub-' . uniqid(), $nonce),
        'nonce' => $nonce,
    ]);

    $json = slfResponseJson($response);
    expect($json['success'] ?? false)->toBeTrue()
        ->and($json['redirect'])->toContain('twofaChallenge');
    expect(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse();
});

it('refuses to create an account when social registration is disabled', function () {
    Mage::app()->getStore()->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_ALLOW_REGISTRATION, '0');

    $email = 'social-denied-' . uniqid() . '@example.com';
    $nonce = Mage::getModel('sociallogin/nonce')->issue();
    $response = slfDispatchLogin([
        'form_key' => $this->formKey,
        'provider' => 'google',
        'token' => slfGoogleToken($email, 'front-sub-' . uniqid(), $nonce),
        'nonce' => $nonce,
    ]);

    expect($response->getHttpResponseCode())->toBe(400);
    expect(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse();

    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
    $customer->loadByEmail($email);
    expect($customer->getId())->toBeNull();
});
