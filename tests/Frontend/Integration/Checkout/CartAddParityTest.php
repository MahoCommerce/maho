<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Checkout\Api\CartProcessor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The add to cart of the storefront and POST /guest-carts/{id}/items add a product through
 * Mage_Checkout_Service_Cart::addProduct() (#1348). Each test gives the same product to both transports and
 * compares the outcome.
 */

/**
 * Create a simple product of the main website. $data and $stock replace the defaults.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $stock
 * @param list<array<string, mixed>> $options
 */
function cartAddParityProduct(array $data = [], array $stock = [], array $options = []): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-cart-add-' . uniqid())->setName('Pest cart add product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData($stock + ['use_config_manage_stock' => 0, 'manage_stock' => 1, 'is_in_stock' => 1, 'qty' => 100])
        ->addData($data);
    if ($options !== []) {
        $product->setHasOptions(true)->setRequiredOptions(true)->setCanSaveCustomOptions(true)->setProductOptions($options);
    }
    $product->save();
    return $product;
}

/**
 * Return a not visible simple product of the main website that is a variant of a configurable product of the
 * storefront, and its parent.
 *
 * @return array{0: Mage_Catalog_Model_Product, 1: Mage_Catalog_Model_Product}|null
 */
function cartAddParityVariant(): ?array
{
    $parents = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->addWebsiteFilter(1)
        ->setPageSize(20);
    foreach ($parents as $parent) {
        $parent = Mage::getModel('catalog/product')->setStoreId(1)->load($parent->getId());
        foreach ($parent->getTypeInstance(true)->getUsedProducts(null, $parent) as $child) {
            $child = Mage::getModel('catalog/product')->setStoreId(1)->load($child->getId());
            if ((int) $child->getStatus() === Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                && (int) $child->getVisibility() === Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
                && $child->isSalable()
            ) {
                return [$child, $parent];
            }
        }
    }
    return null;
}

/**
 * Post $params to the add to cart of the storefront, with $quote as the cart of the session.
 *
 * @param array<string, mixed> $params
 * @return array{success: list<string>, errors: list<string>, redirect: string|null}
 */
function cartAddViaWeb(Mage_Sales_Model_Quote $quote, Mage_Catalog_Model_Product $product, array $params = []): array
{
    Mage::getSingleton('checkout/session')->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId()));
    Mage::getSingleton('checkout/cart')->unsetData('quote');

    // getParams() reads the superglobals, as on a real request
    $_POST = ['product' => $product->getId()] + $params;
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/add', 'POST', $_POST));
    $request->setRouteName('checkout')->setControllerName('cart')->setActionName('add')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    try {
        new Mage_Checkout_CartController($request, $response)->addAction();
    } finally {
        $_POST = [];
    }

    $messages = ['success' => [], 'errors' => [], 'redirect' => $response->getSymfonyResponse()->headers->get('Location')];
    foreach (Mage::getSingleton('checkout/session')->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

/**
 * Post $body to POST /api/rest/v2/guest-carts/{id}/items.
 *
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function cartAddViaApi(Mage_Sales_Model_Quote $quote, Mage_Catalog_Model_Product $product, array $body = [], ?Security $security = null): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/guest-carts/{$maskedId}/items", ['sku' => $product->getSku()] + $body);
    $processor = new CartProcessor($security ?? CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Post(name: 'add_guest_item'), ['id' => $maskedId], ['request' => $request]));
}

/**
 * @return list<array{product_id: int, qty: float, parent: bool}>
 */
function cartAddParityLines(Mage_Sales_Model_Quote $quote): array
{
    $lines = [];
    foreach (Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getAllItems() as $item) {
        $lines[] = ['product_id' => (int) $item->getProductId(), 'qty' => (float) $item->getQty(), 'parent' => !$item->getParentItemId()];
    }
    return $lines;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->carts = [CartParity::cart(withItem: false), CartParity::cart(withItem: false)];
    $this->products = [];
});

