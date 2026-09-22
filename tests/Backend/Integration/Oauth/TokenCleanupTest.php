<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The token cleanup runs on a random probability after any token save, so its query must be
 * portable. A string literal in double quotes is a column name on PostgreSQL.
 */
function createOauthCleanupToken(string $type, int $ageMinutes): Mage_Oauth_Model_Token
{
    $helper = Mage::helper('oauth');

    $consumer = Mage::getModel('oauth/consumer')->setData([
        'name' => 'Cleanup ' . uniqid(),
        'key' => $helper->generateConsumerKey(),
        'secret' => $helper->generateConsumerSecret(),
    ]);
    $consumer->save();

    $token = Mage::getModel('oauth/token')->setData([
        'consumer_id' => $consumer->getId(),
        'type' => $type,
        'token' => $helper->generateToken(),
        'secret' => $helper->generateTokenSecret(),
        'verifier' => $helper->generateVerifier(),
        'callback_url' => Mage_Oauth_Model_Server::CALLBACK_ESTABLISHED,
    ]);
    $token->save();

    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('oauth/token'),
        ['created_at' => date(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $ageMinutes * 60)],
        ['entity_id = ?' => (int) $token->getId()],
    );

    return $token;
}

function oauthTokenExists(int $tokenId): bool
{
    return (bool) Mage::getModel('oauth/token')->load($tokenId)->getId();
}

it('deletes an old request token and keeps an old access token', function () {
    $request = createOauthCleanupToken(Mage_Oauth_Model_Token::TYPE_REQUEST, 120);
    $access = createOauthCleanupToken(Mage_Oauth_Model_Token::TYPE_ACCESS, 120);
    $fresh = createOauthCleanupToken(Mage_Oauth_Model_Token::TYPE_REQUEST, 0);

    Mage::getResourceModel('oauth/token')->deleteOldEntries(60);

    expect(oauthTokenExists((int) $request->getId()))->toBeFalse()
        ->and(oauthTokenExists((int) $access->getId()))->toBeTrue()
        ->and(oauthTokenExists((int) $fresh->getId()))->toBeTrue();
});

it('deletes nothing when the cleanup period is zero', function () {
    $request = createOauthCleanupToken(Mage_Oauth_Model_Token::TYPE_REQUEST, 120);

    expect(Mage::getResourceModel('oauth/token')->deleteOldEntries(0))->toBe(0)
        ->and(oauthTokenExists((int) $request->getId()))->toBeTrue();
});
