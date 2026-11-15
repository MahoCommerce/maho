<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Mage\ProductAlert\Api\ProductAlert;
use Mage\ProductAlert\Api\ProductAlertProcessor;
use Mage\ProductAlert\Api\ProductAlertProvider;
use Mage_ProductAlert_Model_Type as Type;
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
 * The product page and the API subscribe a customer to a price alert or a stock alert through
 * Mage_ProductAlert_Service_Alert (#1348). The API had no operation, and the controller did not read the switches
 * "Allow Alert When Product Price Changes" and "Allow Alert When Product Comes Back in Stock".
 */

function productAlertSecurity(int $customerId): Security
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
 * Post the alert form of the product page, as the browser does.
 *
 * @return array{accepted: bool, messages: list<string>}
 */
function productAlertViaPage(string $type, int $productId): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("/productalert/add/{$type}", 'POST', [
        'product_id' => $productId,
        Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => Mage::helper('core')->urlEncode(Mage::getBaseUrl()),
    ]));
    $request->setRouteName('productalert')->setControllerName('add')->setActionName($type)->setDispatched(true);
    new Mage_ProductAlert_AddController($request, new Mage_Core_Controller_Response_Http())->{$type . 'Action'}();

    $success = [];
    $errors = [];
    foreach (Mage::getSingleton('catalog/session')->getMessages(true)->getItems() as $message) {
        if ($message->getType() === Mage_Core_Model_Message::SUCCESS) {
            $success[] = $message->getText();
        } else {
            $errors[] = $message->getText();
        }
    }
    return ['accepted' => $success !== [] && $errors === [], 'messages' => $errors !== [] ? $errors : $success];
}

/**
 * Call POST /api/rest/v2/customers/me/product-alerts, or the subscribe mutation with $graphQl.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function productAlertViaApi(int $customerId, string $type, int $productId, bool $graphQl = false): array
{
    $body = ['type' => $type, 'productId' => $productId];
    $request = AdminApiParity::jsonRequest('POST', '/api/rest/v2/customers/me/product-alerts', $body);
    $data = new ProductAlert();
    if (!$graphQl) {
        $data->type = $type;
        $data->productId = $productId;
    }

    return AdminApiParity::api($request, fn() => new ProductAlertProcessor(productAlertSecurity($customerId))
        ->process($data, new Post(name: $graphQl ? 'subscribe' : 'post'), [], ['request' => $request, 'args' => ['input' => $graphQl ? $body : []]]));
}

/**
 * @return list<int> the product IDs of the alerts of $type of the customer
 */
function productAlertProducts(Type $type, int $customerId): array
{
    return array_map(
        fn(Mage_Core_Model_Abstract $alert): int => (int) $alert->getProductId(),
        Mage::getService('productalert/alert')->getByCustomer($customerId, (int) Mage::app()->getStore()->getId(), $type),
    );
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
    $store->setConfig(Mage_ProductAlert_Model_Observer::XML_PATH_PRICE_ALLOW, '1');
    $store->setConfig(Mage_ProductAlert_Model_Observer::XML_PATH_STOCK_ALLOW, '1');
    Mage::getSingleton('catalog/session')->getMessages(true);

    $this->product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->addWebsiteFilter((int) $store->getWebsiteId())
        ->setPageSize(1)
        ->getFirstItem();
    if (!$this->product->getId()) {
        $this->markTestSkipped('No visible simple product available for testing');
    }

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) $store->getWebsiteId())
        ->setEmail('alert-parity-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_ProductAlert_Model_Observer::XML_PATH_PRICE_ALLOW, '0');
    $store->setConfig(Mage_ProductAlert_Model_Observer::XML_PATH_STOCK_ALLOW, '0');
    // The delete of the customer removes the alerts through the foreign key
    Mage::register('isSecureArea', true, true);
    $this->customer?->delete();
    Mage::unregister('isSecureArea');
});

