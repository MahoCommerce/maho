<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoBackendTestCase::class);

function resetCustomerPasswordSessionValidator(): void
{
    (new ReflectionProperty(Mage_Core_Model_Session_Abstract::class, 'isValidated'))
        ->setValue(null, false);
}

/**
 * @return array<string, mixed>
 */
function customerPasswordSessionData(int $customerId, int $lastUsed): array
{
    $request = SymfonyRequest::createFromGlobals();

    return [
        '_sf2_meta' => [
            'c' => $lastUsed - 60,
            'u' => $lastUsed,
            'l' => Mage_Core_Controller_Front_Action::SESSION_DEFAULT_LIFETIME,
        ],
        Mage_Core_Model_Session_Abstract::VALIDATOR_KEY => [
            Mage_Core_Model_Session_Abstract::VALIDATOR_REMOTE_ADDR_KEY => $request->getClientIp() ?: '',
            Mage_Core_Model_Session_Abstract::VALIDATOR_HTTP_VIA_KEY => '',
            Mage_Core_Model_Session_Abstract::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY => '',
            Mage_Core_Model_Session_Abstract::VALIDATOR_HTTP_USER_AGENT_KEY => '',
        ],
        'core' => [
            'visitor_data' => ['visitor_id' => 1, 'session_id' => 'password-session-test'],
        ],
        'customer' => [
            'id' => $customerId,
            'customer_id' => $customerId,
        ],
    ];
}

/**
 * Start a customer request from an already stored session and report whether it survives.
 *
 * @param array<string, mixed> $sessionData
 */
function customerPasswordSessionSurvives(array $sessionData): bool
{
    $_SESSION = $sessionData;
    $storage = new MockArraySessionStorage();
    $storage->setSessionData(['_sf2_meta' => $sessionData['_sf2_meta']]);
    $session = new Session($storage);
    $session->start();
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    resetCustomerPasswordSessionValidator();

    expect($session->getMetadataBag()->getLastUsed())
        ->toBe((int) $sessionData['_sf2_meta']['u']);

    try {
        // The shared validator is evaluated once, before the customer namespace may be loaded.
        new Mage_Core_Model_Session();
        new Mage_Customer_Model_Session();
    } catch (Mage_Core_Model_Session_Exception) {
        return false;
    }

    return true;
}

beforeEach(function () {
    $_SESSION = [];
    Mage::app()->getStore()->setConfig(
        Mage_Customer_Model_Config_Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE,
        (string) Mage_Customer_Model_Config_Share::SHARE_GLOBAL,
    );
    Mage::app()->getStore()->setConfig('system/smtp/enabled', '');
});

afterEach(function () {
    $_SESSION = [];
});

it('revokes an older customer session after a password reset', function () {
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setEmail('password-session-' . bin2hex(random_bytes(4)) . '@example.com')
        ->setFirstname('Password')
        ->setLastname('Session')
        ->setPassword('OldPassword1')
        ->save();

    try {
        $now = time();
        $oldLastUsed = $now - 10;
        $changedLastUsed = $now;
        $customer->setPasswordCreatedAt($now - 5)->save();

        $oldSession = customerPasswordSessionData((int) $customer->getId(), $oldLastUsed);
        $changedSession = customerPasswordSessionData((int) $customer->getId(), $changedLastUsed);

        expect(customerPasswordSessionSurvives($changedSession))->toBeTrue()
            ->and(customerPasswordSessionSurvives($oldSession))->toBeFalse();
    } finally {
        Mage::register('isSecureArea', true, true);
        $customer->delete();
        Mage::unregister('isSecureArea');
    }
});
