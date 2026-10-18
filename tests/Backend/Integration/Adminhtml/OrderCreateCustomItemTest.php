<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Pins the custom line of admin order creation: a line with a name, a price and a tax class,
 * and no catalog product behind it.
 */
const CUSTOM_ITEM_TAX_CLASS = 2;

function customItemOrderCreate(Mage_Sales_Model_Quote $quote): Mage_Adminhtml_Model_Sales_Order_Create
{
    return Mage::getModel('adminhtml/sales_order_create')->setQuote($quote);
}

/** A store-1 quote with addresses, and no items. */
function customItemEmptyQuote(): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->setIsActive();
    foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
        $address->setCountryId('US')
            ->setRegionId(12)
            ->setPostcode('90210')
            ->setFirstname('Test')
            ->setLastname('Customer')
            ->setStreet('123 Test St')
            ->setCity('Beverly Hills')
            ->setTelephone('555-1234')
            ->setEmail('custom-item@example.com');
    }
    $quote->save();

    return $quote;
}

/** @return list<Mage_Sales_Model_Quote_Item> */
function customItemsOf(Mage_Sales_Model_Quote $quote): array
{
    return array_values(array_filter(
        $quote->getAllItems(),
        fn(Mage_Sales_Model_Quote_Item $item) => $item->isCustomItem(),
    ));
}

