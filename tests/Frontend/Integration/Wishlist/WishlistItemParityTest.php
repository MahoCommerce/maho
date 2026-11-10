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
 * The wishlist page, the shared wishlist, the admin customer page and the API change a wishlist item and move it to
 * the cart through Mage_Wishlist_Service_Wishlist (#1348).
 */

function wishlistItemSecurity(int $customerId): Security
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
 * Post $params to an action of the wishlist page or of the shared wishlist. Return the messages of the sessions.
 *
 * @param array<string, mixed> $params
 * @return list<string>
 */
function wishlistItemViaPage(string $action, array $params, string $controller = 'index'): array
{
    Mage::unregister('wishlist');
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("/wishlist/{$controller}/{$action}", 'POST', $params));
    $request->setRouteName('wishlist')->setControllerName($controller)->setActionName($action)->setDispatched(true);
    Mage::app()->setRequest($request);
    $class = $controller === 'index' ? Mage_Wishlist_IndexController::class : Mage_Wishlist_SharedController::class;
    new $class($request, new Mage_Core_Controller_Response_Http())->{$action . 'Action'}();

    $messages = [];
    foreach (['customer/session', 'wishlist/session', 'catalog/session', 'checkout/session'] as $session) {
        foreach (Mage::getSingleton($session)->getMessages(true)->getItems() as $message) {
            $messages[] = $message->getText();
        }
    }
    return $messages;
}

/**
 * Post $fields to the configure dialog of a wishlist item on the admin customer page, and return the JSON answer.
 *
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function wishlistItemViaAdmin(int $customerId, int $itemId, array $fields): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create(
        "http://localhost/admin/customer_wishlist_product_composite_wishlist/update?customer_id={$customerId}&id={$itemId}&website_id=1",
        'POST',
        $fields,
    ));
    $request->setRouteName('adminhtml')->setControllerName('customer_wishlist_product_composite_wishlist')->setActionName('update')->setDispatched(true);
    Mage::app()->setRequest($request);
    $controller = new Mage_Adminhtml_Customer_Wishlist_Product_Composite_WishlistController($request, new Mage_Core_Controller_Response_Http());
    $controller->updateAction();

    return Mage::helper('core')->jsonDecode($controller->getResponse()->getBody());
}

/**
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function wishlistItemMoveViaApi(int $customerId, int $itemId): array
{
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/customers/me/wishlist/{$itemId}/move-to-cart");
    return AdminApiParity::api($request, fn() => new WishlistProcessor(wishlistItemSecurity($customerId))
        ->process(null, new Post(name: 'moveToCart'), [], ['request' => $request, 'args' => ['input' => ['itemId' => $itemId, 'qty' => 1]]]));
}

/**
 * @return array<int, array{qty: float, engraving: string|null}> the items of the wishlist, by item ID
 */
function wishlistItemRows(Mage_Wishlist_Model_Wishlist $wishlist, int $optionId): array
{
    $rows = [];
    foreach (Mage::getModel('wishlist/item')->getCollection()->setWishlist($wishlist) as $item) {
        $item = Mage::getModel('wishlist/item')->loadWithOptions($item->getId());
        $rows[(int) $item->getId()] = ['qty' => (float) $item->getQty(), 'engraving' => $item->getBuyRequest()->getOptions()[$optionId] ?? null];
    }
    return $rows;
}

/**
 * @return list<string|null> the engraving of each line of the cart of $customerId
 */
function wishlistItemCartLines(int $customerId, int $optionId): array
{
    $lines = [];
    $quote = Mage::getModel('sales/quote')->setSharedStoreIds([1])->loadByCustomer($customerId);
    foreach ($quote->getAllVisibleItems() as $item) {
        $lines[] = $item->getOptionByCode('option_' . $optionId)?->getValue();
    }
    return $lines;
}

/**
 * Create a simple product of the main website with an optional text option, and in stock when $inStock is true.
 */
