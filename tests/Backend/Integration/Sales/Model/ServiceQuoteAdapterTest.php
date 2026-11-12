<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The model sales/service_quote places the order through Mage_Sales_Service_Order::submitWithoutNotice(), and keeps the parts of
 * its contract that an extension uses: a subclass can keep the cart active, and can add a check to _validate().
 */
class ServiceQuoteAdapterTestKeepsCart extends Mage_Sales_Model_Service_Quote
{
    #[\Override]
    protected $_shouldInactivateQuote = false;
}

class ServiceQuoteAdapterTestRefuses extends Mage_Sales_Model_Service_Quote
{
    #[\Override]
    protected function _validate()
    {
        throw new Mage_Core_Exception('The extension refuses this cart.');
    }
}

beforeEach(function (): void {
    $this->quote = createPlaceableQuote(loadSimplePricedProduct(), 1);
});

afterEach(function (): void {
    $order = Mage::getModel('sales/order')->load($this->quote->getId(), 'quote_id');
    if ($order->getId()) {
        Mage::register('isSecureArea', true, true);
        $order->delete();
        Mage::unregister('isSecureArea');
    }
    $this->quote->delete();
});

it('closes the cart of the order', function (): void {
    $order = new Mage_Sales_Model_Service_Quote($this->quote)->submitOrder();

    expect($order->getId())->not->toBeNull()
        ->and((bool) Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId())->getIsActive())->toBeFalse();
});

it('does not dispatch checkout_submit_all_after, because its caller does', function (): void {
    $listener = new class {
        public int $calls = 0;

        public function record(Maho\Event\Observer $observer): void
        {
            $this->calls++;
        }
    };
    Mage::register('_singleton/service_quote_adapter_listener', $listener, true);
    (function (): void {
        $this->_events['global']['checkout_submit_all_after']['observers']['service_quote_adapter_listener']
            = ['type' => 'singleton', 'model' => 'service_quote_adapter_listener', 'method' => 'record'];
    })->call(Mage::app());

    try {
        $order = new Mage_Sales_Model_Service_Quote($this->quote)->submitOrder();
    } finally {
        (function (): void {
            unset($this->_events['global']['checkout_submit_all_after']['observers']['service_quote_adapter_listener']);
        })->call(Mage::app());
        Mage::unregister('_singleton/service_quote_adapter_listener');
    }

    expect($order->getId())->not->toBeNull()
        ->and($listener->calls)->toBe(0);
});

it('keeps the cart active when a subclass sets _shouldInactivateQuote to false', function (): void {
    $order = new ServiceQuoteAdapterTestKeepsCart($this->quote)->submitOrder();

    expect($order->getId())->not->toBeNull()
        ->and((bool) $this->quote->getIsActive())->toBeTrue()
        ->and((bool) Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId())->getIsActive())->toBeTrue();
});

it('runs the check that a subclass adds to _validate() before it places the order', function (): void {
    expect(fn() => new ServiceQuoteAdapterTestRefuses($this->quote)->submitOrder())
        ->toThrow(Mage_Core_Exception::class, 'The extension refuses this cart.');

    expect(Mage::getModel('sales/order')->load($this->quote->getId(), 'quote_id')->getId())->toBeNull();
});