function customItemReload(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Quote
{
    return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
}

function customItemPlace(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Order
{
    if (!$quote->isVirtual()) {
        $quote->getShippingAddress()->setCollectShippingRates()->setShippingMethod('flatrate_flatrate');
    }
    $quote->collectTotals();
    // A payment method checks the items, so it comes after them.
    $quote->getPayment()->importData(['method' => 'checkmo']);
    $quote->save();

    return Mage::getService('sales/order')->place($quote)['order'];
}

/**
 * Run $body and fail on a PHP warning or deprecation. A NULL product id used as an array key is a
 * deprecation in PHP 8.5, a missing key is a warning, and the test runner only reports those.
 *
 * @param callable():void $body
 */
function customItemFailOnPhpError(callable $body): void
{
    set_error_handler(
        fn(int $errno, string $errstr, string $errfile, int $errline) => throw new ErrorException($errstr, 0, $errno, $errfile, $errline),
        E_WARNING | E_DEPRECATED,
    );
    try {
        $body();
    } finally {
        restore_error_handler();
    }
}

beforeEach(function () {
    $this->product = loadSimplePricedProduct();
    $this->stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($this->product);
    $this->stockQty = (float) $this->stock->getQty();
    $this->stockIsIn = (bool) $this->stock->getIsInStock();
});

afterEach(function () {
    $this->stock->setQty($this->stockQty)->setIsInStock($this->stockIsIn)->save();
});

it('adds a line with the entered name, price and tax class', function () {
    $quote = customItemEmptyQuote();
    customItemOrderCreate($quote)->addCustomItem('Installation', 40.0, CUSTOM_ITEM_TAX_CLASS, false, 0.0);
    customItemFailOnPhpError(fn() => $quote->collectTotals()->save());

    $items = customItemsOf($quote);
    expect($items)->toHaveCount(1);
    expect($items[0]->getProductId())->toBeNull();
    expect($items[0]->getName())->toBe('Installation');
    expect($items[0]->getTaxClassId())->toBe(CUSTOM_ITEM_TAX_CLASS);
    expect((float) $items[0]->getRowTotal())->toEqualWithDelta(40.0, 0.0001);

    $calculation = Mage::getSingleton('tax/calculation');
    $request = $calculation->getRateRequest(
        $quote->getShippingAddress(),
        $quote->getBillingAddress(),
        $quote->getCustomerTaxClassId(),
        $quote->getStore(),
    );
    $rate = (float) $calculation->getRate($request->setProductClassId(CUSTOM_ITEM_TAX_CLASS));
    if ($rate <= 0) {
        test()->markTestSkipped('This install has no tax rate for the address under test');
    }
    expect((float) $items[0]->getTaxPercent())->toEqualWithDelta($rate, 0.0001);
    expect((float) $items[0]->getTaxAmount())->toBeGreaterThan(0.0);
});

it('keeps the line when the quote loads again', function () {
    $quote = customItemEmptyQuote();
    customItemOrderCreate($quote)->addCustomItem('Assembly', 25.5, CUSTOM_ITEM_TAX_CLASS, true, 3.0);
    $quote->collectTotals()->save();

    $reloaded = customItemReload($quote);
    $reloaded->collectTotals();
    $items = customItemsOf($reloaded);

    expect($items)->toHaveCount(1);
    expect($items[0]->getName())->toBe('Assembly');
    expect($items[0]->getTaxClassId())->toBe(CUSTOM_ITEM_TAX_CLASS);
    expect($items[0]->getProductType())->toBe(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    expect($items[0]->getWeight())->toEqualWithDelta(3.0, 0.0001);
    expect((float) $items[0]->getRowTotal())->toEqualWithDelta(25.5, 0.0001);
});

it('keeps two lines with the same values apart', function () {
    $quote = customItemEmptyQuote();
    $create = customItemOrderCreate($quote);
    $create->addCustomItem('Surcharge', 5.0, CUSTOM_ITEM_TAX_CLASS, false, 0.0);
    $create->addCustomItem('Surcharge', 5.0, CUSTOM_ITEM_TAX_CLASS, false, 0.0);
    $quote->collectTotals()->save();

    expect(customItemsOf(customItemReload($quote)))->toHaveCount(2);
});

it('makes a quote with only a line that needs no shipping virtual', function () {
    $quote = customItemEmptyQuote();
    customItemOrderCreate($quote)->addCustomItem('Consulting hour', 90.0, 0, false, 0.0);
    $quote->collectTotals()->save();

    expect(customItemReload($quote)->isVirtual())->toBeTrue();
});

it('refuses a line that needs shipping and has no weight', function () {
    customItemOrderCreate(customItemEmptyQuote())->addCustomItem('Table', 300.0, CUSTOM_ITEM_TAX_CLASS, true, 0.0);
})->throws(Mage_Core_Exception::class);

it('refuses a line with no name', function () {
    customItemOrderCreate(customItemEmptyQuote())->addCustomItem('  ', 10.0, CUSTOM_ITEM_TAX_CLASS, false, 0.0);
})->throws(Mage_Core_Exception::class);

it('refuses a line with a negative price', function () {
    customItemOrderCreate(customItemEmptyQuote())->addCustomItem('Credit', -10.0, CUSTOM_ITEM_TAX_CLASS, false, 0.0);
})->throws(Mage_Core_Exception::class);

it('refuses a tax class that is not a product tax class', function () {
    $customerClassId = (int) Mage::getModel('tax/class')->getCollection()
        ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)
        ->getFirstItem()
        ->getId();

    customItemOrderCreate(customItemEmptyQuote())->addCustomItem('Fee', 10.0, $customerClassId, false, 0.0);
})->throws(Mage_Core_Exception::class);

it('places an order with the line, then invoices and refunds it with return to stock', function () {
    $quote = customItemEmptyQuote();
    $quote->addProduct($this->product, 1);
    customItemOrderCreate($quote)->addCustomItem('Installation', 40.0, CUSTOM_ITEM_TAX_CLASS, true, 2.0);

    $order = null;
    customItemFailOnPhpError(function () use ($quote, &$order) {
        $order = customItemPlace($quote);
    });
    $orderItem = null;
    foreach ($order->getAllItems() as $item) {
        if ($item->isCustomItem()) {
            $orderItem = $item;
        }
    }

    expect($orderItem)->not->toBeNull();
    expect($orderItem->getName())->toBe('Installation');
    expect($orderItem->getTaxClassId())->toBe(CUSTOM_ITEM_TAX_CLASS);
    expect($orderItem->getIsVirtual())->toBeFalse();

    $order = Mage::getModel('sales/order')->load($order->getId());
    $invoice = $order->prepareInvoice();
    $invoice->register();
    Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();

    $order = Mage::getModel('sales/order')->load($order->getId());
    $creditmemo = Mage::getModel('sales/service_order', $order)->prepareCreditmemo();
    foreach ($creditmemo->getAllItems() as $creditmemoItem) {
        $creditmemoItem->setBackToStock(true);
    }
    $creditmemo->setOfflineRequested(true);
    $creditmemo->register();
    customItemFailOnPhpError(function () use ($creditmemo, $order) {
        Mage::getModel('core/resource_transaction')->addObject($creditmemo)->addObject($order)->save();
    });

    expect((float) $creditmemo->getGrandTotal())->toEqualWithDelta((float) $order->getGrandTotal(), 0.0001);
});

it('keeps the line when an order loads into a new quote', function () {
    $quote = customItemEmptyQuote();
    customItemOrderCreate($quote)->addCustomItem('Deposit', 100.0, CUSTOM_ITEM_TAX_CLASS, false, 0.0);
    $order = customItemPlace($quote);

    $orderItem = $order->getAllItems()[0];
    $newQuote = customItemEmptyQuote();
    customItemOrderCreate($newQuote)->initFromOrderItem($orderItem, 1);
    $newQuote->collectTotals()->save();

    $items = customItemsOf(customItemReload($newQuote));
    expect($items)->toHaveCount(1);
    expect($items[0]->getName())->toBe('Deposit');
    expect($items[0]->getTaxClassId())->toBe(CUSTOM_ITEM_TAX_CLASS);
    expect((float) $items[0]->getRowTotal())->toEqualWithDelta(100.0, 0.0001);
});

it('lets the admin reorder a real product next to a line', function () {
    $quote = customItemEmptyQuote();
    $quote->addProduct($this->product, 1);
    customItemOrderCreate($quote)->addCustomItem('Installation', 40.0, CUSTOM_ITEM_TAX_CLASS, false, 0.0);

    expect(customItemPlace($quote)->canReorderIgnoreSalable())->toBeTrue();
});

it('finds no catalog rule price for an item with no product', function () {
    customItemFailOnPhpError(function () {
        expect(Mage::getResourceModel('catalogrule/rule')->getRulePrice(time(), 1, 0, null))->toBeFalse();
    });
});

it('applies no catalog rule price to a custom line in the admin', function () {
    $product = Mage::getModel('catalog/product')
        ->setStoreId(1)
        ->setCustomerGroupId(0)
        ->setFinalPrice(5.0);
    $observer = new Maho\Event\Observer(['event' => new Maho\Event(['product' => $product])]);

    customItemFailOnPhpError(fn() => Mage::getSingleton('catalogrule/observer')->processAdminFinalPrice($observer));

    expect($product->getData('final_price'))->toBe(5.0);
});
