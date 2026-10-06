<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Sales\Api\OrderService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

uses(Tests\MahoBackendTestCase::class);

/**
 * placeAdminOrder() throws the exception of a failed check unchanged, so the API returns its status and message.
 */
describe('OrderService validation messages', function (): void {

    $createQuote = function (string $postcode): Mage_Sales_Model_Quote {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->setPageSize(1)
            ->getFirstItem();
        if (!$product->getId()) {
            test()->markTestSkipped('No simple product available for testing');
        }

        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->addProduct(Mage::getModel('catalog/product')->load($product->getId()), 1);

        $addressData = [
            'country_id' => 'US',
            'region_id' => 12,
            'postcode' => $postcode,
            'firstname' => 'Pest',
            'lastname' => 'Validation',
            'street' => '123 Test St',
            'city' => 'Beverly Hills',
            'telephone' => '555-1234',
            'email' => 'order-validation@example.com',
        ];
        $quote->getBillingAddress()->addData($addressData);
        $quote->getShippingAddress()->addData($addressData)
            ->setCollectShippingRates(true)
            ->setShippingMethod('flatrate_flatrate');
        $quote->setCustomerIsGuest(true)->setCustomerEmail('order-validation@example.com');
        $quote->getPayment()->setMethod('cashondelivery');
        $quote->setIsActive(true);
        $quote->collectTotals()->save();

        return $quote;
    };

    it('throws the Mage_Core_Exception of the quote validation unchanged', function () use ($createQuote): void {
        $quote = $createQuote('');

        try {
            expect(fn() => new OrderService()->placeAdminOrder($quote))
                ->toThrow(Mage_Core_Exception::class, 'Please enter the zip/postal code.');

            $reloaded = Mage::getModel('sales/quote')->load($quote->getId());
            expect((int) $reloaded->getIsActive())->toBe(1);
        } finally {
            $quote->delete();
        }
    });

    it('throws the BadRequestHttpException of the gift card check unchanged', function () use ($createQuote): void {
        $quote = $createQuote('90210');
        $quote->setData('giftcard_codes', Mage::helper('core')->jsonEncode(['PEST-MISSING-CARD' => 10.0]));

        try {
            expect(fn() => new OrderService()->placeAdminOrder($quote))
                ->toThrow(BadRequestHttpException::class, 'Gift card "PEST-MISSING-CARD" is no longer valid');
        } finally {
            $quote->delete();
        }
    });

});
