<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin order create places the order through Mage_Sales_Service_Order::submit() with the staff actor (#1348).
 */

/**
 * Return a guest quote of the admin order create, with a product, the addresses and check / money order. Without
 * $shippingMethod, the quote has no shipping method.
 */
function orderCreatePlacementQuote(bool $shippingMethod = true): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setIsSuperMode(true)->setIsActive(false)
        ->setCustomerIsGuest(true)->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    $quote->addProduct(loadSimplePricedProduct(), 1);
    foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
        $address->setCountryId('US')->setRegionId(12)->setPostcode('90210')->setFirstname('Admin')->setLastname('Order')
            ->setStreet('1 Admin St')->setCity('Los Angeles')->setTelephone('5550100')->setEmail('admin.order@example.com');
    }
    $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod($shippingMethod ? 'flatrate_flatrate' : null);
    $quote->collectTotals();
    $quote->getPayment()->importData(['method' => 'checkmo']);
    $quote->save();

    return $quote;
}

function orderCreatePlacementModel(Mage_Sales_Model_Quote $quote, bool $sendConfirmation): Mage_Adminhtml_Model_Sales_Order_Create
{
    Mage::getSingleton('adminhtml/session_quote')->setStoreId(1)->setCustomerId(0)->setCustomerIsGuest(true);

    return Mage::getModel('adminhtml/sales_order_create')->setQuote($quote)
        ->setData('account', ['email' => 'admin.order@example.com'])
        ->setData('send_confirmation', $sendConfirmation ? 1 : 0);
}

beforeEach(function () {
    $this->spy = new class extends Mage_Sales_Service_Order {
        /** @var list<string> */
        public array $newOrderEmails = [];

        #[\Override]
        protected function sendNewOrderEmail(Mage_Sales_Model_Order $order): void
        {
            $this->newOrderEmails[] = (string) $order->getIncrementId();
        }
    };
    Mage::unregister('_service/sales/order');
    Mage::register('_service/sales/order', $this->spy);
    $this->events = new class {
        public int $count = 0;

        public function record(): void
        {
            $this->count++;
        }
    };
    Mage::register('_singleton/order_create_placement_listener', $this->events, true);
    (function (): void {
        $this->_events['global']['checkout_submit_all_after']['observers']['order_create_placement_listener']
            = ['type' => 'singleton', 'model' => 'order_create_placement_listener', 'method' => 'record'];
    })->call(Mage::app());
    $this->quotes = [];
});

afterEach(function () {
    (function (): void {
        unset($this->_events['global']['checkout_submit_all_after']['observers']['order_create_placement_listener']);
    })->call(Mage::app());
    Mage::unregister('_singleton/order_create_placement_listener');
    Mage::unregister('_service/sales/order');
    Mage::getSingleton('adminhtml/session_quote')->clear();
    foreach ($this->quotes as $quote) {
        $order = Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
        if ($order->getId()) {
            Mage::register('isSecureArea', true, true);
            $order->delete();
            Mage::unregister('isSecureArea');
        }
        $quote->delete();
    }
});

it('places the order with one checkout_submit_all_after, and sends the email only when the admin asks for it', function () {
    $this->quotes = [$withEmail = orderCreatePlacementQuote(), $withoutEmail = orderCreatePlacementQuote()];

    $first = orderCreatePlacementModel($withEmail, true)->createOrder();
    $second = orderCreatePlacementModel($withoutEmail, false)->createOrder();

    expect($first->getId())->not->toBeNull()
        ->and($second->getId())->not->toBeNull()
        ->and($this->spy->newOrderEmails)->toBe([(string) $first->getIncrementId()])
        ->and($this->events->count)->toBe(2);
});

it('refuses a quote without a shipping method with the message of the order service', function () {
    $this->quotes = [$quote = orderCreatePlacementQuote(shippingMethod: false)];

    $errors = [];
    try {
        orderCreatePlacementModel($quote, true)->createOrder();
    } catch (Mage_Core_Exception_Input $e) {
        $errors = $e->getErrors();
    }

    expect($errors)->toBe([['field' => 'shipping_method', 'message' => 'Please specify a shipping method.']])
        ->and($this->events->count)->toBe(0)
        ->and(Mage::getModel('sales/order')->load($quote->getId(), 'quote_id')->getId())->toBeNull();
});

/**
 * Make $order the order that the admin edits, or no order with null.
 */
function orderCreatePlacementEdit(?Mage_Sales_Model_Order $order): void
{
    $session = Mage::getSingleton('adminhtml/session_quote');
    (function () use ($order): void {
        $this->_order = $order;
    })->call($session);
}

it('leaves the old order of an order edit and its payment as they are when the new order cannot be placed', function () {
    $this->quotes = [$first = orderCreatePlacementQuote(), $second = orderCreatePlacementQuote()];
    $old = orderCreatePlacementModel($first, false)->createOrder();
    $failing = new class extends Mage_Sales_Service_Order {
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
    };
    Mage::unregister('_service/sales/order');
    Mage::register('_service/sales/order', $failing);
    $create = orderCreatePlacementModel($second, false);
    orderCreatePlacementEdit($old);

    try {
        expect(fn() => $create->createOrder())->toThrow(Mage_Core_Exception::class, 'The payment of the new order failed');
    } finally {
        orderCreatePlacementEdit(null);
    }

    expect($old->isCanceled())->toBeFalse()
        ->and(Mage::getModel('sales/order')->load($old->getId())->isCanceled())->toBeFalse();
});

it('cancels the old order of an order edit and links it to the new order', function () {
    $this->quotes = [$first = orderCreatePlacementQuote(), $second = orderCreatePlacementQuote()];
    $old = orderCreatePlacementModel($first, false)->createOrder();
    $create = orderCreatePlacementModel($second, false);
    orderCreatePlacementEdit($old);

    try {
        $new = $create->createOrder();
    } finally {
        orderCreatePlacementEdit(null);
    }

    $reloaded = Mage::getModel('sales/order')->load($old->getId());
    expect($reloaded->isCanceled())->toBeTrue()
        ->and((int) $reloaded->getRelationChildId())->toBe((int) $new->getId())
        ->and((int) $new->getRelationParentId())->toBe((int) $old->getId())
        ->and($new->getIncrementId())->toBe($old->getIncrementId() . '-1');
});
