<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Checkout_Service_Cart::useBillingForShipping() copies the billing address to the shipping address for the
 * storefront, the API and the admin order create (#1348).
 */

beforeEach(function () {
    $this->quote = createPlaceableQuote(loadSimplePricedProduct(), 1);
    $this->quote->getBillingAddress()->addData([
        'vat_id' => 'IT12345678901', 'vat_is_valid' => 1, 'vat_request_id' => 'REQ-1',
        'vat_request_date' => '2026-11-10 10:00:00', 'vat_request_success' => 1,
    ]);
    $this->saves = new class {
        public int $count = 0;

        public function record(): void
        {
            $this->count++;
        }
    };
    Mage::register('_singleton/billing_for_shipping_saves', $this->saves, true);
    (function (): void {
        $this->_events['global']['sales_quote_save_after']['observers']['billing_for_shipping_saves']
            = ['type' => 'singleton', 'model' => 'billing_for_shipping_saves', 'method' => 'record'];
    })->call(Mage::app());
});

afterEach(function () {
    (function (): void {
        unset($this->_events['global']['sales_quote_save_after']['observers']['billing_for_shipping_saves']);
    })->call(Mage::app());
    Mage::unregister('_singleton/billing_for_shipping_saves');
    $this->quote->delete();
});

it('copies the VAT number and the result of its check to the shipping address', function () {
    Mage::getService('checkout/cart')->useBillingForShipping($this->quote);

    $shipping = Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId())->getShippingAddress();
    expect([
        $shipping->getVatId(), (int) $shipping->getData('vat_is_valid'), $shipping->getData('vat_request_id'),
        $shipping->getData('vat_request_date'), (int) $shipping->getData('vat_request_success'),
    ])->toBe(['IT12345678901', 1, 'REQ-1', '2026-11-10 10:00:00', 1]);
});

it('does not save the cart again when the admin order create reloads with the same billing address', function () {
    $create = Mage::getModel('adminhtml/sales_order_create')->setQuote($this->quote);

    $create->setShippingAsBilling(1);
    $afterCopy = $this->saves->count;
    $create->setShippingAsBilling(1);
    $afterReload = $this->saves->count;
    $this->quote->getBillingAddress()->setCity('Pasadena');
    $create->setShippingAsBilling(1);

    expect($afterCopy)->toBe(1)
        ->and($afterReload)->toBe(1)
        ->and($this->saves->count)->toBe(2)
        ->and($this->quote->getShippingAddress()->getCity())->toBe('Pasadena');
});
