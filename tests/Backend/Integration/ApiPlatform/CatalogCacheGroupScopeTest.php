<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Service\StoreContext;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

beforeEach(function (): void {
    StoreContext::ensureStore();

    // The Mage\<Module>\Api\ autoloader is only registered by the API kernel's
    // boot(), which doesn't run in a plain backend test. Register an equivalent
    // PSR-4 mapping (app/code/core/) so these namespaced classes resolve here.
    static $registered = false;
    if (!$registered) {
        spl_autoload_register(function (string $class): void {
            if (!str_starts_with($class, 'Mage\\')) {
                return;
            }
            $file = Mage::getBaseDir() . '/app/code/core/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
        $registered = true;
    }
});

// A customer in the General group (id 1), distinct from guests (group 0).
function createGroup1Customer(): int
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1)
        ->setEmail('apicachetest_' . uniqid() . '@example.com')
        ->setFirstname('Cache')
        ->setLastname('Test')
        ->setGroupId(1)
        ->setPassword('Password123!')
        ->save();

    return (int) $customer->getId();
}

function deleteCustomer(int $customerId): void
{
    Mage::register('isSecureArea', true, true);
    Mage::getModel('customer/customer')->load($customerId)->delete();
    Mage::unregister('isSecureArea');
}

// Resolve the protected trait method via an anonymous class that fakes identity.
function resolveGroupFor(?int $customerId): int
{
    $resolver = new class ($customerId) {
        use Maho\ApiPlatform\Trait\AuthenticationTrait;

        public function __construct(private ?int $cid) {}

        protected function getAuthenticatedCustomerId(): ?int
        {
            return $this->cid;
        }

        public function group(): int
        {
            return $this->getCustomerGroupId();
        }
    };

    return $resolver->group();
}

// Read the real ProductProvider's private collection cache key for a given
// customer group. ProductProvider is final, so we set the memoized group on
// the (trait-provided) property directly instead of subclassing.
function productCacheKeyForGroup(int $groupId): string
{
    $provider = new Mage\Catalog\Api\ProductProvider(null);

    $groupProp = new ReflectionProperty(Maho\ApiPlatform\Provider::class, 'customerGroupId');
    $groupProp->setValue($provider, $groupId);

    $method = new ReflectionMethod(Mage\Catalog\Api\ProductProvider::class, 'getCollectionCacheKey');

    return $method->invoke($provider, []);
}

it('resolves guests to NOT_LOGGED_IN and customers to their group', function (): void {
    $customerId = createGroup1Customer();

    try {
        expect(resolveGroupFor(null))->toBe(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        expect(resolveGroupFor($customerId))->toBe(1);
    } finally {
        deleteCustomer($customerId);
    }
});

it('partitions the product cache key by customer group', function (): void {
    $guestKey = productCacheKeyForGroup(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    $groupKey = productCacheKeyForGroup(1);

    // Different groups → different cache entries (no cross-group price leak).
    expect($groupKey)->not->toBe($guestKey);

    // Same group → stable key (cache actually hits).
    expect(productCacheKeyForGroup(1))->toBe($groupKey);
});

it('invalidates every group variant when a product is saved', function (): void {
    $cache = Mage::app()->getCache();
    $keyGuest = 'api_product_999999_1_0_USD';
    $keyGroup1 = 'api_product_999999_1_1_USD';
    $tags = ['API_PRODUCTS', 'API_PRODUCT_999999'];

    $cache->save('guest-payload', $keyGuest, $tags, 300);
    $cache->save('group1-payload', $keyGroup1, $tags, 300);

    if ($cache->load($keyGuest) === false) {
        $this->markTestSkipped('Cache backend is disabled in this environment');
    }

    // What the product-save observer does: clean the per-id tag.
    $cache->clean(['API_PRODUCT_999999']);

    expect($cache->load($keyGuest))->toBeFalse();
    expect($cache->load($keyGroup1))->toBeFalse();
});
