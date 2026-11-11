<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The cart page moves a cart line to the wishlist through Mage_Wishlist_Service_Wishlist::moveFromCart() (#1348).
 * The controller wrote both models itself, and it removed the cart line also when the wishlist refused the product.
 */

function wishlistFromCartProduct(int $visibility): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-wishlist-fromcart-' . uniqid())->setName('Pest wishlist from cart product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility($visibility)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $product->save();
    return $product;
}

/**
 * Return a new cart with one line of $product, and the ID of the line.
 *
 * @return array{Mage_Sales_Model_Quote, int}
 */
function wishlistFromCartQuote(Mage_Catalog_Model_Product $product): array
{
    $quote = Mage::getService('checkout/cart')->create(null, 1);
    $quote->addProduct(Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId()), new Maho\DataObject(['qty' => 2]));
    $quote->collectTotals()->save();
    $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());

    return [$quote, (int) $quote->getAllVisibleItems()[0]->getId()];
}

/**
 * @return list<int> the product IDs of the lines of the cart $quoteId
 */
function wishlistFromCartLines(int $quoteId): array
{
    return array_map(
        fn(Mage_Sales_Model_Quote_Item $item): int => (int) $item->getProductId(),
        Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId)->getAllVisibleItems(),
    );
}

/**
 * @return list<int> the product IDs of the items of $wishlist
 */
function wishlistFromCartItems(Mage_Wishlist_Model_Wishlist $wishlist): array
{
    return array_map(intval(...), Mage::getModel('wishlist/item')->getCollection()->setWishlist($wishlist)->getColumnValues('product_id'));
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '1');
    Mage::getSingleton('checkout/session')->getMessages(true);

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail('wishlist-fromcart-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();
    $this->wishlist = Mage::getService('wishlist/wishlist')->getForCustomer((int) $this->customer->getId(), 1);
    $this->products = [];
    $this->quotes = [];
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    Mage::unregister('wishlist');
    Mage::unregister('_singleton/checkout/cart');
    foreach ($this->quotes as $quote) {
        $quote->delete();
    }
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    foreach ($this->products as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
});

it('adds the product of the cart line to the wishlist and removes the line', function () {
    $this->products[] = $product = wishlistFromCartProduct(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    [$quote, $lineId] = wishlistFromCartQuote($product);
    $this->quotes[] = $quote;

    $item = Mage::getService('wishlist/wishlist')->moveFromCart($this->wishlist, $quote, $lineId);

    expect((int) $item->getProductId())->toBe((int) $product->getId())
        ->and((float) $item->getQty())->toBe(2.0)
        ->and(wishlistFromCartItems($this->wishlist))->toBe([(int) $product->getId()])
        ->and(wishlistFromCartLines((int) $quote->getId()))->toBe([]);
});

it('keeps the cart line when the wishlist refuses the product', function () {
    $this->products[] = $product = wishlistFromCartProduct(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
    [$quote, $lineId] = wishlistFromCartQuote($product);
    $this->quotes[] = $quote;

    expect(fn() => Mage::getService('wishlist/wishlist')->moveFromCart($this->wishlist, $quote, $lineId))
        ->toThrow(Mage_Core_Exception::class, 'Product not found')
        ->and(wishlistFromCartItems($this->wishlist))->toBe([])
        ->and(wishlistFromCartLines((int) $quote->getId()))->toBe([(int) $product->getId()]);
});

it('answers that the cart has no such line', function () {
    $this->products[] = $product = wishlistFromCartProduct(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    [$quote] = wishlistFromCartQuote($product);
    $this->quotes[] = $quote;

    expect(fn() => Mage::getService('wishlist/wishlist')->moveFromCart($this->wishlist, $quote, 999999999))
        ->toThrow(Mage_Core_Exception_NoSuchEntity::class, "Requested cart item doesn't exist");
});

it('keeps the cart line on the cart page when the wishlist refuses the product', function () {
    $this->products[] = $product = wishlistFromCartProduct(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
    [$quote, $lineId] = wishlistFromCartQuote($product);
    $this->quotes[] = $quote;
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    Mage::getSingleton('checkout/cart')->setQuote($quote);

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/wishlist/index/fromcart', 'POST', ['item' => $lineId]));
    $request->setRouteName('wishlist')->setControllerName('index')->setActionName('fromcart')->setDispatched(true);
    new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http())->fromcartAction();

    $messages = [];
    foreach (Mage::getSingleton('checkout/session')->getMessages(true)->getItems() as $message) {
        $messages[] = $message->getText();
    }
    expect($messages)->toBe(['Product not found'])
        ->and(wishlistFromCartItems($this->wishlist))->toBe([])
        ->and(wishlistFromCartLines((int) $quote->getId()))->toBe([(int) $product->getId()]);
});
