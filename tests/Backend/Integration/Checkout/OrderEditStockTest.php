<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An order edit of the admin cancels the old order and places the new one in one transaction, the cancel first
 * (#1348). When one of the two steps fails, the store has the old order as it was, with its stock, and no new order.
 */

/**
 * Return a guest quote of the admin order create with $qty units of $product, the addresses and check / money order.
 */
function orderEditStockQuote(Mage_Catalog_Model_Product $product, float $qty): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setIsSuperMode(true)->setIsActive(false)
        ->setCustomerIsGuest(true)->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    $quote->addProduct(Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId()), $qty);
    foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
        $address->setCountryId('US')->setRegionId(12)->setPostcode('90210')->setFirstname('Order')->setLastname('Edit')
            ->setStreet('1 Admin St')->setCity('Los Angeles')->setTelephone('5550100')->setEmail('order.edit@example.com');
    }
    $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
    $quote->collectTotals();
    $quote->getPayment()->importData(['method' => 'checkmo']);
    $quote->save();

    return $quote;
}

function orderEditStockCreate(Mage_Sales_Model_Quote $quote, ?Mage_Sales_Model_Order $oldOrder): Mage_Sales_Model_Order
{
    $session = Mage::getSingleton('adminhtml/session_quote')->setStoreId(1)->setCustomerId(0)->setCustomerIsGuest(true);
    (function () use ($oldOrder): void {
        $this->_order = $oldOrder;
    })->call($session);
    try {
        return Mage::getModel('adminhtml/sales_order_create')->setQuote($quote)
            ->setData('account', ['email' => 'order.edit@example.com'])
            ->setData('send_confirmation', 0)
            ->createOrder();
    } finally {
        (function (): void {
            $this->_order = null;
        })->call($session);
    }
}

function orderEditStockQty(Mage_Catalog_Model_Product $product): float
{
    return (float) Mage::getModel('cataloginventory/stock_item')->loadByProduct((int) $product->getId())->getQty();
}

beforeEach(function () {
    $this->product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-order-edit-' . uniqid())->setName('Pest order edit product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 1, 'use_config_backorders' => 0, 'backorders' => 0, 'is_in_stock' => 1, 'qty' => 2]);
    $this->product->save();
    $this->quotes = [];
});

afterEach(function () {
    Mage::getSingleton('adminhtml/session_quote')->clear();
    Mage::register('isSecureArea', true, true);
    foreach ($this->quotes as $quote) {
        $order = Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
        if ($order->getId()) {
            $order->delete();
        }
        $quote->delete();
    }
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('returns the stock of the old order and takes the stock of the new order', function () {
    $this->quotes[] = $first = orderEditStockQuote($this->product, 1);
    $old = orderEditStockCreate($first, null);
    expect(orderEditStockQty($this->product))->toBe(1.0);

    $this->quotes[] = $second = orderEditStockQuote($this->product, 1);
    $new = orderEditStockCreate($second, $old);

    expect($new->getId())->not->toBeNull()
        ->and(Mage::getModel('sales/order')->load($old->getId())->isCanceled())->toBeTrue()
        ->and((int) $new->getRelationParentId())->toBe((int) $old->getId())
        ->and(orderEditStockQty($this->product))->toBe(1.0);
});

it('places no new order when the cancel of the old order fails', function () {
    $this->quotes[] = $first = orderEditStockQuote($this->product, 1);
    $old = orderEditStockCreate($first, null);
    $this->quotes[] = $second = orderEditStockQuote($this->product, 1);
    Mage::unregister('_service/sales/order');
    Mage::register('_service/sales/order', new class extends Mage_Sales_Service_Order {
        #[\Override]
        public function cancel(Mage_Sales_Model_Order $order, ?string $reason = null): Mage_Sales_Model_Order
        {
            throw new Mage_Core_Exception_Conflict('Another operation is already in progress for this order');
        }
    });

    try {
        expect(fn() => orderEditStockCreate($second, $old))->toThrow(Mage_Core_Exception_Conflict::class);
    } finally {
        Mage::unregister('_service/sales/order');
    }

    expect(Mage::getModel('sales/order')->load($second->getId(), 'quote_id')->getId())->toBeNull()
        ->and(Mage::getModel('sales/order')->load($old->getId())->isCanceled())->toBeFalse()
        ->and(orderEditStockQty($this->product))->toBe(1.0);
});

it('keeps the old order and its stock when the new order cannot be placed', function () {
    $this->quotes[] = $first = orderEditStockQuote($this->product, 1);
    $old = orderEditStockCreate($first, null);
    $this->quotes[] = $second = orderEditStockQuote($this->product, 1);
    Mage::unregister('_service/sales/order');
    Mage::register('_service/sales/order', new class extends Mage_Sales_Service_Order {
        #[\Override]
        public function submit(
            Mage_Sales_Model_Quote $quote,
            Mage_Checkout_Model_Actor $actor,
            ?array $agreementIds = null,
            array $orderData = [],
            ?bool $sendConfirmation = null,
            ?Mage_Sales_Model_Convert_Quote $convertor = null,
            ?callable $afterSave = null,
        ): Mage_Sales_Model_Order {
            throw new Mage_Core_Exception('The payment of the new order failed');
        }
    });

    try {
        expect(fn() => orderEditStockCreate($second, $old))->toThrow(Mage_Core_Exception::class, 'The payment of the new order failed');
    } finally {
        Mage::unregister('_service/sales/order');
    }

    // The cancel returned one unit, and the transaction took it back
    expect($old->isCanceled())->toBeFalse()
        ->and(Mage::getModel('sales/order')->load($old->getId())->isCanceled())->toBeFalse()
        ->and(orderEditStockQty($this->product))->toBe(1.0);
});
