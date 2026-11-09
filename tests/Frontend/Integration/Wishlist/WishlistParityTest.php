<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Mage\Wishlist\Api\WishlistItem;
use Mage\Wishlist\Api\WishlistProcessor;
use Mage\Wishlist\Api\WishlistProvider;
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
 * The wishlist page of the storefront, the API and the wishlist tab of the admin customer page change a wishlist
 * through Mage_Wishlist_Service_Wishlist (#1348). Each test gives the same input to the transports and compares
 * the outcome.
 */

function wishlistParitySecurity(int $customerId): Security
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
 * Post $params to an action of the wishlist page, as the browser does.
 *
 * @param array<string, mixed> $params
 * @return array{accepted: bool, messages: list<string>}
 */
function wishlistViaPage(string $action, array $params): array
{
    Mage::unregister('wishlist');
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("/wishlist/index/{$action}", 'POST', $params));
    $request->setRouteName('wishlist')->setControllerName('index')->setActionName($action)->setDispatched(true);
    new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http())->{$action . 'Action'}();

    $success = [];
    $errors = [];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $message) {
        if ($message->getType() === Mage_Core_Model_Message::SUCCESS) {
            $success[] = $message->getText();
        } else {
            $errors[] = $message->getText();
        }
    }
    return ['accepted' => $success !== [] && $errors === [], 'messages' => $errors !== [] ? $errors : $success];
}

/**
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function wishlistViaApi(int $customerId, ?int $productId = null, ?int $deleteItemId = null): array
{
    $request = AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers/me/wishlist');
    $data = new WishlistItem();
    $data->productId = $productId;
    $data->qty = 1;
    $operation = $deleteItemId === null ? new Post() : new Delete();

    return AdminApiParity::api($request, fn() => new WishlistProcessor(wishlistParitySecurity($customerId))
        ->process($data, $operation, ['id' => $deleteItemId], ['request' => $request]));
}

/**
 * @return list<Mage_Wishlist_Model_Item>
 */
function wishlistParityItems(int $customerId): array
{
    $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId);
    return array_values(Mage::getModel('wishlist/item')->getCollection()->setWishlist($wishlist)->getItems());
}

function wishlistParityCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setEmail('wishlist-parity-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $customer->save();
    return $customer;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    // A message that an earlier test left in the session is not an outcome of this test
    Mage::getSingleton('core/session')->getMessages(true);
    Mage::getSingleton('customer/session')->getMessages(true);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    // The customer of the test logs in without the confirmation email
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '1');

    $this->product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->addWebsiteFilter((int) Mage::app()->getStore()->getWebsiteId())
        ->setPageSize(1)
        ->getFirstItem();
    if (!$this->product->getId()) {
        $this->markTestSkipped('No visible simple product available for testing');
    }

    $this->customer = wishlistParityCustomer();
    $this->other = null;
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    Mage::unregister('wishlist');
    Mage::register('isSecureArea', true, true);
    $this->customer?->delete();
    $this->other?->delete();
    Mage::unregister('isSecureArea');
});

it('adds the same product to one item from the page and from the API', function () {
    $customerId = (int) $this->customer->getId();
    $productId = (int) $this->product->getId();

    expect(wishlistViaPage('add', ['product' => $productId, 'qty' => 1])['accepted'])->toBeTrue()
        ->and(wishlistViaApi($customerId, $productId)['status'])->toBe(200);

    $items = wishlistParityItems($customerId);
    expect($items)->toHaveCount(1)
        ->and((float) $items[0]->getQty())->toBe(2.0)
        ->and((int) $items[0]->getStoreId())->toBe((int) Mage::app()->getStore()->getId());
});

it('refuses a product that the store does not show on both transports', function () {
    $hidden = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
        ->setPageSize(1)
        ->getFirstItem();
    if (!$hidden->getId()) {
        $this->markTestSkipped('No product that is not visible individually');
    }
    $customerId = (int) $this->customer->getId();

    $api = wishlistViaApi($customerId, (int) $hidden->getId());
    expect(wishlistViaPage('add', ['product' => (int) $hidden->getId()]))
        ->toBe(['accepted' => false, 'messages' => ['An error occurred while adding item to wishlist: Product not found']])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe(['Product not found'])
        ->and(wishlistParityItems($customerId))->toBe([]);
});

it('answers 404 on the API when the store has no wishlist', function () {
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '0');

    $api = wishlistViaApi((int) $this->customer->getId(), (int) $this->product->getId());

    expect($api['status'])->toBe(404)
        ->and($api['errors'])->toBe(['The wishlist is not available.'])
        ->and(Mage::helper('wishlist')->isAllow())->toBeFalse();
});

it('removes an item only from the wishlist that holds it, on every transport', function () {
    $service = Mage::getService('wishlist/wishlist');
    $storeId = (int) Mage::app()->getStore()->getId();
    $this->other = wishlistParityCustomer();
    $foreign = $service->addProduct(
        $service->getForCustomer((int) $this->other->getId(), $storeId),
        (int) $this->product->getId(),
        new \Maho\DataObject(),
        $storeId,
    );

    $api = wishlistViaApi((int) $this->customer->getId(), deleteItemId: (int) $foreign->getId());
    wishlistViaPage('remove', ['item' => (int) $foreign->getId()]);
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/admin/customer/wishlist', 'POST', [
        'id' => (int) $this->customer->getId(),
        'delete' => (int) $foreign->getId(),
    ]));
    try {
        new Mage_Adminhtml_CustomerController($request, new Mage_Core_Controller_Response_Http())->wishlistAction();
    } catch (Throwable) {
        // The layout of the grid needs a dispatched admin request. The delete runs before it
    } finally {
        Mage::unregister('current_customer');
    }

    expect($api['status'])->toBe(404)
        ->and($api['errors'])->toBe(['Wishlist item not found'])
        ->and(wishlistParityItems((int) $this->other->getId()))->toHaveCount(1);
});

it('shows the API list without the item that the page removed', function () {
    $customerId = (int) $this->customer->getId();
    $added = wishlistViaApi($customerId, (int) $this->product->getId());
    $list = fn(): array => iterator_to_array(new WishlistProvider(wishlistParitySecurity($customerId))
        ->provide(new GetCollection(name: 'my'), [], []));
    expect($list())->toHaveCount(1);

    wishlistViaPage('remove', ['item' => $added['result']->id]);

    expect($list())->toBe([]);
});
