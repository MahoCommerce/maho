<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\OrderService;

uses(Tests\MahoBackendTestCase::class);

/**
 * placeAdminOrder() wrapped every exception in a RuntimeException, which the
 * API returns as a 500 "An internal error occurred". A validation failure of
 * the quote, for example a postcode in the wrong format, must reach the caller
 * as a Mage_Core_Exception, which ApiExceptionListener returns as a 422.
 */
describe('OrderService validation messages', function (): void {

    it('throws the Mage_Core_Exception of the quote validation unchanged', function (): void {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1)
            ->getFirstItem();
        if (!$product->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }

        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->addProduct(Mage::getModel('catalog/product')->load($product->getId()), 1);

        $addressData = [
            'country_id' => 'US',
            'region_id' => 12,
            'postcode' => '123456789',
            'firstname' => 'Invalid',
            'lastname' => 'Postcode',
            'street' => '123 Test St',
            'city' => 'Beverly Hills',
            'telephone' => '555-1234',
            'email' => 'invalid-postcode@example.com',
        ];
        $quote->getBillingAddress()->addData($addressData);
        $quote->getShippingAddress()->addData($addressData)
            ->setCollectShippingRates(true)
            ->setShippingMethod('flatrate_flatrate');
        $quote->setCustomerIsGuest(true)->setCustomerEmail('invalid-postcode@example.com');
        $quote->getPayment()->setMethod('cashondelivery');
        $quote->setIsActive(true);
        $quote->collectTotals()->save();

        try {
            expect(fn() => new OrderService()->placeAdminOrder($quote))
                ->toThrow(Mage_Core_Exception::class, 'Please enter a valid postcode for United States');

            $reloaded = Mage::getModel('sales/quote')->load($quote->getId());
            expect((int) $reloaded->getIsActive())->toBe(1);
        } finally {
            $quote->delete();
        }
    });

});