function wishlistItemProduct(bool $inStock = true): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-wishlist-item-' . uniqid())->setName('Pest wishlist item product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 1, 'is_in_stock' => (int) $inStock, 'qty' => $inStock ? 100 : 0])
        ->setHasOptions(true)->setCanSaveCustomOptions(true)
        ->setProductOptions([['title' => 'Engraving', 'type' => 'field', 'is_require' => 0, 'price' => 0, 'price_type' => 'fixed', 'sort_order' => 0]]);
    $product->save();
    return $product;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '1');
    Mage::app()->getStore()->setConfig(Mage_Checkout_Helper_Cart::XML_PATH_REDIRECT_TO_CART, '0');
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');

    $this->products = [];
    $this->useProduct = function (bool $inStock = true): void {
        $this->products[] = $this->product = wishlistItemProduct($inStock);
        $this->optionId = (int) Mage::getModel('catalog/product_option')->getCollection()
            ->addFieldToFilter('product_id', $this->product->getId())->getFirstItem()->getId();
    };
    ($this->useProduct)();

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail('wishlist-item-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    $this->customerId = (int) $this->customer->getId();

    $this->service = Mage::getService('wishlist/wishlist');
    $this->wishlist = $this->service->getForCustomer($this->customerId, 1);
    $this->wishlist->setShared(true)->save();
    // The page and the API put the product in the same cart of the customer
    Mage::getSingleton('checkout/session')->replaceQuote(Mage::getService('checkout/cart')->getForCustomer($this->customerId, 1));
    Mage::getSingleton('checkout/cart')->unsetData('quote');
    $this->addItem = fn(string $engraving): int => (int) $this->service->addProduct(
        $this->wishlist,
        (int) $this->product->getId(),
        new Maho\DataObject(['qty' => 1, 'options' => [$this->optionId => $engraving]]),
        1,
    )->getId();
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    Mage::unregister('wishlist');
    foreach (Mage::getModel('sales/quote')->getCollection()->addFieldToFilter('customer_id', $this->customerId) as $quote) {
        $quote->delete();
    }
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    foreach ($this->products as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
});

it('gives an item new options and a new qty on the page and in the admin', function () {
    $itemId = ($this->addItem)('For Jane');

    expect(wishlistItemViaPage('updateItemOptions', ['product' => $this->product->getId(), 'id' => $itemId, 'qty' => 2, 'options' => [$this->optionId => 'For John']]))
        ->toBe(['Pest wishlist item product has been updated in your wishlist.']);
    $itemId = array_key_first(wishlistItemRows($this->wishlist, $this->optionId));
    expect(wishlistItemRows($this->wishlist, $this->optionId))->toBe([$itemId => ['qty' => 2.0, 'engraving' => 'For John']])
        ->and(wishlistItemViaAdmin($this->customerId, $itemId, ['qty' => 3, 'options' => [$this->optionId => 'For Ann']]))->toBe(['ok' => true]);
    $itemId = array_key_first(wishlistItemRows($this->wishlist, $this->optionId));
    expect(wishlistItemRows($this->wishlist, $this->optionId))->toBe([$itemId => ['qty' => 3.0, 'engraving' => 'For Ann']]);
});

it('removes an item with a qty of 0 on the page, in the options form and in the admin', function () {
    $first = ($this->addItem)('One');
    $second = ($this->addItem)('Two');
    $third = ($this->addItem)('Three');

    wishlistItemViaPage('update', ['description' => [$first => 'A gift', $second => ''], 'qty' => [$first => '2', $second => '0']]);
    wishlistItemViaPage('updateItemOptions', ['product' => $this->product->getId(), 'id' => $first, 'qty' => 0, 'options' => [$this->optionId => 'One']]);
    expect(wishlistItemViaAdmin($this->customerId, $third, ['qty' => 0, 'options' => [$this->optionId => 'Three']]))->toBe(['ok' => true])
        ->and(wishlistItemRows($this->wishlist, $this->optionId))->toBe([]);
});

