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

        validateInitiateCallback($consumer, 'https://legit.com/cb?x=1');
        validateInitiateCallback($consumer, Mage_Oauth_Model_Server::CALLBACK_ESTABLISHED);
        expect(true)->toBeTrue();
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

        validateInitiateCallback($consumer, 'https://anywhere.example/cb');
        expect(fn() => validateInitiateCallback($consumer, 'not a url'))->toThrow(Mage_Oauth_Exception::class);
    });
});
