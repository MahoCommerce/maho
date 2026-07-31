<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function coreSession(): Mage_Core_Model_Session_Abstract
{
    return Mage::getSingleton('core/session');
}

function invokeOnSession(string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(Mage_Core_Model_Session_Abstract::class, $method))
        ->invoke(coreSession(), ...$args);
}

function setSessionProperty(string $name, ?int $value): void
{
    (new ReflectionProperty(Mage_Core_Model_Session_Abstract::class, $name))
        ->setValue(coreSession(), $value);
}

function renewCookieObserver(string $sessionName): \Maho\Event\Observer
{
    return new \Maho\Event\Observer([
        'cookie' => Mage::getSingleton('core/cookie'),
        'session' => coreSession(),
        'session_name' => $sessionName,
    ]);
}

beforeEach(function () {
    $store = Mage::app()->getStore();
    // Keep customer accounts global so the customer session namespace does not resolve a
    // website, which the admin store context cannot supply
    $store->setConfig(
        Mage_Customer_Model_Config_Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE,
        (string) Mage_Customer_Model_Config_Share::SHARE_GLOBAL,
    );
    $store->setConfig('web/cookie/cookie_lifetime', '604800');
    $store->setConfig('web/cookie/remember_cookie_lifetime', '2592000');
    $store->setConfig('web/cookie/remember_enabled', '1');
    $store->setConfig('admin/security/session_cookie_lifetime', '10800');
});

describe('configured fallback', function () {
    it('uses the storefront cookie lifetime for the storefront namespace', function () {
        expect(invokeOnSession('resolveConfiguredSessionLifetime', Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))
            ->toBe(604800);
    });

    it('clamps the storefront fallback to the storefront minimum, not a lower one', function () {
        // A cookie_lifetime of 0 is a valid merchant setting; the session must not fall below
        // the same floor the cookie observes.
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '0');

        expect(invokeOnSession('resolveConfiguredSessionLifetime', Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Core_Controller_Front_Action::SESSION_MIN_LIFETIME);
    });

    it('clamps the storefront fallback down to SESSION_MAX_LIFETIME', function () {
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '99999999999');

        expect(invokeOnSession('resolveConfiguredSessionLifetime', Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Core_Controller_Front_Action::SESSION_MAX_LIFETIME);
    });

    it('uses the admin cookie lifetime for the admin namespace', function () {
        expect(invokeOnSession('resolveConfiguredSessionLifetime', Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(10800);
    });

    it('does not let a longer storefront lifetime govern the admin namespace', function () {
        // Regression: the admin session used to be created with max($admin, $frontend, 86400),
        // so it carried a 7 day gc_maxlifetime for a policy that says 3 hours
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '2592000');

        expect(invokeOnSession('resolveConfiguredSessionLifetime', Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(10800);
    });

    it('clamps the admin fallback to the admin minimum, not the one day floor', function () {
        Mage::app()->getStore()->setConfig('admin/security/session_cookie_lifetime', '0');

        expect(invokeOnSession('resolveConfiguredSessionLifetime', Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Adminhtml_Controller_Action::SESSION_MIN_LIFETIME);
    });

    it('clamps the admin fallback down to SESSION_MAX_LIFETIME', function () {
        Mage::app()->getStore()->setConfig('admin/security/session_cookie_lifetime', '99999999999');

        expect(invokeOnSession('resolveConfiguredSessionLifetime', Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Adminhtml_Controller_Action::SESSION_MAX_LIFETIME);
    });

    it('never shortens a namespace that has no observer of its own', function () {
        // Regression: an extension declaring its own _sessionNamespace must not silently
        // inherit the admin lifetime.
        expect(invokeOnSession('resolveConfiguredSessionLifetime', 'third_party_session'))
            ->toBe(604800);
    });

    it('keeps a floor of one day for a namespace with no observer', function () {
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '3600');
        Mage::app()->getStore()->setConfig('admin/security/session_cookie_lifetime', '3600');

        expect(invokeOnSession('resolveConfiguredSessionLifetime', 'third_party_session'))
            ->toBe(86400);
    });
});

describe('resolved lifetime', function () {
    it('prefers a lifetime supplied by an observer over configuration', function () {
        setSessionProperty('sessionLifetimeFallback', 604800);
        coreSession()->setSessionLifetime(2592000);

        expect(coreSession()->getSessionLifetime())->toBe(2592000);
    });

    it('does not persist the supplied lifetime into session data', function () {
        coreSession()->setSessionLifetime(1234);

        // The DataObject magic setter would have written this into $_SESSION, which is bound
        // by reference in init() and would leak the value into later requests.
        expect(coreSession()->getData('session_lifetime'))->toBeNull();
    });

    it('never resolves a non-positive lifetime', function () {
        // Redis setEx() fails on a ttl of 0 or less, which would drop the session at shutdown
        coreSession()->setSessionLifetime(0);

        expect(coreSession()->getSessionLifetime())->toBeGreaterThan(0);
    });
});

describe('observer precedence', function () {
    it('has the storefront observer supply the Remember Me lifetime', function () {
        $customerSession = Mage::getSingleton('customer/session');
        $original = $customerSession->getData('remember_me');

        try {
            $customerSession->setRememberMe(true);

            Mage::getModel('customer/observer')
                ->setCookieLifetime(renewCookieObserver(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE));

            expect(coreSession()->getSessionLifetime())->toBe(2592000);
        } finally {
            $customerSession->setData('remember_me', $original);
        }
    });

    it('has the storefront observer supply the plain lifetime without Remember Me', function () {
        Mage::getSingleton('customer/session')->setRememberMe(false);

        Mage::getModel('customer/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE));

        expect(coreSession()->getSessionLifetime())->toBe(604800);
    });

    it('has the admin observer supply the admin lifetime', function () {
        Mage::getModel('adminhtml/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE));

        expect(coreSession()->getSessionLifetime())->toBe(10800);
    });

    it('does not let the storefront observer act on a namespace it does not own', function () {
        setSessionProperty('sessionLifetimeFallback', 10800);

        Mage::getModel('customer/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE));

        expect(coreSession()->getSessionLifetime())->toBe(10800);
    });

    it('does not raise the admin lifetime to a longer storefront one', function () {
        // Regression: max($admin, $frontend, 86400) let a longer storefront lifetime silently
        // extend the admin idle window.
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '2592000');

        Mage::getModel('adminhtml/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE));

        expect(coreSession()->getSessionLifetime())->toBe(10800);
    });
});
