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
 * @return array<string, string[]>
 */
function disposableKeysOfCoreModels(): array
{
    $models = [
        'core' => Mage_Core_Model_Session::class,
        'catalog' => Mage_Catalog_Model_Session::class,
        'customer_base' => Mage_Customer_Model_Session::class,
        'checkout' => Mage_Checkout_Model_Session::class,
        'reports' => Mage_Reports_Model_Session::class,
    ];

    $keys = [];
    foreach ($models as $namespace => $class) {
        $model = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $keys[$namespace] = (new ReflectionMethod($class, 'getDisposableKeys'))->invoke($model);
    }
    return $keys;
}

/**
 * The session that an anonymous GET of a category or product page leaves behind
 */
function anonymousPageViewSession(): array
{
    return [
        Mage_Core_Model_Session_Abstract::VALIDATOR_KEY => ['remote_addr' => '127.0.0.1', 'http_user_agent' => 'Googlebot'],
        Mage_Core_Model_Session_Abstract::SECURE_COOKIE_CHECK_KEY => md5('x'),
        'core' => [
            'messages' => new Mage_Core_Model_Message_Collection(),
            '_form_key' => 'abc',
            'visitor_data' => ['visitor_id' => 12, 'session_id' => 'abc'],
            'last_url' => 'https://example.com/shoes.html',
        ],
        'customer_base' => ['id' => null, 'wishlist_item_count' => 0, 'messages' => new Mage_Core_Model_Message_Collection()],
        'catalog' => [
            'messages' => new Mage_Core_Model_Message_Collection(),
            'last_visited_category_id' => 4,
            'last_viewed_category_id' => 4,
            'last_viewed_product_id' => 17,
            'sort_order' => 'price',
            'limit_page' => 36,
        ],
        'checkout' => ['messages' => new Mage_Core_Model_Message_Collection(), 'cart_was_updated' => true],
        'reports' => ['product_index_viewed_count' => 0, 'product_index_compared_count' => 0],
        'store_default' => [],
    ];
}

describe('holdsState()', function (): void {
    it('finds no state in the session of an anonymous page view', function (): void {
        expect(Mage_Core_Model_Session_Abstract::holdsState(anonymousPageViewSession(), disposableKeysOfCoreModels()))
            ->toBeFalse();
    });

    it('finds no state in an empty session', function (): void {
        expect(Mage_Core_Model_Session_Abstract::holdsState([]))->toBeFalse();
    });

    it('finds state in a message', function (): void {
        $session = anonymousPageViewSession();
        $session['core']['messages']->add(new Mage_Core_Model_Message_Error('Invalid form key'));

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });

    it('finds state in a quote id', function (): void {
        $session = anonymousPageViewSession();
        $session['checkout']['quote_id_1'] = 42;

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });

    it('finds state in the url to go back to after login', function (): void {
        $session = anonymousPageViewSession();
        $session['customer_base']['before_auth_url'] = 'https://example.com/customer/account/';

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });

    it('finds state in a customer id', function (): void {
        $session = anonymousPageViewSession();
        $session['customer_base']['id'] = 5;

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });

    it('finds state in a key that no model declares disposable', function (): void {
        $session = anonymousPageViewSession();
        $session['catalog']['sort_order_of_extension'] = 'name';

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });

    it('finds state in a false value', function (): void {
        $session = anonymousPageViewSession();
        $session['customer_base']['remember_me'] = false;

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });

    it('finds state in a top-level value that it does not know', function (): void {
        $session = anonymousPageViewSession();
        $session['extension_token'] = 'abc';

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });

    it('keeps a disposable key of one namespace as state in another', function (): void {
        $session = anonymousPageViewSession();
        $session['checkout']['last_url'] = 'https://example.com/';

        expect(Mage_Core_Model_Session_Abstract::holdsState($session, disposableKeysOfCoreModels()))->toBeTrue();
    });
});

describe('getFormKey()', function (): void {
    beforeEach(function (): void {
        $this->previousSession = Mage::registry(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    });

    afterEach(function (): void {
        Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        if ($this->previousSession !== null) {
            Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $this->previousSession);
        }
    });

    $formKeyFor = function (?string $sessionId): string {
        Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        if ($sessionId !== null) {
            $storage = new MockArraySessionStorage(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE);
            $storage->setId($sessionId);
            Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, new Session($storage));
        }

        $session = new Mage_Core_Model_Session();
        $session->unsetData('_form_key');
        return $session->getFormKey();
    };

    it('derives the same key from the same session id', function () use ($formKeyFor): void {
        expect($formKeyFor('5f4dcc3b5aa765d61d8327deb882cf99'))
            ->toBe($formKeyFor('5f4dcc3b5aa765d61d8327deb882cf99'))
            ->toHaveLength(32);
    });

    it('derives another key from another session id', function () use ($formKeyFor): void {
        expect($formKeyFor('5f4dcc3b5aa765d61d8327deb882cf99'))
            ->not->toBe($formKeyFor('e99a18c428cb38d5f260853678922e03'));
    });

    it('gives a random key when there is no session id', function () use ($formKeyFor): void {
        expect($formKeyFor(null))->toHaveLength(16)->not->toBe($formKeyFor(null));
    });

    it('returns a stored key unchanged', function (): void {
        $storage = new MockArraySessionStorage(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE);
        $storage->setId('5f4dcc3b5aa765d61d8327deb882cf99');
        Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, new Session($storage));

        $session = new Mage_Core_Model_Session();
        $session->setData('_form_key', 'storedRandomKey1');

        expect($session->getFormKey())->toBe('storedRandomKey1');
    });

    it('stores a new random key when it renews the key', function (): void {
        $storage = new MockArraySessionStorage(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE);
        $storage->setId('5f4dcc3b5aa765d61d8327deb882cf99');
        Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, new Session($storage));

        $session = new Mage_Core_Model_Session();
        $session->unsetData('_form_key');
        $derived = $session->getFormKey();
        $session->renewFormKey();

        expect($session->getFormKey())->not->toBe($derived)->toHaveLength(16);
    });
});
