<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function resolveLifetime(string $sessionName, bool $rememberMe = false): int
{
    return Mage_Core_Model_Session_Abstract::resolveConfiguredSessionLifetime($sessionName, $rememberMe);
}

function storedLifetime(string $sessionName): int
{
    return (new ReflectionMethod(Mage_Core_Model_Session_Abstract::class, 'resolveStoredSessionLifetime'))
        ->invoke(null, $sessionName);
}

function renewCookieObserver(string $sessionName): \Maho\Event\Observer
{
    return new \Maho\Event\Observer([
        'cookie' => Mage::getSingleton('core/cookie'),
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

describe('configured lifetime', function () {
    it('uses the storefront cookie lifetime for the storefront namespace', function () {
        expect(resolveLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))
            ->toBe(604800);
    });

    it('clamps the storefront lifetime to the storefront minimum, not a lower one', function () {
        // A cookie_lifetime of 0 is a valid merchant setting; the session must not fall below
        // the same floor the cookie observes.
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '0');

        expect(resolveLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Core_Controller_Front_Action::SESSION_MIN_LIFETIME);
    });

    it('clamps the storefront lifetime down to SESSION_MAX_LIFETIME', function () {
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '99999999999');

        expect(resolveLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Core_Controller_Front_Action::SESSION_MAX_LIFETIME);
    });

    it('uses the Remember Me lifetime when the flag is set', function () {
        expect(resolveLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE, true))
            ->toBe(2592000);
    });

    it('ignores the Remember Me flag when the feature is disabled', function () {
        Mage::app()->getStore()->setConfig('web/cookie/remember_enabled', '0');

        expect(resolveLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE, true))
            ->toBe(604800);
    });

    it('ignores the Remember Me flag outside the storefront namespace', function () {
        expect(resolveLifetime(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE, true))
            ->toBe(10800);
    });

    it('uses the admin cookie lifetime for the admin namespace', function () {
        expect(resolveLifetime(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(10800);
    });

    it('does not let a longer storefront lifetime govern the admin namespace', function () {
        // Regression: the admin session used to be created with max($admin, $frontend, 86400),
        // so it carried a 7 day gc_maxlifetime for a policy that says 3 hours
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '2592000');

        expect(resolveLifetime(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(10800);
    });

    it('clamps the admin lifetime to the admin minimum, not the one day floor', function () {
        Mage::app()->getStore()->setConfig('admin/security/session_cookie_lifetime', '0');

        expect(resolveLifetime(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Adminhtml_Controller_Action::SESSION_MIN_LIFETIME);
    });

    it('clamps the admin lifetime down to SESSION_MAX_LIFETIME', function () {
        Mage::app()->getStore()->setConfig('admin/security/session_cookie_lifetime', '99999999999');

        expect(resolveLifetime(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))
            ->toBe(Mage_Adminhtml_Controller_Action::SESSION_MAX_LIFETIME);
    });

    it('never shortens a namespace that has no observer of its own', function () {
        // Regression: an extension declaring its own _sessionNamespace must not silently
        // inherit the admin lifetime.
        expect(resolveLifetime('third_party_session'))
            ->toBe(604800);
    });

    it('keeps a floor of one day for a namespace with no observer', function () {
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '3600');
        Mage::app()->getStore()->setConfig('admin/security/session_cookie_lifetime', '3600');

        expect(resolveLifetime('third_party_session'))
            ->toBe(86400);
    });
});

describe('stored record lifetime', function () {
    it('covers the longest storefront lifetime any store view can grant', function () {
        // Reaching it destroys the record, and both the store view and the Remember Me flag
        // follow the request, so a narrower value would let any request destroy a live session
        expect(storedLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))->toBe(2592000);
    });

    it('is never shorter than a cookie any store view can hand out', function () {
        $current = Mage::app()->getStore();
        $others = array_filter(
            Mage::app()->getStores(),
            fn(Mage_Core_Model_Store $store): bool => $store->getId() !== $current->getId(),
        );

        if ($others === []) {
            $this->markTestSkipped('Needs a second store view');
        }

        $current->setConfig('web/cookie/cookie_lifetime', '3600');
        $current->setConfig('web/cookie/remember_enabled', '0');
        foreach ($others as $store) {
            $store->setConfig('web/cookie/remember_enabled', '1');
            $store->setConfig('web/cookie/remember_cookie_lifetime', '31536000');
        }

        expect(storedLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))->toBe(31536000);
    });

    it('ignores the Remember Me flag, which follows the requested website', function () {
        // The customer namespace is per website by default, so a cross website request loses the
        // flag; folding it in would let that request destroy a remembered session
        Mage::getSingleton('customer/session')->setRememberMe(false);

        expect(storedLifetime(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE))->toBe(2592000);
    });

    it('keeps the admin record on the admin policy alone', function () {
        // Regression: max($admin, $frontend, 86400) let a longer storefront lifetime silently
        // extend the admin idle window
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '2592000');

        expect(storedLifetime(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))->toBe(10800);
    });

    it('does not let a store view raise the admin record', function () {
        foreach (Mage::app()->getStores() as $store) {
            $store->setConfig('admin/security/session_cookie_lifetime', '2592000');
        }

        expect(storedLifetime(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE))->toBe(10800);
    });
});

describe('cookie lifetime', function () {
    it('has the storefront observer supply the Remember Me lifetime', function () {
        Mage::getSingleton('customer/session')->setRememberMe(true);

        Mage::getModel('customer/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE));

        expect(Mage::getSingleton('core/cookie')->getLifetime())->toBe(2592000);
    });

    it('has the storefront observer supply the plain lifetime without Remember Me', function () {
        Mage::getSingleton('customer/session')->setRememberMe(false);

        Mage::getModel('customer/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE));

        expect(Mage::getSingleton('core/cookie')->getLifetime())->toBe(604800);
    });

    it('keeps the cookie on the requested store view policy', function () {
        Mage::app()->getStore()->setConfig('web/cookie/cookie_lifetime', '3600');
        Mage::getSingleton('customer/session')->setRememberMe(false);

        Mage::getModel('customer/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE));

        expect(Mage::getSingleton('core/cookie')->getLifetime())->toBe(3600);
    });

    it('has the admin observer supply the admin lifetime', function () {
        Mage::getModel('adminhtml/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE));

        expect(Mage::getSingleton('core/cookie')->getLifetime())->toBe(10800);
    });

    it('does not let the storefront observer act on a namespace it does not own', function () {
        Mage::getSingleton('core/cookie')->setLifetime(42);

        Mage::getModel('customer/observer')
            ->setCookieLifetime(renewCookieObserver(Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE));

        expect(Mage::getSingleton('core/cookie')->getLifetime())->toBe(42);
    });
});
