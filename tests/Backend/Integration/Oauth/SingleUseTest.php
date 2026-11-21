<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class OauthSingleUseServer extends Mage_Oauth_Model_Server
{
    public bool $acceptSignature = false;

    public function __construct(private readonly string $nonce) {}

    public function process(): void
    {
        $this->_processRequest(self::REQUEST_RESOURCE);
    }

    #[\Override]
    protected function _fetchParams(): void
    {
        $this->_protocolParams = [
            'oauth_consumer_key' => str_repeat('c', Mage_Oauth_Model_Consumer::KEY_LENGTH),
            'oauth_signature_method' => self::SIGNATURE_HMAC,
            'oauth_signature' => 'signature',
            'oauth_nonce' => $this->nonce,
            'oauth_timestamp' => (string) time(),
        ];
    }

    #[\Override]
    protected function _initConsumer(): void
    {
        $this->_consumer = new Maho\DataObject(['secret' => 'consumer-secret']);
    }

    #[\Override]
    protected function _initToken(): void
    {
        $this->_token = new Maho\DataObject(['secret' => 'token-secret']);
    }

    #[\Override]
    protected function _validateSignature(): void
    {
        if (!$this->acceptSignature) {
            $this->_throwException('', self::ERR_SIGNATURE_INVALID);
        }
    }
}

function oauthSingleUseNonceExists(string $nonce): bool
{
    return (bool) Mage::getModel('oauth/nonce')->load($nonce, 'nonce')->getTimestamp();
}

it('claims a nonce only after the signature is accepted', function (): void {
    $nonce = bin2hex(random_bytes(16));
    $server = new OauthSingleUseServer($nonce);

    try {
        expect(fn() => $server->process())->toThrow(Mage_Core_Exception::class)
            ->and(oauthSingleUseNonceExists($nonce))->toBeFalse();

        $server->acceptSignature = true;
        $server->process();
        expect(oauthSingleUseNonceExists($nonce))->toBeTrue()
            ->and(fn() => $server->process())->toThrow(Mage_Core_Exception::class);
    } finally {
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
            Mage::getSingleton('core/resource')->getTableName('oauth/nonce'),
            ['nonce = ?' => $nonce],
        );
    }
});

it('rejects a replayed nonce even when its timestamp differs', function (): void {
    $nonce = bin2hex(random_bytes(16));
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('oauth/nonce');
    $connection->insert($table, [
        'nonce' => $nonce,
        'timestamp' => time() - 1,
    ]);

    $server = new OauthSingleUseServer($nonce);
    $server->acceptSignature = true;

    try {
        $error = null;
        try {
            $server->process();
        } catch (Mage_Core_Exception $exception) {
            $error = $exception->getCode();
        }

        expect($error)->toBe(Mage_Oauth_Model_Server::ERR_NONCE_USED);
    } finally {
        $connection->delete($table, ['nonce = ?' => $nonce]);
    }
});

it('converts one request token to access only once', function (): void {
    $helper = Mage::helper('oauth');
    $consumer = Mage::getModel('oauth/consumer')->setData([
        'name' => 'Single use ' . uniqid(),
        'key' => $helper->generateConsumerKey(),
        'secret' => $helper->generateConsumerSecret(),
    ])->save();
    $request = Mage::getModel('oauth/token')->setData([
        'consumer_id' => $consumer->getId(),
        'type' => Mage_Oauth_Model_Token::TYPE_REQUEST,
        'token' => $helper->generateToken(),
        'secret' => $helper->generateTokenSecret(),
        'verifier' => $helper->generateVerifier(),
        'callback_url' => Mage_Oauth_Model_Server::CALLBACK_ESTABLISHED,
    ])->save();
    $first = Mage::getModel('oauth/token')->load($request->getId());
    $second = Mage::getModel('oauth/token')->load($request->getId());

    try {
        $first->convertToAccess();
        $token = (string) $first->getToken();
        $error = null;

        try {
            $second->convertToAccess();
        } catch (Mage_Core_Exception $e) {
            $error = $e->getCode();
        }

        expect($error)->toBe(Mage_Oauth_Model_Server::ERR_TOKEN_USED)
            ->and(Mage::getModel('oauth/token')->load($request->getId())->getToken())->toBe($token);
    } finally {
        $request->delete();
        $consumer->delete();
    }
});
