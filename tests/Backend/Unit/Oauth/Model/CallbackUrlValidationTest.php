<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A consumer with a registered callback URL only receives tokens on that URL. The
 * initiate request refuses any other callback.
 */
function oauthConsumerWithCallback(?string $callbackUrl): Mage_Oauth_Model_Consumer
{
    $helper = Mage::helper('oauth');

    return Mage::getModel('oauth/consumer')->setData([
        'entity_id' => 1,
        'name' => 'Test consumer',
        'key' => $helper->generateConsumerKey(),
        'secret' => $helper->generateConsumerSecret(),
        'callback_url' => $callbackUrl,
    ]);
}

function validateInitiateCallback(Mage_Oauth_Model_Consumer $consumer, string $callbackUrl): void
{
    $server = Mage::getModel('oauth/server');
    $reflection = new ReflectionObject($server);
    $reflection->getProperty('_consumer')->setValue($server, $consumer);
    $reflection->getProperty('_protocolParams')->setValue($server, ['oauth_callback' => $callbackUrl]);
    $reflection->getMethod('_validateCallbackUrlParam')->invoke($server);
}

describe('Mage_Oauth_Model_Server initiate request', function () {
    it('accepts the registered callback and the out-of-band marker', function () {
        $consumer = oauthConsumerWithCallback('https://legit.com/cb');

        expect(fn() => validateInitiateCallback($consumer, 'https://legit.com/cb?x=1'))->not->toThrow(Mage_Oauth_Exception::class)
            ->and(fn() => validateInitiateCallback($consumer, Mage_Oauth_Model_Server::CALLBACK_ESTABLISHED))->not->toThrow(Mage_Oauth_Exception::class);
    });

    it('rejects a callback on another host even when it is a valid URL', function (string $callbackUrl) {
        $consumer = oauthConsumerWithCallback('https://legit.com/cb');

        expect(fn() => validateInitiateCallback($consumer, $callbackUrl))->toThrow(Mage_Oauth_Exception::class);
    })->with([
        'host suffix' => 'https://legit.com.evil.com/cb',
        'userinfo' => 'https://legit.com@evil.com/cb',
        'unrelated host' => 'https://evil.com/cb',
    ]);

    it('falls back to URL validation when the consumer has no registered callback', function () {
        $consumer = oauthConsumerWithCallback(null);

        expect(fn() => validateInitiateCallback($consumer, 'https://anywhere.example/cb'))->not->toThrow(Mage_Oauth_Exception::class)
            ->and(fn() => validateInitiateCallback($consumer, 'not a url'))->toThrow(Mage_Oauth_Exception::class);
    });
});

describe('Mage_Oauth_Model_Consumer callback URL', function () {
    it('accepts an absolute URL, a custom scheme, or no URL at all', function (?string $callbackUrl) {
        expect(fn() => oauthConsumerWithCallback($callbackUrl)->validate())->not->toThrow(Mage_Core_Exception::class);
    })->with([
        'https' => 'https://legit.com/cb',
        'custom scheme' => 'myapp:callback',
        'empty' => '',
        'null' => null,
    ]);

    it('refuses a registered URL the allowlist could never match', function (string $callbackUrl) {
        expect(fn() => oauthConsumerWithCallback($callbackUrl)->validate())->toThrow(Mage_Core_Exception::class);
    })->with([
        'no scheme' => 'legit.com/cb',
        'no scheme with port' => 'legit.com:8080/cb',
        'userinfo' => 'https://user:pw@legit.com/cb',
    ]);

    it('refuses a rejected callback URL without a scheme', function () {
        $consumer = oauthConsumerWithCallback('https://legit.com/cb')->setData('rejected_callback_url', 'legit.com/rejected');

        expect(fn() => $consumer->validate())->toThrow(Mage_Core_Exception::class);
    });
});
