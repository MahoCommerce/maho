<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartService;
use Mage\Wishlist\Api\WishlistItem;
use Mage\Wishlist\Api\WishlistProcessor;
use Mage\Wishlist\Api\WishlistProvider;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

uses(Tests\MahoBackendTestCase::class);

/**
 * An observer on api_wishlist_item_dto_build must see every wishlist DTO the API
 * returns, whether the list came from the database or from the cache.
 */
final class WishlistDtoBuildProbe
{
    public static int $calls = 0;

    public function onBuild(Maho\Event\Observer $observer): void
    {
        self::$calls++;
        $dto = $observer->getEvent()->getDto();
        $dto->extensions['probe'] = true;
        // Mutates a cached base field, so a cache that kept it would apply twice
        $dto->productName .= ' [probe]';
    }
}

function wishlistDtoSecurity(int $customerId): Security
{
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage = new TokenStorage();
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    $container = new class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private readonly TokenStorage $storage) {}

        #[\Override]
        public function get(string $id): mixed
        {
            return $id === 'security.authorization_checker'
                ? new AuthorizationChecker($this->storage, new AccessDecisionManager([new RoleVoter()]))
                : $this->storage;
        }

        #[\Override]
        public function has(string $id): bool
        {
            return in_array($id, ['security.token_storage', 'security.authorization_checker'], true);
        }
    };

    return new Security($container);
}

function wishlistDtoCall(object $target, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod($target, $method))->invoke($target, ...$args);
}

beforeEach(function (): void {
    StoreContext::ensureStore();

    $this->product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->addAttributeToSelect('name')
        ->addWebsiteFilter(1)
        ->setOrder('entity_id', 'ASC')
        ->setPageSize(1)
        ->getFirstItem();
    if (!$this->product->getId()) {
        $this->markTestSkipped('No visible simple product available for testing');
    }

    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setFirstname('Pest')
        ->setLastname('Wishlist Event')
        ->setEmail('pest-wishlist-event-' . uniqid() . '@example.com');
    $customer->save();
    $this->customer = $customer;
    $this->security = wishlistDtoSecurity((int) $customer->getId());

    $app = Mage::app();
    $this->eventsProperty = new ReflectionProperty($app, '_events');
    $this->originalEvents = $this->eventsProperty->getValue($app);
    $events = $this->originalEvents;
    $events['global']['api_wishlist_item_dto_build'] = ['observers' => ['probe' => [
        'type' => 'model',
        'model' => WishlistDtoBuildProbe::class,
        'method' => 'onBuild',
        'args' => [],
    ]]];
    $this->eventsProperty->setValue($app, $events);
    WishlistDtoBuildProbe::$calls = 0;
});

afterEach(function (): void {
    $this->eventsProperty->setValue(Mage::app(), $this->originalEvents);
    WishlistProvider::invalidateCache((int) $this->customer->getId());
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
});

it('dispatches the event for the item returned by an add', function (): void {
    $processor = new WishlistProcessor($this->security, new CartService());
    $dto = wishlistDtoCall($processor, 'addToWishlist', (int) $this->product->getId());

    expect(WishlistDtoBuildProbe::$calls)->toBe(1)
        ->and($dto->extensions)->toBe(['probe' => true]);
});

it('dispatches the event once per item on a cache miss and on a cache hit', function (): void {
    $customerId = (int) $this->customer->getId();
    wishlistDtoCall(new WishlistProcessor($this->security, new CartService()), 'addToWishlist', (int) $this->product->getId());
    WishlistProvider::invalidateCache($customerId);

    $provider = new WishlistProvider($this->security);
    $name = $this->product->getName();

    foreach (['miss', 'hit'] as $path) {
        WishlistDtoBuildProbe::$calls = 0;
        /** @var list<WishlistItem> $items */
        $items = wishlistDtoCall($provider, 'getWishlistItems');

        expect($items)->toHaveCount(1, $path)
            ->and(WishlistDtoBuildProbe::$calls)->toBe(1, $path)
            ->and($items[0]->extensions)->toBe(['probe' => true], $path)
            ->and($items[0]->productName)->toBe($name . ' [probe]', $path);
    }
});

it('caches the item as built from the database, before observers run', function (): void {
    $customerId = (int) $this->customer->getId();
    wishlistDtoCall(new WishlistProcessor($this->security, new CartService()), 'addToWishlist', (int) $this->product->getId());
    WishlistProvider::invalidateCache($customerId);

    wishlistDtoCall(new WishlistProvider($this->security), 'getWishlistItems');

    $storeId = Mage::app()->getStore()->getId();
    $groupId = Mage::helper('customer')->getCurrentGroupId();
    $cached = Mage::app()->getCache()->load("api_wishlist_{$customerId}_{$storeId}_{$groupId}");
    if ($cached === false) {
        $this->markTestSkipped('Cache backend is disabled in this environment');
    }

    $row = Mage::helper('core')->jsonDecode($cached)[0];
    expect($row['productName'])->toBe($this->product->getName())
        ->and($row)->not->toHaveKey('extensions');
});

it('dispatches the event for a single item read', function (): void {
    $added = wishlistDtoCall(new WishlistProcessor($this->security, new CartService()), 'addToWishlist', (int) $this->product->getId());
    WishlistDtoBuildProbe::$calls = 0;

    $dto = wishlistDtoCall(new WishlistProvider($this->security), 'getItem', $added->id);

    expect(WishlistDtoBuildProbe::$calls)->toBe(1)
        ->and($dto->extensions)->toBe(['probe' => true]);
});

it('does not dispatch the event for a single item read of another customer', function (): void {
    $added = wishlistDtoCall(new WishlistProcessor($this->security, new CartService()), 'addToWishlist', (int) $this->product->getId());
    WishlistDtoBuildProbe::$calls = 0;

    $otherSecurity = wishlistDtoSecurity((int) $this->customer->getId() + 1);
    $dto = wishlistDtoCall(new WishlistProvider($otherSecurity), 'getItem', $added->id);

    expect(WishlistDtoBuildProbe::$calls)->toBe(0)
        ->and($dto->extensions)->toBe([]);
});
