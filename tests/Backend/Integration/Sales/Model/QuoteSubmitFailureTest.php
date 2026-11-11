<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A cart whose save fails inside the transaction of Mage_Sales_Model_Service_Quote::submitOrder().
 */
class QuoteSubmitFailureQuote extends Mage_Sales_Model_Quote
{
    public bool $failSave = false;

    #[\Override]
    public function save()
    {
        if ($this->failSave) {
            throw new RuntimeException('The database refused the cart.');
        }
        return parent::save();
    }
}

/**
 * A cart of $customer that submitOrder() accepts, with one simple product, an address, flat rate shipping and
 * check / money order.
 */
function quoteSubmitFailureQuote(Mage_Customer_Model_Customer $customer): QuoteSubmitFailureQuote
{
    $productId = (int) Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->joinField('is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', '{{table}}.is_in_stock=1', 'inner')
        ->setOrder('entity_id', 'ASC')
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    $quote = new QuoteSubmitFailureQuote();
    $quote->setStoreId(1);
    $quote->setCustomer($customer);
    $quote->setCustomerIsNew(!$customer->getId());
    $quote->addProduct(Mage::getModel('catalog/product')->setStoreId(1)->load($productId), 1);
    foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
        $address->addData([
            'firstname' => 'Ann',
            'lastname' => 'Lee',
            'street' => '123 Test St',
            'city' => 'Beverly Hills',
            'region_id' => 12,
            'postcode' => '90210',
            'country_id' => 'US',
            'telephone' => '555-1234',
        ]);
    }
    $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate')->setCollectShippingRates(true);
    $quote->getPayment()->importData(['method' => 'checkmo']);
    $quote->collectTotals();
    $quote->failSave = true;

    return $quote;
}

function quoteSubmitFailureCustomer(): Mage_Customer_Model_Customer
{
    return Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail('submit-failure-' . uniqid() . '@example.com')
        ->setFirstname('Ann')
        ->setLastname('Lee');
}

it('keeps the ID of an existing customer when the order fails, without a customer session', function () {
    $customer = quoteSubmitFailureCustomer();
    $customer->save();
    $customerId = (int) $customer->getId();
    $quote = quoteSubmitFailureQuote($customer);

    try {
        expect(fn() => new Mage_Sales_Model_Service_Quote($quote)->submitOrder())
            ->toThrow(RuntimeException::class, 'The database refused the cart.');
        expect((int) $quote->getCustomer()->getId())->toBe($customerId);
    } finally {
        $customer->delete();
    }
});

it('removes the ID of a customer who registers in the checkout when the order fails', function () {
    $customer = quoteSubmitFailureCustomer();
    $quote = quoteSubmitFailureQuote($customer);

    expect(fn() => new Mage_Sales_Model_Service_Quote($quote)->submitOrder())
        ->toThrow(RuntimeException::class, 'The database refused the cart.');
    expect($customer->getId())->toBeNull()
        ->and(Mage::getModel('customer/customer')->setWebsiteId(1)->loadByEmail((string) $customer->getEmail())->getId())->toBeNull();
});
