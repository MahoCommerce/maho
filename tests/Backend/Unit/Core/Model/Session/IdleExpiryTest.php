<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoBackendTestCase::class);

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
    $session = Mage::getSingleton('core/session');
    (new ReflectionProperty(Mage_Core_Model_Session_Abstract::class, 'sessionLifetime'))
        ->setValue($session, $lifetime);

    return $session;
}

function isForeign(string $sessionName): bool
{
    $model = Mage::getSingleton('core/session');
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, new Session(new MockArraySessionStorage($sessionName)), true);

    return (new ReflectionMethod(Mage_Core_Model_Session_Abstract::class, 'isForeignNamespace'))
        ->invoke($model);
}

beforeEach(function () {
    $_SESSION = [];
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);

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

it('keeps a session sitting on the boundary', function () {
    // One second short, so a clock tick lands on the boundary itself rather than flipping the test
    expect(expireIdle(sessionWithLifetime(604800), idleSession(604800 - 1)))->toBeFalse();
});

it('expires a session one second past the boundary', function () {
    expect(expireIdle(sessionWithLifetime(604800), idleSession(604800 + 1)))->toBeTrue();
});

it('never expires a session that carries no last-used stamp', function () {
    // Also every session written before read-time expiry existed
    expect(expireIdle(sessionWithLifetime(60), idleSession(null), 0))->toBeFalse();
});

it('does not expire when the clock has stepped backwards', function () {
    expect(expireIdle(sessionWithLifetime(604800), idleSession(60), time() + 3600))->toBeFalse();
});

it('keeps a session inside the Remember Me lifetime the record is graded on', function () {
    expect(expireIdle(sessionWithLifetime(2592000), idleSession(10 * 86400)))->toBeFalse();
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

it('treats a record belonging to another session namespace as foreign', function () {
    // Regression: the record is keyed on the id alone, so ?SID= let a storefront id be graded and
    // re-stamped on the admin policy, and vice versa
    $_SESSION[Mage_Core_Model_Session_Abstract::NAMESPACE_KEY] = Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE;

    expect(isForeign(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))->toBeTrue();
});

it('adopts a record whose namespace matches', function () {
    $_SESSION[Mage_Core_Model_Session_Abstract::NAMESPACE_KEY] = Mage_Core_Controller_Front_Action::SESSION_NAMESPACE;

    expect(isForeign(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))->toBeFalse();
});

it('adopts a record written before namespaces were recorded', function () {
    expect(isForeign(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))->toBeFalse();
});

it('uses the last-used value captured before validate() re-stamped it', function () {
    // A check reading getLastUsed() at this point would never expire anything
    $session = idleSession(604800 + 60);
    $captured = $session->getMetadataBag()->getLastUsed();
    $session->getMetadataBag()->stampNew(604800);

    expect(expireIdle(sessionWithLifetime(604800), $session, $captured))->toBeTrue();
});