it('subscribes a customer on each transport', function (string $type, string $pageMessage) {
    $customerId = (int) $this->customer->getId();
    $productId = (int) $this->product->getId();

    expect(productAlertViaPage($type, $productId))->toBe(['accepted' => true, 'messages' => [$pageMessage]])
        ->and(productAlertProducts(Type::from($type), $customerId))->toBe([$productId]);

    Mage::getService('productalert/alert')->unsubscribe(Type::from($type), $customerId, $productId, (int) Mage::app()->getStore()->getId());
    foreach ([false, true] as $graphQl) {
        $api = productAlertViaApi($customerId, $type, $productId, $graphQl);
        expect($api['errors'])->toBe([])
            ->and($api['result']->id)->toBe("{$type}-{$productId}")
            ->and($api['result']->productId)->toBe($productId)
            // A second subscription keeps one alert
            ->and(productAlertProducts(Type::from($type), $customerId))->toBe([$productId]);
    }
})->with([
    'price' => ['price', 'The alert subscription has been saved.'],
    'stock' => ['stock', 'Alert subscription has been saved.'],
]);

it('keeps the price of the product in a price alert', function () {
    $customerId = (int) $this->customer->getId();
    $productId = (int) $this->product->getId();
    $storeId = (int) Mage::app()->getStore()->getId();
    $price = (float) Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId)
        ->setCustomerGroupId((int) $this->customer->getGroupId())->getFinalPrice();

    $api = productAlertViaApi($customerId, 'price', $productId);

    expect($api['result']->price)->toBe($price)
        ->and(Mage::getService('productalert/alert')->getByProduct(Type::Price, $customerId, $productId, $storeId)->getPrice())->toBe($price);
});

it('refuses a subscription on both transports when the store has the alert off', function (string $type, string $path) {
    Mage::app()->getStore()->setConfig($path, '0');
    $customerId = (int) $this->customer->getId();
    $productId = (int) $this->product->getId();

    $api = productAlertViaApi($customerId, $type, $productId);
    expect(productAlertViaPage($type, $productId))->toBe(['accepted' => false, 'messages' => ['This product alert is not available.']])
        ->and($api['status'])->toBe(404)
        ->and($api['errors'])->toBe(['This product alert is not available.'])
        ->and(productAlertProducts(Type::from($type), $customerId))->toBe([]);
})->with([
    'price' => ['price', Mage_ProductAlert_Model_Observer::XML_PATH_PRICE_ALLOW],
    'stock' => ['stock', Mage_ProductAlert_Model_Observer::XML_PATH_STOCK_ALLOW],
]);

it('refuses a product that the store does not show on both transports', function () {
    $hidden = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
        ->setPageSize(1)
        ->getFirstItem();
    if (!$hidden->getId()) {
        $this->markTestSkipped('No product that is not visible individually');
    }
    $customerId = (int) $this->customer->getId();

    $api = productAlertViaApi($customerId, 'stock', (int) $hidden->getId());
    expect(productAlertViaPage('stock', (int) $hidden->getId()))->toBe(['accepted' => false, 'messages' => ['The product was not found.']])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe(['The product was not found.'])
        ->and(productAlertProducts(Type::Stock, $customerId))->toBe([]);
});

it('refuses a type that is not price or stock on the API', function () {
    $api = productAlertViaApi((int) $this->customer->getId(), 'colour', (int) $this->product->getId());

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['type']);
});

it('lists and deletes the alerts of the customer on the API', function () {
    $customerId = (int) $this->customer->getId();
    $productId = (int) $this->product->getId();
    $security = productAlertSecurity($customerId);
    productAlertViaApi($customerId, 'price', $productId);
    productAlertViaApi($customerId, 'stock', $productId);

    $list = iterator_to_array(new ProductAlertProvider($security)->provide(new GetCollection(), [], []));
    expect(array_map(fn(ProductAlert $alert): ?string => $alert->id, $list))->toBe(["price-{$productId}", "stock-{$productId}"]);

    $request = AdminApiParity::jsonRequest('DELETE', "/api/rest/v2/customers/me/product-alerts/price-{$productId}");
    $delete = fn() => new ProductAlertProcessor($security)->process(null, new Delete(), ['id' => "price-{$productId}"], ['request' => $request]);
    expect(AdminApiParity::api($request, $delete)['errors'])->toBe([])
        ->and(productAlertProducts(Type::Price, $customerId))->toBe([])
        ->and(productAlertProducts(Type::Stock, $customerId))->toBe([$productId])
        ->and(AdminApiParity::api($request, $delete)['status'])->toBe(404);
});
