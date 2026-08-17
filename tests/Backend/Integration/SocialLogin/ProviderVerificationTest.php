<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Helpers\SocialLoginJwt;

uses(Tests\MahoBackendTestCase::class);

const SL_TEST_KID = 'sociallogin-test-key';
const SL_GOOGLE_CLIENT_ID = 'test-google-client.apps.googleusercontent.com';
const SL_APPLE_SERVICE_ID = 'com.example.test.service';

/**
 * @param array<string, mixed> $claims
 */
function slIdToken(string $issuer, string $audience, array $claims = [], ?DateTimeImmutable $expiresAt = null): string
{
    return SocialLoginJwt::idToken(SL_TEST_KID, $issuer, $audience, $claims, $expiresAt);
}

beforeEach(function () {
    SocialLoginJwt::seedJwksCache(SL_TEST_KID, 'sociallogin_jwks_google', 'sociallogin_jwks_apple');
    $store = Mage::app()->getStore();
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_GOOGLE_ENABLED, '1');
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_GOOGLE_CLIENT_ID, SL_GOOGLE_CLIENT_ID);
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_APPLE_ENABLED, '1');
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_APPLE_SERVICE_ID, SL_APPLE_SERVICE_ID);
    $this->storeId = (int) $store->getId();
});

it('converts an RSA JWK to a PEM that OpenSSL accepts', function () {
    $pair = SocialLoginJwt::keypair();
    $pem = Maho_SocialLogin_Model_JwkToPem::convert([
        'kty' => 'RSA',
        'n' => Base64UrlSafe::encodeUnpadded($pair['n']),
        'e' => Base64UrlSafe::encodeUnpadded($pair['e']),
    ]);
    $key = openssl_pkey_get_public($pem);
    expect($key)->not->toBeFalse();
    expect(trim($pem))->toBe(trim($pair['public']));
});

it('verifies a valid Google ID token and returns normalized claims', function () {
    $token = slIdToken('https://accounts.google.com', SL_GOOGLE_CLIENT_ID, [
        'sub' => 'google-sub-1',
        'email' => 'User@Example.com',
        'email_verified' => true,
        'given_name' => 'Uma',
        'family_name' => 'User',
    ]);
    $claims = Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId);
    expect($claims['sub'])->toBe('google-sub-1')
        ->and($claims['email'])->toBe('user@example.com')
        ->and($claims['given_name'])->toBe('Uma');
});

it('rejects a Google token with the wrong audience', function () {
    $token = slIdToken('https://accounts.google.com', 'other-client', [
        'sub' => 's',
        'email' => 'a@b.c',
        'email_verified' => true,
    ]);
    Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId);
})->throws(InvalidArgumentException::class);

it('rejects a Google token with the wrong issuer', function () {
    $token = slIdToken('https://evil.example.com', SL_GOOGLE_CLIENT_ID, [
        'sub' => 's',
        'email' => 'a@b.c',
        'email_verified' => true,
    ]);
    Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId);
})->throws(InvalidArgumentException::class);

it('rejects an expired Google token', function () {
    $expired = new DateTimeImmutable('-1 hour', new DateTimeZone('UTC'));
    $token = slIdToken('https://accounts.google.com', SL_GOOGLE_CLIENT_ID, [
        'sub' => 's',
        'email' => 'a@b.c',
        'email_verified' => true,
    ], $expired);
    Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId);
})->throws(InvalidArgumentException::class);

it('rejects a Google token whose email is not verified', function () {
    $token = slIdToken('https://accounts.google.com', SL_GOOGLE_CLIENT_ID, [
        'sub' => 's',
        'email' => 'a@b.c',
        'email_verified' => false,
    ]);
    Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId);
})->throws(InvalidArgumentException::class);

it('rejects a Google token whose nonce does not match', function () {
    $token = slIdToken('https://accounts.google.com', SL_GOOGLE_CLIENT_ID, [
        'sub' => 's',
        'email' => 'a@b.c',
        'email_verified' => true,
        'nonce' => 'other-nonce',
    ]);
    Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId, 'expected-nonce');
})->throws(InvalidArgumentException::class);

it('rejects a Google token without a nonce when one is expected', function () {
    $token = slIdToken('https://accounts.google.com', SL_GOOGLE_CLIENT_ID, [
        'sub' => 's',
        'email' => 'a@b.c',
        'email_verified' => true,
    ]);
    Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId, 'expected-nonce');
})->throws(InvalidArgumentException::class);

