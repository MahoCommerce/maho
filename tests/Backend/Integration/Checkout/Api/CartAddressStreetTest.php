<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartService;
use Mage\Sales\Api\OrderService;

uses(Tests\MahoBackendTestCase::class);

/**
 * Every REST and GraphQL cart address write lands in CartService, which applies
 * the payload with addData(). addData() bypasses the setStreet() magic setter,
 * so the street array reached the flat column as the literal string 'Array'
 * (issue #1327). The response looked correct, because it reads the in-memory
 * model before the column round-trip.
 */
function cartApiAddressInput(): array
{
    return [
        'firstName' => 'Street',
        'lastName' => 'Tester',
        'street' => ['1 Test Street', 'Apt 7'],
        'city' => 'Los Angeles',
        'region' => 'California',
        'postcode' => '90210',
        'countryId' => 'US',
        'telephone' => '5125550100',
    ];
}

function cartApiQuoteStreet(int $quoteId, string $addressType): mixed
{
    $core = Mage::getSingleton('core/resource');
    $adapter = $core->getConnection('core_read');

    return $adapter->fetchOne(
        $adapter->select()
            ->from($core->getTableName('sales/quote_address'), ['street'])
            ->where('quote_id = ?', $quoteId)
            ->where('address_type = ?', $addressType),
    );
}

function cartApiOrderStreet(int $addressId): mixed
{
    $core = Mage::getSingleton('core/resource');
    $adapter = $core->getConnection('core_read');

    return $adapter->fetchOne(
        $adapter->select()
            ->from($core->getTableName('sales/order_address'), ['street'])
            ->where('entity_id = ?', $addressId),
    );
}

// tests/Pest.php on this branch carries no cart fixtures, so the placeable
// quote is built here.
function cartApiPricedProduct(): Mage_Catalog_Model_Product
{
    $productId = Mage::getResourceModel('catalog/product_collection')
        ->addWebsiteFilter([1])
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('price', ['gteq' => 10])
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    if (!$productId) {
        test()->markTestSkipped('No priced simple product available');
    }

    return Mage::getModel('catalog/product')->setStoreId(1)->load($productId);
}

function cartApiPlaceableQuote(Mage_Catalog_Model_Product $product, int $qty = 2): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->setIsActive(true);
    $quote->addProduct($product, $qty);

    foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
        $address->setCountryId('US')
            ->setRegionId(12)
            ->setPostcode('90210')
            ->setFirstname('Test')
            ->setLastname('Customer')
            ->setStreet('123 Test St')
            ->setCity('Beverly Hills')
            ->setTelephone('555-1234')
            ->setEmail('street-lines@example.com');
    }
    $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
    $quote->getPayment()->importData(['method' => 'checkmo']);

    $quote->collectTotals();
    $quote->save();

    return $quote;
}

describe('cart API street lines', function (): void {

    it('stores every line of a shipping address', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        try {
            $service = new CartService();
            $service->setShippingAddress($quote, $service->mapAddressInput(cartApiAddressInput()));

            expect(cartApiQuoteStreet((int) $quote->getId(), 'shipping'))->toBe("1 Test Street\nApt 7");

            $reloaded = Mage::getModel('sales/quote')->load($quote->getId());
            expect($reloaded->getShippingAddress()->getStreet())->toBe(['1 Test Street', 'Apt 7']);
        } finally {
            $quote->delete();
        }
    });

    it('stores every line of a billing address', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        try {
            $service = new CartService();
            $service->setBillingAddress($quote, $service->mapAddressInput(cartApiAddressInput()));

            expect(cartApiQuoteStreet((int) $quote->getId(), 'billing'))->toBe("1 Test Street\nApt 7");

            $reloaded = Mage::getModel('sales/quote')->load($quote->getId());
            expect($reloaded->getBillingAddress()->getStreet())->toBe(['1 Test Street', 'Apt 7']);
        } finally {
            $quote->delete();
        }
    });

    it('copies every line when the billing address is the same as shipping', function (): void {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->save();

        try {
            $service = new CartService();
            $service->setShippingAddress($quote, $service->mapAddressInput(cartApiAddressInput()));
            $service->setBillingAddress($quote, [], true);

            expect(cartApiQuoteStreet((int) $quote->getId(), 'billing'))->toBe("1 Test Street\nApt 7");
        } finally {
            $quote->delete();
        }
    });

    it('carries every line onto the order addresses', function (): void {
        $product = cartApiPricedProduct();
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        $stockQty = (float) $stock->getQty();
        $stockIsIn = (int) $stock->getIsInStock();

        try {
            $quote = cartApiPlaceableQuote($product, 1);

            $service = new CartService();
            $service->setShippingAddress($quote, $service->mapAddressInput(cartApiAddressInput()));
            $service->setBillingAddress($quote, [], true);

            // Place-order is its own request, so it reads the address back from
            // the column instead of reusing the model the write left in memory.
            $placed = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            if (!$placed->getShippingAddress()->getShippingMethod()) {
                test()->markTestSkipped('Flat rate shipping not available in this environment');
            }

            $order = (new OrderService())->placeAdminOrder($placed)['order'];

            foreach ([$order->getShippingAddress(), $order->getBillingAddress()] as $address) {
                expect(cartApiOrderStreet((int) $address->getId()))->toBe("1 Test Street\nApt 7");
                expect($address->getStreet())->toBe(['1 Test Street', 'Apt 7']);
            }
        } finally {
            $stock->setQty($stockQty)->setIsInStock($stockIsIn)->save();
        }
    });

});
