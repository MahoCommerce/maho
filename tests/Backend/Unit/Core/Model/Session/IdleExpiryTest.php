<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoBackendTestCase::class);

/**
 * File sessions have no storage-level expiry, so the session is expired when it is read.
 */

function idleSession(?int $idleSeconds): Session
{
    $storage = new MockArraySessionStorage(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE);
    if ($idleSeconds !== null) {
        $storage->setSessionData([
            '_sf2_meta' => ['c' => time() - $idleSeconds - 60, 'u' => time() - $idleSeconds, 'l' => 604800],
        ]);
    }

    $session = new Session($storage);
    $session->start();

    return $session;
}

function expireIdle(Mage_Core_Model_Session_Abstract $model, Session $session, ?int $lastUsed = null): bool
{
    return (new ReflectionMethod(Mage_Core_Model_Session_Abstract::class, 'expireIdleSession'))
        ->invoke($model, $session, $lastUsed ?? $session->getMetadataBag()->getLastUsed());
}

function sessionWithLifetime(int $lifetime): Mage_Core_Model_Session_Abstract
{
    return Mage::getSingleton('core/session')->setSessionLifetime($lifetime);
}

beforeEach(function () {
    $_SESSION = [];

    // Keep customer accounts global so the customer session namespace does not resolve a website
    Mage::app()->getStore()->setConfig(
        Mage_Customer_Model_Config_Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE,
        (string) Mage_Customer_Model_Config_Share::SHARE_GLOBAL,
    );
});

afterEach(function () {
    $_SESSION = [];
});

it('expires a session left idle for longer than its lifetime', function () {
    $session = idleSession(604800 + 60);
    $originalId = $session->getId();

    expect(expireIdle(sessionWithLifetime(604800), $session))->toBeTrue()
        ->and($session->getId())->not->toBe($originalId);
});

it('keeps a session still inside its lifetime', function () {
    $_SESSION['core'] = ['visitor' => 1];
    $session = idleSession(604800 - 60);
    $originalId = $session->getId();

    expect(expireIdle(sessionWithLifetime(604800), $session))->toBeFalse()
        ->and($session->getId())->toBe($originalId)
        ->and($_SESSION['core'])->toBe(['visitor' => 1]);
});

it('keeps a session sitting exactly on the boundary', function () {
    expect(expireIdle(sessionWithLifetime(604800), idleSession(604800)))->toBeFalse();
});

it('never expires a session that carries no metadata', function () {
    // Also every session written before read-time expiry existed
    expect(expireIdle(sessionWithLifetime(60), idleSession(null)))->toBeFalse();
});

it('does not expire when the clock has stepped backwards', function () {
    expect(expireIdle(sessionWithLifetime(604800), idleSession(60), time() + 3600))->toBeFalse();
});

it('honors the Remember Me lifetime an observer supplied', function () {
    // Why the check runs after session_before_renew_cookie
    $tenDaysIdle = 10 * 86400;

    expect(expireIdle(sessionWithLifetime(2592000), idleSession($tenDaysIdle)))->toBeFalse()
        ->and(expireIdle(sessionWithLifetime(604800), idleSession($tenDaysIdle)))->toBeTrue();
});

it('stops a session model constructed during the dispatch from serving the expired data', function () {
    // Regression: invalidate() reassigns $_SESSION, which does not write through the reference
    // init() binds, so the observer's customer/session kept reporting the customer as logged in
    $customerSession = Mage::getSingleton('customer/session');
    $customerSession->setCustomerId(42);

    expect(expireIdle(sessionWithLifetime(604800), idleSession(604800 + 60)))->toBeTrue()
        ->and($customerSession->getCustomerId())->toBeNull();
});

it('clears the validator keys instead of leaving them set but empty', function () {
    $_SESSION[Mage_Core_Model_Session_Abstract::VALIDATOR_KEY] = ['remote_addr' => '127.0.0.1'];
    $_SESSION[Mage_Core_Model_Session_Abstract::SECURE_COOKIE_CHECK_KEY] = md5('x');

    expect(expireIdle(sessionWithLifetime(604800), idleSession(604800 + 60)))->toBeTrue()
        ->and(isset($_SESSION[Mage_Core_Model_Session_Abstract::VALIDATOR_KEY]))->toBeFalse()
        ->and(isset($_SESSION[Mage_Core_Model_Session_Abstract::SECURE_COOKIE_CHECK_KEY]))->toBeFalse();
});

it('uses the last-used value captured before the observers ran', function () {
    // A check reading getLastUsed() at this point would never expire anything
    $session = idleSession(604800 + 60);
    $captured = $session->getMetadataBag()->getLastUsed();
    $session->getMetadataBag()->stampNew(604800);

    expect(expireIdle(sessionWithLifetime(604800), $session, $captured))->toBeTrue();
});