it('accepts a Google token with the expected nonce', function () {
    $token = slIdToken('https://accounts.google.com', SL_GOOGLE_CLIENT_ID, [
        'sub' => 'nonce-sub',
        'email' => 'a@b.c',
        'email_verified' => true,
        'nonce' => 'expected-nonce',
    ]);
    $claims = Mage::getModel('sociallogin/provider_google')->verify($token, $this->storeId, 'expected-nonce');
    expect($claims['sub'])->toBe('nonce-sub');
});

it('accepts an Apple token with a string "true" email_verified claim', function () {
    $token = slIdToken('https://appleid.apple.com', SL_APPLE_SERVICE_ID, [
        'sub' => 'apple-sub-1',
        'email' => 'relay@privaterelay.appleid.com',
        'email_verified' => 'true',
        'is_private_email' => 'true',
    ]);
    $claims = Mage::getModel('sociallogin/provider_apple')->verify($token, $this->storeId);
    expect($claims['sub'])->toBe('apple-sub-1')
        ->and($claims['email'])->toBe('relay@privaterelay.appleid.com');
});

it('rejects an Apple token without an email claim', function () {
    $token = slIdToken('https://appleid.apple.com', SL_APPLE_SERVICE_ID, [
        'sub' => 'apple-sub-2',
    ]);
    Mage::getModel('sociallogin/provider_apple')->verify($token, $this->storeId);
})->throws(InvalidArgumentException::class);

it('accepts an Apple token whose nonce claim is the SHA-256 of the expected nonce', function () {
    $token = slIdToken('https://appleid.apple.com', SL_APPLE_SERVICE_ID, [
        'sub' => 'apple-sub-3',
        'email' => 'a@b.c',
        'email_verified' => true,
        'nonce' => hash('sha256', 'expected-nonce'),
    ]);
    $claims = Mage::getModel('sociallogin/provider_apple')->verify($token, $this->storeId, 'expected-nonce');
    expect($claims['sub'])->toBe('apple-sub-3');
});

function slFacebookProvider(array $responses): Maho_SocialLogin_Model_Provider_Facebook
{
    $store = Mage::app()->getStore();
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_FACEBOOK_ENABLED, '1');
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_FACEBOOK_APP_ID, 'test-app-id');
    $store->setConfig(Maho_SocialLogin_Helper_Data::XML_PATH_FACEBOOK_APP_SECRET, 'test-app-secret');

    $provider = Mage::getModel('sociallogin/provider_facebook');
    $provider->setHttpClient(new MockHttpClient(array_map(
        fn(array $body) => new MockResponse(json_encode($body)),
        $responses,
    )));
    return $provider;
}

it('verifies a valid Facebook access token', function () {
    $provider = slFacebookProvider([
        ['data' => ['is_valid' => true, 'app_id' => 'test-app-id', 'user_id' => 'fb-1', 'expires_at' => time() + 3600]],
        ['id' => 'fb-1', 'email' => 'FB@Example.com', 'first_name' => 'Fab', 'last_name' => 'Book', 'name' => 'Fab Book'],
    ]);
    $claims = $provider->verify('fb-token', (int) Mage::app()->getStore()->getId());
    expect($claims['sub'])->toBe('fb-1')
        ->and($claims['email'])->toBe('fb@example.com')
        ->and($claims['given_name'])->toBe('Fab');
});

it('rejects a Facebook token that debug_token marks invalid', function () {
    $provider = slFacebookProvider([
        ['data' => ['is_valid' => false]],
    ]);
    $provider->verify('fb-token', (int) Mage::app()->getStore()->getId());
})->throws(InvalidArgumentException::class);

it('rejects a Facebook token issued for another app', function () {
    $provider = slFacebookProvider([
        ['data' => ['is_valid' => true, 'app_id' => 'other-app', 'user_id' => 'fb-1']],
    ]);
    $provider->verify('fb-token', (int) Mage::app()->getStore()->getId());
})->throws(InvalidArgumentException::class);

it('rejects a Facebook profile without an email', function () {
    $provider = slFacebookProvider([
        ['data' => ['is_valid' => true, 'app_id' => 'test-app-id', 'user_id' => 'fb-1']],
        ['id' => 'fb-1', 'first_name' => 'No', 'last_name' => 'Email'],
    ]);
    $provider->verify('fb-token', (int) Mage::app()->getStore()->getId());
})->throws(InvalidArgumentException::class);
