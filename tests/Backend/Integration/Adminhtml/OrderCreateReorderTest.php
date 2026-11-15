<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The reorder, the order edit, "Last Ordered Items" and the move of a cart item in the admin add each product through
 * Mage_Checkout_Service_Cart with the staff actor (#1348). The reorder in the admin refuses a product that is now
 * disabled or is not in the website, gives an error message for that item, and loads the other items.
 */

function orderCreateReorderProduct(): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-reorder-' . uniqid())->setName('Pest reorder product')->setPrice(12.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1]);
    $product->save();

    return $product;
}

function orderCreateReorderQuote(): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setIsSuperMode(true)->setIsActive(false)
        ->setCustomerIsGuest(true)->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    $quote->save();

    return $quote;
}

function orderCreateReorderModel(Mage_Sales_Model_Quote $quote): Mage_Adminhtml_Model_Sales_Order_Create
{
    $session = Mage::getSingleton('adminhtml/session_quote');
    $session->clear();
    $session->getMessages(true);
    $session->setStoreId(1)->setCustomerId(0)->setCustomerIsGuest(true);

    return Mage::getModel('adminhtml/sales_order_create')->setQuote($quote);
}

/**
 * @return list<string>
 */
function orderCreateReorderErrors(): array
{
    return array_map(
        fn(Mage_Core_Model_Message_Abstract $message): string => $message->getCode(),
        Mage::getSingleton('adminhtml/session_quote')->getMessages()->getErrors(),
    );
}

/**
 * @return list<int>
 */
function orderCreateReorderProductIds(Mage_Sales_Model_Quote $quote): array
{
    return array_map(fn(Mage_Sales_Model_Quote_Item $item): int => (int) $item->getProductId(), $quote->getAllVisibleItems());
}

beforeEach(function () {
    $this->product = orderCreateReorderProduct();
    $this->quotes = [];
    $this->order = null;
    $this->kept = null;
});

afterEach(function () {
    Mage::getSingleton('adminhtml/session_quote')->getMessages(true);
    Mage::getSingleton('adminhtml/session_quote')->clear();
    Mage::register('isSecureArea', true, true);
    $this->order?->delete();
    Mage::unregister('isSecureArea');
    foreach ($this->quotes as $quote) {
        $quote->delete();
    }
    Mage::register('isSecureArea', true, true);
    $this->product->delete();
    $this->kept?->delete();
    Mage::unregister('isSecureArea');
});

it('refuses a disabled product of an order item, and adds a product that the store sells', function () {
    $this->quotes = [$quote = orderCreateReorderQuote()];
    $create = orderCreateReorderModel($quote);
    $orderItem = fn(Mage_Catalog_Model_Product $product): Mage_Sales_Model_Order_Item => Mage::getModel('sales/order_item')
        ->setId(987654321)
        ->setProductId((int) $product->getId())
        ->setProductType(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setProductOptions(['info_buyRequest' => ['qty' => 1]]);

    $create->initFromOrderItem($orderItem($this->product), 2);
    Mage::getSingleton('catalog/product_action')->updateAttributes([(int) $this->product->getId()], ['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED], 0);

    expect(fn() => $create->initFromOrderItem($orderItem($this->product), 1))
        ->toThrow(Mage_Core_Exception::class, "Product '{$this->product->getSku()}' is not available")
        ->and(orderCreateReorderProductIds($quote))->toBe([(int) $this->product->getId()])
        ->and((float) $quote->getAllVisibleItems()[0]->getQty())->toBe(2.0);
});

it('loads the other items of a reorder and gives an error message for the item that the cart refuses', function () {
    $this->kept = $kept = orderCreateReorderProduct();
    $this->quotes = [$source = orderCreateReorderQuote(), $target = orderCreateReorderQuote()];
    $source->addProduct(Mage::getModel('catalog/product')->setStoreId(1)->load($kept->getId()), 1);
    $source->addProduct(Mage::getModel('catalog/product')->setStoreId(1)->load($this->product->getId()), 1);
    foreach ([$source->getBillingAddress(), $source->getShippingAddress()] as $address) {
        $address->setCountryId('US')->setRegionId(12)->setPostcode('90210')->setFirstname('Admin')->setLastname('Reorder')
            ->setStreet('1 Admin St')->setCity('Los Angeles')->setTelephone('5550100')->setEmail('admin.reorder@example.com');
    }
    $source->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
    $source->collectTotals();
    $source->getPayment()->importData(['method' => 'checkmo']);
    $source->save();
    $this->order = orderCreateReorderModel($source)
        ->setData('account', ['email' => 'admin.reorder@example.com'])
        ->setData('send_confirmation', 0)
        ->createOrder();
    Mage::getSingleton('catalog/product_action')->updateAttributes([(int) $this->product->getId()], ['status' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED], 0);

    $order = Mage::getModel('sales/order')->load($this->order->getId())->setReordered(true);
    orderCreateReorderModel($target)->initFromOrder($order);

    expect(orderCreateReorderProductIds($target))->toBe([(int) $kept->getId()])
        ->and(orderCreateReorderErrors())->toBe(["Product '{$this->product->getSku()}' is not available"])
        ->and($target->getShippingAddress()->getShippingMethod())->toBe('flatrate_flatrate');
});
