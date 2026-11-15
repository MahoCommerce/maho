<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Sales_Service_Order::submit() adds the addresses of an order to the address book of its customer, for the
 * storefront, the admin and the API (#1348).
 */

function addressBookProduct(): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-address-book-' . uniqid())->setName('Pest address book product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $product->save();

    return Mage::getModel('catalog/product')->load($product->getId());
}

function addressBookCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)->setStoreId(1)->setGroupId(1)
        ->setFirstname('Address')->setLastname('Book')
        ->setEmail('pest.address.book.' . uniqid() . '@example.com');
    $customer->save();

    return Mage::getModel('customer/customer')->load($customer->getId());
}

function addressBookSavedAddress(Mage_Customer_Model_Customer $customer, string $street): Mage_Customer_Model_Address
{
    $address = Mage::getModel('customer/address')->setData([
        'parent_id' => $customer->getId(), 'firstname' => 'Saved', 'lastname' => 'Address', 'street' => $street,
        'city' => 'Beverly Hills', 'region_id' => 12, 'postcode' => '90210', 'country_id' => 'US', 'telephone' => '555-1234',
        'is_default_billing' => true, 'is_default_shipping' => true,
    ])->setCustomer($customer);
    $address->save();

    return $address;
}

function addressBookQuote(Mage_Catalog_Model_Product $product, Mage_Customer_Model_Customer $customer): Mage_Sales_Model_Quote
{
    $quote = createPlaceableQuote($product, 1);
    $quote->setCustomer($customer);
    $quote->getShippingAddress()->setSameAsBilling(false);

    return $quote;
}

function addressBookPlace(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Order
{
    $quote->collectTotals()->save();
    $order = Mage::getService('sales/order')->place($quote)['order'];
    $orderIds = &addressBookOrderIds();
    $orderIds[] = (int) $order->getId();

    return $order;
}

/**
 * @return list<int>
 */
function &addressBookOrderIds(): array
{
    static $orderIds = [];
    return $orderIds;
}

/**
 * @return list<string> the first street line of each address of the address book of $customer
 */
function addressBookStreets(Mage_Customer_Model_Customer $customer): array
{
    $streets = [];
    foreach (Mage::getModel('customer/customer')->load($customer->getId())->getAddressesCollection() as $address) {
        $streets[] = (string) $address->getStreet(1);
    }
    sort($streets);

    return $streets;
}

beforeEach(function (): void {
    Mage::app()->setCurrentStore(1);
    $this->product = addressBookProduct();
    $this->customer = addressBookCustomer();
});

afterEach(function (): void {
    Mage::register('isSecureArea', true, true);
    $orderIds = &addressBookOrderIds();
    foreach ($orderIds as $orderId) {
        Mage::getModel('sales/order')->load($orderId)->delete();
    }
    $orderIds = [];
    $this->customer->delete();
    $this->product->delete();
    Mage::unregister('isSecureArea');
    Mage::app()->setCurrentStore(0);
});

it('adds no address that the client did not ask to save', function (): void {
    addressBookPlace(addressBookQuote($this->product, $this->customer));

    expect(addressBookStreets($this->customer))->toBe([]);
});

it('adds the billing address once when it is also the shipping address, as the default of both', function (): void {
    $quote = addressBookQuote($this->product, $this->customer);
    $quote->getBillingAddress()->setSaveInAddressBook()->setStreet('1 Billing St');
    $quote->getShippingAddress()->setSameAsBilling(true)->setSaveInAddressBook()->setStreet('1 Billing St');
    $order = addressBookPlace($quote);

    $customer = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect(addressBookStreets($customer))->toBe(['1 Billing St'])
        ->and((int) $customer->getDefaultBilling())->toBeGreaterThan(0)
        ->and($customer->getDefaultShipping())->toBe($customer->getDefaultBilling())
        ->and((int) $order->getBillingAddress()->getCustomerAddressId())->toBe((int) $customer->getDefaultBilling());
});

it('adds a billing and a shipping address that are not the same, each as its default', function (): void {
    $quote = addressBookQuote($this->product, $this->customer);
    $quote->getBillingAddress()->setSaveInAddressBook()->setStreet('2 Billing St');
    $quote->getShippingAddress()->setSaveInAddressBook()->setStreet('2 Shipping St');
    addressBookPlace($quote);

    $customer = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect(addressBookStreets($customer))->toBe(['2 Billing St', '2 Shipping St'])
        ->and((string) $customer->getDefaultBillingAddress()->getStreet(1))->toBe('2 Billing St')
        ->and((string) $customer->getDefaultShippingAddress()->getStreet(1))->toBe('2 Shipping St');
});

it('keeps the billing address out of the default shipping address when the shipping address is not saved', function (): void {
    $quote = addressBookQuote($this->product, $this->customer);
    $quote->getBillingAddress()->setSaveInAddressBook()->setStreet('3 Billing St');
    $quote->getShippingAddress()->setStreet('3 Shipping St');
    addressBookPlace($quote);

    $customer = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect(addressBookStreets($customer))->toBe(['3 Billing St'])
        ->and($customer->getDefaultShipping())->toBeNull();
});

it('updates the saved address that the client picked, and keeps the defaults of the customer', function (): void {
    $saved = addressBookSavedAddress($this->customer, '4 Old St');
    $customer = Mage::getModel('customer/customer')->load($this->customer->getId());
    $quote = addressBookQuote($this->product, $customer);
    $quote->getBillingAddress()->setCustomerAddressId((int) $saved->getId())->setSaveInAddressBook()->setStreet('4 New St');
    $quote->getShippingAddress()->setSaveInAddressBook()->setStreet('4 Shipping St');
    addressBookPlace($quote);

    $customer = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect(addressBookStreets($customer))->toBe(['4 New St', '4 Shipping St'])
        ->and((int) $customer->getDefaultBilling())->toBe((int) $saved->getId())
        ->and((int) $customer->getDefaultShipping())->toBe((int) $saved->getId());
});

it('does not add again an address that the caller already added', function (): void {
    $quote = addressBookQuote($this->product, $this->customer);
    $billing = $quote->getBillingAddress()->setSaveInAddressBook()->setStreet('5 Billing St');
    $customerAddress = $billing->exportCustomerAddress();
    $quote->getCustomer()->addAddress($customerAddress);
    $billing->setCustomerAddress($customerAddress);
    addressBookPlace($quote);

    expect(addressBookStreets($this->customer))->toBe(['5 Billing St']);
});