afterEach(function () {
    foreach ($this->carts as $cart) {
        $cart->delete();
    }
    Mage::register('isSecureArea', true, true);
    foreach ($this->products as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
});

it('adds a product that the storefront shows on both transports', function () {
    $this->products[] = $product = cartAddParityProduct();
    [$webCart, $apiCart] = $this->carts;

    $web = cartAddViaWeb($webCart, $product, ['qty' => 2]);
    $api = cartAddViaApi($apiCart, $product, ['qty' => 2]);

    $line = [['product_id' => (int) $product->getId(), 'qty' => 2.0, 'parent' => true]];
    expect($web['success'])->toBe(['Pest cart add product was added to your shopping cart.'])
        ->and($api['status'])->toBe(200)
        ->and(cartAddParityLines($webCart))->toBe($line)
        ->and(cartAddParityLines($apiCart))->toBe($line);
});

it('refuses a product that is not available with the same message', function (array $data) {
    $this->products[] = $product = cartAddParityProduct($data);
    [$webCart, $apiCart] = $this->carts;

    $web = cartAddViaWeb($webCart, $product);
    $api = cartAddViaApi($apiCart, $product);

    $message = "Product '{$product->getSku()}' is not available";
    expect($web['errors'])->toBe([$message])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe([$message])
        ->and(cartAddParityLines($webCart))->toBe([])
        ->and(cartAddParityLines($apiCart))->toBe([]);
})->with([
    'disabled' => [['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED]],
    'not visible, with no configurable parent' => [['visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE]],
    'in another website only' => [['website_ids' => [2]]],
]);

it('adds a variant as its configurable parent on both transports', function () {
    $variant = cartAddParityVariant();
    if ($variant === null) {
        $this->markTestSkipped('The store has no configurable product with a variant that the storefront does not show');
    }
    [$child, $parent] = $variant;
    [$webCart, $apiCart] = $this->carts;

    cartAddViaWeb($webCart, $child);
    cartAddViaApi($apiCart, $child);

    $lines = [
        ['product_id' => (int) $parent->getId(), 'qty' => 1.0, 'parent' => true],
        ['product_id' => (int) $child->getId(), 'qty' => 1.0, 'parent' => false],
    ];
    expect(cartAddParityLines($webCart))->toBe($lines)
        ->and(cartAddParityLines($apiCart))->toBe($lines);
});

it('raises a new line below the minimum sale qty to the minimum on both transports', function () {
    $this->products[] = $product = cartAddParityProduct(stock: ['use_config_min_sale_qty' => 0, 'min_sale_qty' => 3]);
    [$webCart, $apiCart] = $this->carts;

    cartAddViaWeb($webCart, $product, ['qty' => 1]);
    $api = cartAddViaApi($apiCart, $product, ['qty' => 1]);

    $line = [['product_id' => (int) $product->getId(), 'qty' => 3.0, 'parent' => true]];
    expect($api['status'])->toBe(200)
        ->and(cartAddParityLines($webCart))->toBe($line)
        ->and(cartAddParityLines($apiCart))->toBe($line);
});

it('refuses a product that misses a required option with the message of the product', function () {
    $this->products[] = $product = cartAddParityProduct(options: [[
        'title' => 'Engraving', 'type' => 'field', 'is_require' => 1, 'price' => 0, 'price_type' => 'fixed', 'sort_order' => 0,
    ]]);
    [$webCart, $apiCart] = $this->carts;

    $web = cartAddViaWeb($webCart, $product);
    $api = cartAddViaApi($apiCart, $product);

    $message = 'Please specify the product required option "Engraving".';
    expect($web['errors'])->toBe([$message])
        ->and($web['redirect'])->toContain((string) $product->getUrlKey())
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe([$message])
        ->and(cartAddParityLines($webCart))->toBe([])
        ->and(cartAddParityLines($apiCart))->toBe([]);
});

it('refuses an uploaded file for an option that is not a file option', function () {
    $this->products[] = $product = cartAddParityProduct(options: [[
        'title' => 'Engraving', 'type' => 'field', 'is_require' => 0, 'price' => 0, 'price_type' => 'fixed', 'sort_order' => 0,
    ]]);
    $optionId = (int) Mage::getModel('catalog/product_option')->getCollection()
        ->addFieldToFilter('product_id', $product->getId())->getFirstItem()->getId();
    $files = [$optionId => ['name' => 'a.txt', 'base64_encoded_data' => base64_encode('a')]];
    [$webCart, $apiCart] = $this->carts;

    $web = cartAddViaWeb($webCart, $product, ['options_files' => $files]);
    $api = cartAddViaApi($apiCart, $product, ['options_files' => $files]);

    $message = "Option ID {$optionId} is not a valid file-type option for this product";
    expect($web['errors'])->toBe([$message])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe([$message])
        ->and(cartAddParityLines($webCart))->toBe([])
        ->and(cartAddParityLines($apiCart))->toBe([]);
});

it('refuses a qty above 10,000 with the same message', function () {
    $this->products[] = $product = cartAddParityProduct(stock: ['manage_stock' => 0]);
    [$webCart, $apiCart] = $this->carts;

    $web = cartAddViaWeb($webCart, $product, ['qty' => 10001]);
    $api = cartAddViaApi($apiCart, $product, ['qty' => 10001]);

    expect($web['errors'])->toBe(['Quantity cannot exceed 10,000'])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe(['Quantity cannot exceed 10,000']);
});

it('fires the add events for an add of the API, as for the storefront', function () {
    $this->products[] = $product = cartAddParityProduct();
    $spy = new class {
        /** @var list<string> */
        public array $events = [];

        public function record(Maho\Event\Observer $observer): void
        {
            $this->events[] = (string) $observer->getEvent()->getName();
        }
    };
    Mage::register('_singleton/cart_add_parity_spy', $spy, true);
    $events = ['checkout_cart_product_add_before', 'checkout_cart_product_add_after'];
    foreach ($events as $event) {
        (function () use ($event): void {
            $this->_events['global'][$event] = ['observers' => [
                'cart_add_parity_spy' => ['type' => 'singleton', 'model' => 'cart_add_parity_spy', 'method' => 'record'],
            ]];
        })->call(Mage::app());
    }

    try {
        cartAddViaWeb($this->carts[0], $product);
        $api = cartAddViaApi($this->carts[1], $product);
    } finally {
        foreach ($events as $event) {
            (function () use ($event): void {
                unset($this->_events['global'][$event]);
            })->call(Mage::app());
        }
        Mage::unregister('_singleton/cart_add_parity_spy');
    }

    expect($api['status'])->toBe(200)
        ->and($spy->events)->toBe([...$events, ...$events]);
});

describe('a custom price', function () {
    it('is not a field of the storefront, and the API refuses it from a guest', function () {
        $this->products[] = $product = cartAddParityProduct();
        [$webCart, $apiCart] = $this->carts;

        cartAddViaWeb($webCart, $product, ['custom_price' => 0.01]);
        $api = cartAddViaApi($apiCart, $product, ['customPrice' => 0.01]);

        expect(Mage::getModel('sales/quote')->loadByIdWithoutStore($webCart->getId())->getAllItems()[0]->getCustomPrice())->toBeNull()
            ->and($api['status'])->toBe(403)
            ->and(cartAddParityLines($apiCart))->toBe([]);
    });
});

describe('staff (D2)', function () {
    it('can add a product that the storefront does not show, and a guest cannot', function () {
        $this->products[] = $product = cartAddParityProduct(['visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE]);
        [$guestCart, $staffCart] = $this->carts;

        $guest = cartAddViaApi($guestCart, $product);
        $staff = cartAddViaApi($staffCart, $product, [], AdminApiParity::adminSecurity());

        expect($guest['status'])->toBe(422)
            ->and($staff['status'])->toBe(200)
            ->and(cartAddParityLines($staffCart))->toBe([['product_id' => (int) $product->getId(), 'qty' => 1.0, 'parent' => true]]);
    });
});

describe('a qty of 0 on an update (D4)', function () {
    it('removes the line on the cart page, and the API refuses it', function () {
        $this->products[] = $product = cartAddParityProduct();
        [$webCart, $apiCart] = $this->carts;
        cartAddViaWeb($webCart, $product);
        cartAddViaApi($apiCart, $product);
        $webItemId = (int) Mage::getModel('sales/quote')->loadByIdWithoutStore($webCart->getId())->getAllItems()[0]->getId();
        $apiItemId = (int) Mage::getModel('sales/quote')->loadByIdWithoutStore($apiCart->getId())->getAllItems()[0]->getId();

        Mage::getSingleton('checkout/session')->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($webCart->getId()));
        Mage::getSingleton('checkout/cart')->unsetData('quote');
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/updatePost', 'POST', [
            'update_cart_action' => 'update_qty',
            'cart' => [$webItemId => ['qty' => '0']],
        ]));
        $request->setRouteName('checkout')->setControllerName('cart')->setActionName('updatePost')->setDispatched(true);
        Mage::app()->setRequest($request);
        new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http())->updatePostAction();

        $maskedId = (string) $apiCart->getData('masked_quote_id');
        $apiRequest = AdminApiParity::jsonRequest('PUT', "/api/rest/v2/guest-carts/{$maskedId}/items/{$apiItemId}", ['qty' => 0]);
        $api = AdminApiParity::api($apiRequest, fn() => new CartProcessor(CartParity::guestSecurity())
            ->process(null, new Put(name: 'update_guest_item'), ['id' => $maskedId, 'itemId' => $apiItemId], ['request' => $apiRequest]));

        expect(cartAddParityLines($webCart))->toBe([])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Quantity must be greater than zero'])
            ->and(cartAddParityLines($apiCart))->toHaveCount(1);
    });
});
