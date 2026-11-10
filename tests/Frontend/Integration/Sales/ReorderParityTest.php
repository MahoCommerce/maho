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
 * The reorder of the storefront, the reorder of the admin and the Reorder buttons ask
 * Mage_Sales_Service_Order::canReorder() (#1348). Before, the admin and a guest checked only the setting of the store.
 */

/**
 * Save an order of $customer in the state $state, with two units of $product, or of a custom line when $product is null.
 */
function reorderParityOrder(Mage_Customer_Model_Customer $customer, ?Mage_Catalog_Model_Product $product, string $state): Mage_Sales_Model_Order
{
    $address = [
        'firstname' => 'Jane', 'lastname' => 'Doe', 'street' => '1 Main St', 'city' => 'Austin', 'region_id' => 57,
        'postcode' => '73301', 'country_id' => 'US', 'telephone' => '5551234567',
    ];
    $order = Mage::getModel('sales/order')
        ->setIncrementId('REORDER-PARITY-' . uniqid())
        ->setCustomerId((int) $customer->getId())
        ->setCustomerIsGuest(false)
        ->setCustomerEmail($customer->getEmail())
        ->setStoreId(1)
        ->setOrderCurrencyCode('USD')->setBaseCurrencyCode('USD')
        ->setSubtotal(20)->setGrandTotal(20)->setTotalQtyOrdered(2)
        ->setShippingMethod('flatrate_flatrate')
        ->setData('state', $state)
        ->setData('status', $state === Mage_Sales_Model_Order::STATE_HOLDED ? 'holded' : $state);
    $order->setBillingAddress(Mage::getModel('sales/order_address')->setData($address));
    $order->setShippingAddress(Mage::getModel('sales/order_address')->setData($address));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
    $order->addItem(Mage::getModel('sales/order_item')
        ->setProductId($product ? (int) $product->getId() : null)->setProductType(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setSku($product?->getSku() ?? 'gift-wrap')->setName($product?->getName() ?? 'Gift wrap')->setPrice(10)->setQtyOrdered(2)
        ->setProductOptions(['info_buyRequest' => ['qty' => 2]]));
    $order->save();
    return $order;
}

/**
 * Reorder $order on the storefront as the customer of the session. Return the path of the redirect.
 */
function reorderParityStorefront(Mage_Sales_Model_Order $order): string
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/sales/order/reorder', 'POST', [
        'order_id' => $order->getId(),
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]));
    $request->setRouteName('sales')->setControllerName('order')->setActionName('reorder')->setDispatched(true);
    Mage::app()->setRequest($request);
    $controller = new Mage_Sales_OrderController($request, new Mage_Core_Controller_Response_Http());
    $controller->dispatch('reorder');

    return (string) parse_url((string) $controller->getResponse()->getSymfonyResponse()->headers->get('Location'), PHP_URL_PATH);
}

/**
 * Reorder $order in the admin. Return true when the admin opens the order create page, false for a 404.
 */
function reorderParityAdmin(Mage_Sales_Model_Order $order): bool
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/admin/sales_order_create/reorder', 'GET', [
        'order_id' => $order->getId(),
    ]));
    $request->setRouteName('adminhtml')->setControllerName('sales_order_create')->setActionName('reorder')->setDispatched(true);
    Mage::app()->setRequest($request);
    try {
        new Mage_Adminhtml_Sales_Order_CreateController($request, new Mage_Core_Controller_Response_Http())->reorderAction();
    } catch (Throwable) {
        // The trailing redirect builds an admin url outside a dispatched request
    }

    return $request->getActionName() !== 'noRoute';
}

/**
 * @return array<int, float> the qty of each product of $quote
 */
function reorderParityLines(Mage_Sales_Model_Quote $quote): array
{
    $lines = [];
    foreach (Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getAllVisibleItems() as $item) {
        $lines[(int) $item->getProductId()] = (float) $item->getQty();
    }
    return $lines;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig(Mage_Sales_Helper_Reorder::XML_PATH_SALES_REORDER_ALLOW, '1');

    $this->product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-reorder-' . uniqid())->setName('Pest reorder product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 1, 'is_in_stock' => 1, 'qty' => 100]);
    $this->product->save();

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail('reorder-parity-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setForceConfirmed()
        ->setPassword('Password123!');
    $this->customer->save();
    Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($this->customer);
    $this->orders = [];
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    foreach ([Mage::getSingleton('checkout/session')->getQuote(), Mage::getSingleton('adminhtml/session_quote')->getQuote()] as $quote) {
        if ($quote->getId()) {
            $quote->delete();
        }
    }
    Mage::register('isSecureArea', true, true);
    foreach ($this->orders as $order) {
        $order->delete();
    }
    $this->customer->delete();
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('puts the items of a complete order in a cart on both transports', function () {
    $this->orders[] = $order = reorderParityOrder($this->customer, $this->product, Mage_Sales_Model_Order::STATE_COMPLETE);
    $productId = (int) $this->product->getId();

    expect(reorderParityStorefront($order))->toEndWith('/checkout/cart/')
        ->and(reorderParityLines(Mage::getSingleton('checkout/session')->getQuote()))->toBe([$productId => 2.0])
        ->and(reorderParityAdmin($order))->toBeTrue()
        ->and(reorderParityLines(Mage::getSingleton('adminhtml/session_quote')->getQuote()))->toBe([$productId => 2.0]);
});

it('refuses an order on hold on both transports, and hides the button of a guest', function () {
    $this->orders[] = $order = reorderParityOrder($this->customer, $this->product, Mage_Sales_Model_Order::STATE_HOLDED);

    expect(reorderParityStorefront($order))->toContain('/sales/order/view/')
        ->and((int) Mage::getSingleton('checkout/session')->getQuote()->getItemsCount())->toBe(0)
        ->and(reorderParityAdmin($order))->toBeFalse();

    Mage::getSingleton('customer/session')->logout();
    expect(Mage::helper('sales/reorder')->canReorder($order))->toBeFalse();
});

it('refuses an order of a store that does not allow reorders on both transports', function () {
    $this->orders[] = $order = reorderParityOrder($this->customer, $this->product, Mage_Sales_Model_Order::STATE_COMPLETE);
    Mage::app()->getStore()->setConfig(Mage_Sales_Helper_Reorder::XML_PATH_SALES_REORDER_ALLOW, '0');

    expect(reorderParityStorefront($order))->toContain('/sales/order/view/')
        ->and(reorderParityAdmin($order))->toBeFalse();
});

describe('kept difference: staff reorders an order that has only a custom line', function () {
    it('refuses it on the storefront and accepts it in the admin', function () {
        $this->orders[] = $order = reorderParityOrder($this->customer, null, Mage_Sales_Model_Order::STATE_COMPLETE);

        expect(reorderParityStorefront($order))->toContain('/sales/order/view/')
            ->and(reorderParityAdmin($order))->toBeTrue();
    });
});
