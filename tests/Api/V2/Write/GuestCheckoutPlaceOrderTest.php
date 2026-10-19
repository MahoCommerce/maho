<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 place-order of guest carts (WRITE)
 *
 * @group write
 */

const GUEST_PLACE_ADDRESS = [
    'firstName' => 'Pest', 'lastName' => 'Guest', 'street' => ['1 Test Street'], 'city' => 'Los Angeles',
    'region' => 'California', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
];

function guestPlaceSetGuestCheckout(?string $value): void
{
    $config = Mage::getModel('core/config');
    if ($value === null) {
        $config->deleteConfig('checkout/options/guest_checkout', 'default', 0);
    } else {
        $config->saveConfig('checkout/options/guest_checkout', $value, 'default', 0);
    }
    Mage::app()->getCache()->cleanType('config');
    Mage::app()->reinitStores();
}

function guestPlaceCart(string $sku): array
{
    $maskedId = apiPost('/api/rest/v2/guest-carts', [])['json']['maskedId'];
    $added = apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", ['sku' => $sku, 'qty' => 1]);
    expect($added['status'])->toBeIn([200, 201]);

    $quoteId = (int) Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne(
        'SELECT entity_id FROM ' . Mage::getSingleton('core/resource')->getTableName('sales/quote') . ' WHERE masked_quote_id = ?',
        [$maskedId],
    );
    trackCreated('quote', $quoteId);

    return [$maskedId, $quoteId];
}

function guestPlaceBody(string $email = 'pest-guest-place@example.com'): array
{
    return [
        'shippingAddress' => GUEST_PLACE_ADDRESS,
        'billingAddress' => GUEST_PLACE_ADDRESS,
        'guestEmail' => $email,
        'paymentMethod' => 'checkmo',
        'shippingMethod' => 'flatrate_flatrate',
    ];
}

beforeAll(function (): void {
    Mage::app();
});

beforeEach(function (): void {
    $sku = 'pest-guest-place-' . uniqid();
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)->setName('Pest guest place product')->setUrlKey($sku)->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $product->save();
    trackCreated('product', (int) $product->getId());
    $this->sku = $sku;
    $this->orderIds = [];
    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $stored = $read->fetchOne(
        $read->select()->from(Mage::getSingleton('core/resource')->getTableName('core/config_data'), 'value')
            ->where('path = ?', 'checkout/options/guest_checkout')->where('scope = ?', 'default')->where('scope_id = ?', 0),
    );
    $this->guestCheckout = $stored === false ? null : (string) $stored;
    guestPlaceSetGuestCheckout('0');
});

afterEach(function (): void {
    foreach ($this->orderIds as $orderId) {
        Mage::register('isSecureArea', true, true);
        Mage::getModel('sales/order')->load($orderId)->delete();
        Mage::unregister('isSecureArea');
    }
    guestPlaceSetGuestCheckout($this->guestCheckout);
    cleanupTestData();
});

it('refuses a guest order with 403 when the store does not allow guest checkout', function (): void {
    [$maskedId] = guestPlaceCart($this->sku);

    $response = apiPost("/api/rest/v2/guest-carts/{$maskedId}/place-order", guestPlaceBody());

    expect($response['status'])->toBe(403)
        ->and($response['json']['error'] ?? null)->toBe('forbidden');
});

it('places a cart assigned to a customer while it still has the guest flag as a customer order', function (): void {
    [$maskedId, $quoteId] = guestPlaceCart($this->sku);
    $customerId = (int) fixtures('customer_id');
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('sales/quote'),
        ['customer_id' => $customerId, 'customer_is_guest' => 1],
        ['entity_id = ?' => $quoteId],
    );

    $response = apiPost("/api/rest/v2/carts/{$quoteId}/place-order", guestPlaceBody('not-the-account@example.com'), customerToken());

    expect($response['status'])->toBeIn([200, 201]);
    $order = Mage::getModel('sales/order')->loadByIncrementId((string) $response['json']['incrementId']);
    $this->orderIds[] = (int) $order->getId();
    expect((bool) $order->getCustomerIsGuest())->toBeFalse()
        ->and((int) $order->getCustomerId())->toBe($customerId)
        ->and($order->getData('guest_access_token'))->toBeEmpty()
        ->and($order->getCustomerEmail())->toBe(Mage::getModel('customer/customer')->load($customerId)->getEmail());
});

it('places a customer cart with the account email, not the guestEmail of the request', function (): void {
    $customerId = (int) fixtures('customer_id');
    $token = customerToken();
    $cartId = (int) apiPost('/api/rest/v2/carts', [], $token)['json']['id'];
    trackCreated('quote', $cartId);
    expect(apiPost("/api/rest/v2/carts/{$cartId}/items", ['sku' => $this->sku, 'qty' => 1], $token)['status'])->toBeIn([200, 201]);

    $response = apiPost("/api/rest/v2/carts/{$cartId}/place-order", guestPlaceBody('not-the-account@example.com'), $token);

    expect($response['status'])->toBeIn([200, 201]);
    $order = Mage::getModel('sales/order')->loadByIncrementId((string) $response['json']['incrementId']);
    $this->orderIds[] = (int) $order->getId();
    expect((int) $order->getCustomerId())->toBe($customerId)
        ->and($order->getCustomerEmail())->toBe(Mage::getModel('customer/customer')->load($customerId)->getEmail());
});

it('places a customer cart with the account email that the customer changed after the cart was made', function (): void {
    $customer = Mage::getModel('customer/customer')->setWebsiteId(1)->setStoreId(1)->setGroupId(1)->setIsActive(1)
        ->setFirstname('Pest')->setLastname('Email')->setEmail('pest-email-before-' . uniqid() . '@example.com');
    $customer->save();
    // A store that asks new accounts to confirm their email gives them a confirmation key, and the API refuses those
    $customer->setConfirmation(null)->save();
    $token = customerToken((int) $customer->getId());

    try {
        $cartId = (int) apiPost('/api/rest/v2/carts', [], $token)['json']['id'];
        trackCreated('quote', $cartId);
        expect(apiPost("/api/rest/v2/carts/{$cartId}/items", ['sku' => $this->sku, 'qty' => 1], $token)['status'])->toBeIn([200, 201]);
        $newEmail = 'pest-email-after-' . uniqid() . '@example.com';
        $customer->setEmail($newEmail)->save();

        $response = apiPost("/api/rest/v2/carts/{$cartId}/place-order", guestPlaceBody(), $token);

        expect($response['status'])->toBeIn([200, 201]);
        $order = Mage::getModel('sales/order')->loadByIncrementId((string) $response['json']['incrementId']);
        $this->orderIds[] = (int) $order->getId();
        expect($order->getCustomerEmail())->toBe($newEmail);
    } finally {
        Mage::register('isSecureArea', true, true);
        $customer->delete();
        Mage::unregister('isSecureArea');
    }
});
