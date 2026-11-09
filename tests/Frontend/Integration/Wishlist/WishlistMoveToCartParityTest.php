<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Wishlist\Api\WishlistProcessor;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The wishlist page and the API move a wishlist item to the cart through Mage_Checkout_Service_Cart::addProduct(),
 * with the buy request of the item (#1348). The API took only the SKU, so the cart line lost the options of the item.
 */

function wishlistMoveSecurity(int $customerId): Security
{
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage = new TokenStorage();
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private TokenStorage $storage) {}

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
    });
}

/**
 * Return the value of the custom option $optionId of each line of $quote.
 *
 * @return list<string|null>
 */
function wishlistMoveOptionValues(Mage_Sales_Model_Quote $quote, int $optionId): array
{
    $values = [];
    foreach (Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getAllVisibleItems() as $item) {
        $values[] = $item->getOptionByCode('option_' . $optionId)?->getValue();
    }
    return $values;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '1');

    $this->product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-wishlist-move-' . uniqid())->setName('Pest wishlist move product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->setHasOptions(true)->setCanSaveCustomOptions(true)
        ->setProductOptions([['title' => 'Engraving', 'type' => 'field', 'is_require' => 0, 'price' => 0, 'price_type' => 'fixed', 'sort_order' => 0]]);
    $this->product->save();
    $this->optionId = (int) Mage::getModel('catalog/product_option')->getCollection()
        ->addFieldToFilter('product_id', $this->product->getId())->getFirstItem()->getId();

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail('wishlist-move-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();
    $this->wishlist = Mage::getService('wishlist/wishlist')->getForCustomer((int) $this->customer->getId(), 1);
    $this->quotes = [];
});

afterEach(function () {
    foreach ($this->quotes as $quote) {
        $quote->delete();
    }
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('moves an item to the cart with its options on both transports', function () {
    $buyRequest = new Maho\DataObject(['qty' => 1, 'options' => [$this->optionId => 'For Jane']]);
    $item = Mage::getService('wishlist/wishlist')->addProduct($this->wishlist, (int) $this->product->getId(), $buyRequest, 1);
    $webQuote = Mage::getService('checkout/cart')->create(null, 1);
    $this->quotes[] = $webQuote;

    // The page keeps the item in the wishlist without "delete", so the API can move the same item after it
    $added = Mage::getModel('wishlist/item')->loadWithOptions($item->getId())
        ->addToCart(Mage::getModel('checkout/cart')->setQuote($webQuote));
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/customers/me/wishlist/{$item->getId()}/move-to-cart");
    $api = AdminApiParity::api($request, fn() => new WishlistProcessor(wishlistMoveSecurity((int) $this->customer->getId()))
        ->process(null, new Post(name: 'moveToCart'), [], ['request' => $request, 'args' => ['input' => ['itemId' => $item->getId(), 'qty' => 1]]]));
    $apiQuote = Mage::getService('checkout/cart')->getForCustomer((int) $this->customer->getId(), 1);
    $this->quotes[] = $apiQuote;

    expect($added)->toBeTrue()
        ->and($api['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and(wishlistMoveOptionValues($webQuote, $this->optionId))->toBe(['For Jane'])
        ->and(wishlistMoveOptionValues($apiQuote, $this->optionId))->toBe(['For Jane']);
});
