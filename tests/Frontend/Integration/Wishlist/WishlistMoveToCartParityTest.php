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
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
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
    // The customer of the test logs in without the confirmation email
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
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
    Mage::getSingleton('customer/session')->logout();
    Mage::unregister('_singleton/checkout/cart');
    Mage::unregister('wishlist');
    foreach ($this->quotes as $quote) {
        $quote->delete();
    }
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('moves an item to the cart with its options on both transports', function () {
    $customerId = (int) $this->customer->getId();
    $productId = (int) $this->product->getId();
    $service = Mage::getService('wishlist/wishlist');
    $buyRequest = fn(): Maho\DataObject => new Maho\DataObject(['qty' => 1, 'options' => [$this->optionId => 'For Jane']]);
    $lines = fn(Mage_Sales_Model_Quote $quote): array => array_map(
        fn(Mage_Sales_Model_Quote_Item $line): float => (float) $line->getQty(),
        Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getAllVisibleItems(),
    );

    // The page and the API use the cart of the customer, so the line of the API must merge with the line of the page
    $quote = Mage::getService('checkout/cart')->getForCustomer($customerId, 1);
    $this->quotes[] = $quote;
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    Mage::getSingleton('checkout/session')->replaceQuote($quote);
    Mage::unregister('_singleton/checkout/cart');
    Mage::unregister('wishlist');

    $pageItemId = (int) $service->addProduct($this->wishlist, $productId, $buyRequest(), 1)->getId();
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/wishlist/index/cart', 'POST', ['item' => $pageItemId]));
    $request->setRouteName('wishlist')->setControllerName('index')->setActionName('cart')->setDispatched(true);
    Mage::app()->setRequest($request);
    new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http())->cartAction();

    expect(Mage::getSingleton('wishlist/session')->getMessages(true)->getErrors())->toBe([])
        ->and(wishlistMoveOptionValues($quote, $this->optionId))->toBe(['For Jane'])
        ->and($lines($quote))->toBe([1.0])
        ->and(Mage::getModel('wishlist/item')->load($pageItemId)->getId())->toBeNull();

    $wishlist = $service->getForCustomer($customerId, 1);
    $apiItemId = (int) $service->addProduct($wishlist, $productId, $buyRequest(), 1)->getId();
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/customers/me/wishlist/{$apiItemId}/move-to-cart");
    $api = AdminApiParity::api($request, fn() => new WishlistProcessor(wishlistMoveSecurity($customerId))
        ->process(null, new Post(name: 'moveToCart'), [], ['request' => $request, 'args' => ['input' => ['itemId' => $apiItemId, 'qty' => 1]]]));

    expect($api['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and(wishlistMoveOptionValues($quote, $this->optionId))->toBe(['For Jane'])
        ->and($lines($quote))->toBe([2.0])
        ->and(Mage::getModel('wishlist/item')->load($apiItemId)->getId())->toBeNull();
});

it('moves the qty of the item when the API client sends no qty, as the wishlist page does', function (string $transport) {
    $itemId = (int) Mage::getService('wishlist/wishlist')
        ->addProduct($this->wishlist, (int) $this->product->getId(), new Maho\DataObject(['qty' => 3]), 1)
        ->getId();
    $request = SymfonyRequest::create(
        "/api/rest/v2/customers/me/wishlist/{$itemId}/move-to-cart",
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: '{}',
    );
    $processor = new WishlistProcessor(wishlistMoveSecurity((int) $this->customer->getId()));

    $api = AdminApiParity::api($request, fn() => $transport === 'REST'
        ? $processor->process(new Mage\Wishlist\Api\WishlistItem(), new Post(name: 'move_to_cart'), ['id' => $itemId], ['request' => $request])
        : $processor->process(null, new Post(name: 'moveToCart'), [], ['request' => $request, 'args' => ['input' => ['itemId' => $itemId]]]));
    $quote = Mage::getService('checkout/cart')->getForCustomer((int) $this->customer->getId(), 1);
    $this->quotes[] = $quote;

    $lines = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getAllVisibleItems();
    expect($api['errors'])->toBe([])
        ->and($lines)->toHaveCount(1)
        ->and((float) $lines[0]->getQty())->toBe(3.0);
})->with(['REST', 'GraphQL']);