it('keeps the comment of an empty field and saves a new comment and qty on the page', function () {
    $itemId = ($this->addItem)('One');
    $this->service->updateItems($this->wishlist, [$itemId => ['description' => 'Old comment']]);

    wishlistItemViaPage('update', ['description' => [$itemId => ''], 'qty' => [$itemId => '4']]);
    $item = Mage::getModel('wishlist/item')->load($itemId);
    expect([(string) $item->getDescription(), (float) $item->getQty()])->toBe(['Old comment', 4.0]);

    wishlistItemViaPage('update', ['description' => [$itemId => 'New comment']]);
    $item = Mage::getModel('wishlist/item')->load($itemId);
    expect([(string) $item->getDescription(), (float) $item->getQty()])->toBe(['New comment', 4.0]);
});

it('moves an item to the cart with its options on the page, the shared wishlist and the API', function () {
    $page = ($this->addItem)('Page');
    $shared = ($this->addItem)('Shared');
    $api = ($this->addItem)('Api');

    expect(wishlistItemViaPage('cart', ['item' => $page]))->toBe(['Pest wishlist item product was added to your shopping cart.'])
        ->and(wishlistItemViaPage('cart', ['item' => $shared, 'code' => $this->wishlist->getSharingCode()], 'shared'))->toBe([])
        ->and(wishlistItemMoveViaApi($this->customerId, $api)['status'])->toBe(200)
        ->and(wishlistItemCartLines($this->customerId, $this->optionId))->toBe(['Page', 'Shared', 'Api'])
        ->and(array_keys(wishlistItemRows($this->wishlist, $this->optionId)))->toBe([$shared]);
});

it('refuses a product that the store no longer sells on the page, the shared wishlist and the API', function () {
    $itemId = ($this->addItem)('One');
    Mage::getSingleton('catalog/product_action')->updateAttributes(
        [$this->product->getId()],
        ['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED],
        0,
    );
    $message = "Product '{$this->product->getSku()}' is not available";

    expect(wishlistItemViaPage('cart', ['item' => $itemId]))->toBe([$message])
        ->and(wishlistItemViaPage('cart', ['item' => $itemId, 'code' => $this->wishlist->getSharingCode()], 'shared'))->toBe([$message])
        ->and(wishlistItemMoveViaApi($this->customerId, $itemId)['errors'])->toBe([$message])
        ->and(wishlistItemCartLines($this->customerId, $this->optionId))->toBe([])
        ->and(array_keys(wishlistItemRows($this->wishlist, $this->optionId)))->toBe([$itemId]);
});

it('refuses a product that is out of stock with the message of the cart on the page and the API', function () {
    ($this->useProduct)(false);
    $itemId = ($this->addItem)('One');

    expect(wishlistItemViaPage('cart', ['item' => $itemId]))->toBe(['This product is currently out of stock.'])
        ->and(wishlistItemMoveViaApi($this->customerId, $itemId)['errors'])->toBe(['This product is currently out of stock.'])
        ->and(array_keys(wishlistItemRows($this->wishlist, $this->optionId)))->toBe([$itemId]);
});

it('moves every item to the cart from the page, and reports each item that the cart refuses', function () {
    ($this->addItem)('One');
    ($this->addItem)('Two');
    $optionId = $this->optionId;
    ($this->useProduct)(false);
    $refused = ($this->addItem)('Three');

    expect(wishlistItemViaPage('allcart', []))->toBe([
        'This product is currently out of stock for "Pest wishlist item product".',
        '2 product(s) have been added to shopping cart: "Pest wishlist item product", "Pest wishlist item product".',
    ])
        ->and(wishlistItemCartLines($this->customerId, $optionId))->toBe(['One', 'Two'])
        ->and(array_keys(wishlistItemRows($this->wishlist, $this->optionId)))->toBe([$refused]);
});
