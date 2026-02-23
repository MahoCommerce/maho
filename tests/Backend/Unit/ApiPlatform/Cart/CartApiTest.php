<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\ApiPlatform\ApiResource\Address;
use Maho\ApiPlatform\ApiResource\Cart;
use Maho\ApiPlatform\ApiResource\CartItem;
use Maho\ApiPlatform\ApiResource\CartPrices;

uses(Tests\MahoBackendTestCase::class);

describe('Cart DTO', function (): void {
    it('has correct default values', function (): void {
        $cart = new Cart();

        expect($cart->id)->toBeNull()
            ->and($cart->maskedId)->toBeNull()
            ->and($cart->customerId)->toBeNull()
            ->and($cart->storeId)->toBe(1)
            ->and($cart->isActive)->toBeTrue()
            ->and($cart->items)->toBe([])
            ->and($cart->prices)->toBeInstanceOf(CartPrices::class)
            ->and($cart->billingAddress)->toBeNull()
            ->and($cart->shippingAddress)->toBeNull()
            ->and($cart->availableShippingMethods)->toBe([])
            ->and($cart->selectedShippingMethod)->toBeNull()
            ->and($cart->availablePaymentMethods)->toBe([])
            ->and($cart->selectedPaymentMethod)->toBeNull()
            ->and($cart->appliedCoupon)->toBeNull()
            ->and($cart->appliedGiftcards)->toBe([])
            ->and($cart->currency)->toBe('AUD')
            ->and($cart->itemsCount)->toBe(0)
            ->and($cart->itemsQty)->toBe(0.0)
            ->and($cart->createdAt)->toBeNull()
            ->and($cart->updatedAt)->toBeNull();
    });

    it('can be instantiated with custom values', function (): void {
        $prices = new CartPrices();
        $prices->grandTotal = 150.00;

        $cart = new Cart();
        $cart->id = 123;
        $cart->maskedId = 'abc123xyz';
        $cart->customerId = 456;
        $cart->storeId = 2;
        $cart->isActive = false;
        $cart->prices = $prices;
        $cart->currency = 'USD';
        $cart->itemsCount = 3;
        $cart->itemsQty = 5.0;
        $cart->createdAt = '2025-01-01 10:00:00';
        $cart->updatedAt = '2025-01-02 15:30:00';

        expect($cart->id)->toBe(123)
            ->and($cart->maskedId)->toBe('abc123xyz')
            ->and($cart->customerId)->toBe(456)
            ->and($cart->storeId)->toBe(2)
            ->and($cart->isActive)->toBeFalse()
            ->and($cart->prices->grandTotal)->toBe(150.00)
            ->and($cart->currency)->toBe('USD')
            ->and($cart->itemsCount)->toBe(3)
            ->and($cart->itemsQty)->toBe(5.0)
            ->and($cart->createdAt)->toBe('2025-01-01 10:00:00')
            ->and($cart->updatedAt)->toBe('2025-01-02 15:30:00');
    });
});

describe('CartPrices DTO', function (): void {
    it('has correct default values', function (): void {
        $prices = new CartPrices();

        expect($prices->subtotal)->toBe(0.0)
            ->and($prices->subtotalInclTax)->toBe(0.0)
            ->and($prices->subtotalWithDiscount)->toBe(0.0)
            ->and($prices->discountAmount)->toBeNull()
            ->and($prices->shippingAmount)->toBeNull()
            ->and($prices->shippingAmountInclTax)->toBeNull()
            ->and($prices->taxAmount)->toBe(0.0)
            ->and($prices->grandTotal)->toBe(0.0)
            ->and($prices->giftcardAmount)->toBeNull();
    });

    it('can be instantiated with custom values', function (): void {
        $prices = new CartPrices();
        $prices->subtotal = 100.00;
        $prices->subtotalInclTax = 110.00;
        $prices->subtotalWithDiscount = 90.00;
        $prices->discountAmount = 10.00;
        $prices->shippingAmount = 15.00;
        $prices->shippingAmountInclTax = 16.50;
        $prices->taxAmount = 10.00;
        $prices->grandTotal = 116.50;
        $prices->giftcardAmount = 20.00;

        expect($prices->subtotal)->toBe(100.00)
            ->and($prices->subtotalInclTax)->toBe(110.00)
            ->and($prices->subtotalWithDiscount)->toBe(90.00)
            ->and($prices->discountAmount)->toBe(10.00)
            ->and($prices->shippingAmount)->toBe(15.00)
            ->and($prices->shippingAmountInclTax)->toBe(16.50)
            ->and($prices->taxAmount)->toBe(10.00)
            ->and($prices->grandTotal)->toBe(116.50)
            ->and($prices->giftcardAmount)->toBe(20.00);
    });
});

describe('CartItem DTO', function (): void {
    it('has correct default values', function (): void {
        $item = new CartItem();

        expect($item->id)->toBeNull()
            ->and($item->sku)->toBe('')
            ->and($item->name)->toBe('')
            ->and($item->qty)->toBe(0.0)
            ->and($item->price)->toBe(0.0)
            ->and($item->priceInclTax)->toBe(0.0)
            ->and($item->rowTotal)->toBe(0.0)
            ->and($item->rowTotalInclTax)->toBe(0.0)
            ->and($item->rowTotalWithDiscount)->toBe(0.0)
            ->and($item->discountAmount)->toBeNull()
            ->and($item->discountPercent)->toBeNull()
            ->and($item->taxAmount)->toBeNull()
            ->and($item->taxPercent)->toBeNull()
            ->and($item->productId)->toBeNull()
            ->and($item->productType)->toBeNull()
            ->and($item->thumbnailUrl)->toBeNull()
            ->and($item->fulfillmentType)->toBe('SHIP');
    });

    it('can be instantiated with custom values', function (): void {
        $item = new CartItem();
        $item->id = 789;
        $item->sku = 'TEST-SKU-001';
        $item->name = 'Test Product';
        $item->qty = 2.0;
        $item->price = 50.00;
        $item->priceInclTax = 55.00;
        $item->rowTotal = 100.00;
        $item->rowTotalInclTax = 110.00;
        $item->rowTotalWithDiscount = 90.00;
        $item->discountAmount = 10.00;
        $item->discountPercent = 10.0;
        $item->taxAmount = 10.00;
        $item->taxPercent = 10.0;
        $item->productId = 999;
        $item->productType = 'simple';
        $item->thumbnailUrl = 'https://example.com/thumb.jpg';
        $item->fulfillmentType = 'PICKUP';

        expect($item->id)->toBe(789)
            ->and($item->sku)->toBe('TEST-SKU-001')
            ->and($item->name)->toBe('Test Product')
            ->and($item->qty)->toBe(2.0)
            ->and($item->price)->toBe(50.00)
            ->and($item->priceInclTax)->toBe(55.00)
            ->and($item->rowTotal)->toBe(100.00)
            ->and($item->rowTotalInclTax)->toBe(110.00)
            ->and($item->rowTotalWithDiscount)->toBe(90.00)
            ->and($item->discountAmount)->toBe(10.00)
            ->and($item->discountPercent)->toBe(10.0)
            ->and($item->taxAmount)->toBe(10.00)
            ->and($item->taxPercent)->toBe(10.0)
            ->and($item->productId)->toBe(999)
            ->and($item->productType)->toBe('simple')
            ->and($item->thumbnailUrl)->toBe('https://example.com/thumb.jpg')
            ->and($item->fulfillmentType)->toBe('PICKUP');
    });
});

describe('Cart - items management', function (): void {
    it('can add cart items to cart', function (): void {
        $cart = new Cart();

        $item1 = new CartItem();
        $item1->id = 1;
        $item1->sku = 'PROD-001';
        $item1->name = 'Product 1';
        $item1->qty = 2.0;
        $item1->price = 25.00;
        $item1->rowTotal = 50.00;

        $item2 = new CartItem();
        $item2->id = 2;
        $item2->sku = 'PROD-002';
        $item2->name = 'Product 2';
        $item2->qty = 1.0;
        $item2->price = 75.00;
        $item2->rowTotal = 75.00;

        $cart->items = [$item1, $item2];
        $cart->itemsCount = 2;
        $cart->itemsQty = 3.0;

        expect($cart->items)->toHaveCount(2)
            ->and($cart->items[0])->toBeInstanceOf(CartItem::class)
            ->and($cart->items[0]->sku)->toBe('PROD-001')
            ->and($cart->items[1])->toBeInstanceOf(CartItem::class)
            ->and($cart->items[1]->sku)->toBe('PROD-002')
            ->and($cart->itemsCount)->toBe(2)
            ->and($cart->itemsQty)->toBe(3.0);
    });

    it('maintains cart items structure integrity', function (): void {
        $cart = new Cart();

        $item = new CartItem();
        $item->id = 100;
        $item->sku = 'SKU-TEST';
        $item->name = 'Test Item';
        $item->qty = 5.0;
        $item->price = 10.00;
        $item->priceInclTax = 11.00;
        $item->rowTotal = 50.00;
        $item->rowTotalInclTax = 55.00;
        $item->taxAmount = 5.00;
        $item->productId = 200;

        $cart->items = [$item];
        $cart->itemsCount = 1;
        $cart->itemsQty = 5.0;

        // Update cart prices to match item
        $cart->prices->subtotal = 50.00;
        $cart->prices->subtotalInclTax = 55.00;
        $cart->prices->taxAmount = 5.00;
        $cart->prices->grandTotal = 55.00;

        expect($cart->items[0]->id)->toBe(100)
            ->and($cart->items[0]->sku)->toBe('SKU-TEST')
            ->and($cart->items[0]->qty)->toBe(5.0)
            ->and($cart->items[0]->rowTotal)->toBe(50.00)
            ->and($cart->prices->subtotal)->toBe(50.00)
            ->and($cart->prices->grandTotal)->toBe(55.00);
    });
});

describe('Cart - address assignment', function (): void {
    it('can assign billing and shipping addresses', function (): void {
        $cart = new Cart();

        $billingAddress = new Address();
        $billingAddress->id = 1;
        $billingAddress->firstName = 'John';
        $billingAddress->lastName = 'Doe';
        $billingAddress->street = ['123 Main St'];
        $billingAddress->city = 'Sydney';
        $billingAddress->postcode = '2000';
        $billingAddress->countryId = 'AU';

        $shippingAddress = new Address();
        $shippingAddress->id = 2;
        $shippingAddress->firstName = 'Jane';
        $shippingAddress->lastName = 'Smith';
        $shippingAddress->street = ['456 Oak Ave'];
        $shippingAddress->city = 'Melbourne';
        $shippingAddress->postcode = '3000';
        $shippingAddress->countryId = 'AU';

        $cart->billingAddress = $billingAddress;
        $cart->shippingAddress = $shippingAddress;

        expect($cart->billingAddress)->toBeInstanceOf(Address::class)
            ->and($cart->billingAddress->firstName)->toBe('John')
            ->and($cart->billingAddress->city)->toBe('Sydney')
            ->and($cart->shippingAddress)->toBeInstanceOf(Address::class)
            ->and($cart->shippingAddress->firstName)->toBe('Jane')
            ->and($cart->shippingAddress->city)->toBe('Melbourne');
    });

    it('can have same address for billing and shipping', function (): void {
        $cart = new Cart();

        $address = new Address();
        $address->id = 1;
        $address->firstName = 'John';
        $address->lastName = 'Doe';
        $address->street = ['789 Elm St'];
        $address->city = 'Brisbane';
        $address->postcode = '4000';
        $address->countryId = 'AU';

        $cart->billingAddress = $address;
        $cart->shippingAddress = $address;

        expect($cart->billingAddress)->toBe($cart->shippingAddress)
            ->and($cart->billingAddress->city)->toBe('Brisbane')
            ->and($cart->shippingAddress->city)->toBe('Brisbane');
    });
});

describe('Cart - shipping methods structure', function (): void {
    it('can store available shipping methods', function (): void {
        $cart = new Cart();

        $shippingMethods = [
            [
                'carrier_code' => 'flatrate',
                'method_code' => 'flatrate',
                'carrier_title' => 'Flat Rate',
                'method_title' => 'Fixed',
                'amount' => 10.00,
                'available' => true,
            ],
            [
                'carrier_code' => 'freeshipping',
                'method_code' => 'freeshipping',
                'carrier_title' => 'Free Shipping',
                'method_title' => 'Free',
                'amount' => 0.00,
                'available' => true,
            ],
        ];

        $cart->availableShippingMethods = $shippingMethods;

        expect($cart->availableShippingMethods)->toHaveCount(2)
            ->and($cart->availableShippingMethods[0]['carrier_code'])->toBe('flatrate')
            ->and($cart->availableShippingMethods[0]['amount'])->toBe(10.00)
            ->and($cart->availableShippingMethods[1]['carrier_code'])->toBe('freeshipping')
            ->and($cart->availableShippingMethods[1]['amount'])->toBe(0.00);
    });

    it('can store selected shipping method', function (): void {
        $cart = new Cart();

        $selectedMethod = [
            'carrier_code' => 'flatrate',
            'method_code' => 'flatrate',
            'carrier_title' => 'Flat Rate',
            'method_title' => 'Fixed',
            'amount' => 10.00,
        ];

        $cart->selectedShippingMethod = $selectedMethod;
        $cart->prices->shippingAmount = 10.00;
        $cart->prices->shippingAmountInclTax = 11.00;

        expect($cart->selectedShippingMethod)->not->toBeNull()
            ->and($cart->selectedShippingMethod['carrier_code'])->toBe('flatrate')
            ->and($cart->selectedShippingMethod['amount'])->toBe(10.00)
            ->and($cart->prices->shippingAmount)->toBe(10.00);
    });

    it('can store available payment methods', function (): void {
        $cart = new Cart();

        $paymentMethods = [
            [
                'code' => 'checkmo',
                'title' => 'Check / Money order',
            ],
            [
                'code' => 'cashondelivery',
                'title' => 'Cash On Delivery',
            ],
        ];

        $cart->availablePaymentMethods = $paymentMethods;

        expect($cart->availablePaymentMethods)->toHaveCount(2)
            ->and($cart->availablePaymentMethods[0]['code'])->toBe('checkmo')
            ->and($cart->availablePaymentMethods[1]['code'])->toBe('cashondelivery');
    });

    it('can store selected payment method', function (): void {
        $cart = new Cart();

        $selectedPayment = [
            'code' => 'checkmo',
            'title' => 'Check / Money order',
        ];

        $cart->selectedPaymentMethod = $selectedPayment;

        expect($cart->selectedPaymentMethod)->not->toBeNull()
            ->and($cart->selectedPaymentMethod['code'])->toBe('checkmo')
            ->and($cart->selectedPaymentMethod['title'])->toBe('Check / Money order');
    });
});

describe('Cart - coupon and giftcard management', function (): void {
    it('can apply a coupon code', function (): void {
        $cart = new Cart();

        $coupon = [
            'code' => 'SAVE10',
            'discount_amount' => 10.00,
        ];

        $cart->appliedCoupon = $coupon;
        $cart->prices->discountAmount = 10.00;

        expect($cart->appliedCoupon)->not->toBeNull()
            ->and($cart->appliedCoupon['code'])->toBe('SAVE10')
            ->and($cart->appliedCoupon['discount_amount'])->toBe(10.00)
            ->and($cart->prices->discountAmount)->toBe(10.00);
    });

    it('can apply multiple giftcards', function (): void {
        $cart = new Cart();

        $giftcards = [
            [
                'code' => 'GIFT-001',
                'amount' => 25.00,
            ],
            [
                'code' => 'GIFT-002',
                'amount' => 15.00,
            ],
        ];

        $cart->appliedGiftcards = $giftcards;
        $cart->prices->giftcardAmount = 40.00;

        expect($cart->appliedGiftcards)->toHaveCount(2)
            ->and($cart->appliedGiftcards[0]['code'])->toBe('GIFT-001')
            ->and($cart->appliedGiftcards[0]['amount'])->toBe(25.00)
            ->and($cart->appliedGiftcards[1]['code'])->toBe('GIFT-002')
            ->and($cart->appliedGiftcards[1]['amount'])->toBe(15.00)
            ->and($cart->prices->giftcardAmount)->toBe(40.00);
    });
});

describe('Cart - complete scenario', function (): void {
    it('can build a complete cart with all components', function (): void {
        $cart = new Cart();
        $cart->id = 1;
        $cart->customerId = 123;
        $cart->storeId = 1;
        $cart->currency = 'AUD';

        // Add items
        $item1 = new CartItem();
        $item1->id = 1;
        $item1->sku = 'RACKET-001';
        $item1->name = 'Tennis Racket';
        $item1->qty = 1.0;
        $item1->price = 150.00;
        $item1->priceInclTax = 165.00;
        $item1->rowTotal = 150.00;
        $item1->rowTotalInclTax = 165.00;
        $item1->taxAmount = 15.00;
        $item1->productId = 501;

        $item2 = new CartItem();
        $item2->id = 2;
        $item2->sku = 'BALLS-001';
        $item2->name = 'Tennis Balls (3-pack)';
        $item2->qty = 2.0;
        $item2->price = 10.00;
        $item2->priceInclTax = 11.00;
        $item2->rowTotal = 20.00;
        $item2->rowTotalInclTax = 22.00;
        $item2->taxAmount = 2.00;
        $item2->productId = 502;

        $cart->items = [$item1, $item2];
        $cart->itemsCount = 2;
        $cart->itemsQty = 3.0;

        // Add addresses
        $billingAddress = new Address();
        $billingAddress->firstName = 'John';
        $billingAddress->lastName = 'Tennis';
        $billingAddress->street = ['123 Court St'];
        $billingAddress->city = 'Sydney';
        $billingAddress->postcode = '2000';
        $billingAddress->countryId = 'AU';
        $billingAddress->telephone = '0412345678';

        $shippingAddress = new Address();
        $shippingAddress->firstName = 'John';
        $shippingAddress->lastName = 'Tennis';
        $shippingAddress->street = ['123 Court St'];
        $shippingAddress->city = 'Sydney';
        $shippingAddress->postcode = '2000';
        $shippingAddress->countryId = 'AU';
        $shippingAddress->telephone = '0412345678';

        $cart->billingAddress = $billingAddress;
        $cart->shippingAddress = $shippingAddress;

        // Add shipping method
        $cart->selectedShippingMethod = [
            'carrier_code' => 'flatrate',
            'method_code' => 'flatrate',
            'amount' => 10.00,
        ];

        // Add payment method
        $cart->selectedPaymentMethod = [
            'code' => 'checkmo',
            'title' => 'Check / Money order',
        ];

        // Calculate prices
        $cart->prices->subtotal = 170.00;
        $cart->prices->subtotalInclTax = 187.00;
        $cart->prices->taxAmount = 17.00;
        $cart->prices->shippingAmount = 10.00;
        $cart->prices->shippingAmountInclTax = 11.00;
        $cart->prices->grandTotal = 198.00;

        // Add coupon
        $cart->appliedCoupon = [
            'code' => 'TENNIS10',
            'discount_amount' => 17.00,
        ];
        $cart->prices->discountAmount = 17.00;
        $cart->prices->subtotalWithDiscount = 153.00;
        $cart->prices->grandTotal = 181.00; // After discount

        expect($cart->id)->toBe(1)
            ->and($cart->items)->toHaveCount(2)
            ->and($cart->itemsCount)->toBe(2)
            ->and($cart->itemsQty)->toBe(3.0)
            ->and($cart->billingAddress)->toBeInstanceOf(Address::class)
            ->and($cart->shippingAddress)->toBeInstanceOf(Address::class)
            ->and($cart->selectedShippingMethod)->not->toBeNull()
            ->and($cart->selectedPaymentMethod)->not->toBeNull()
            ->and($cart->appliedCoupon)->not->toBeNull()
            ->and($cart->prices->subtotal)->toBe(170.00)
            ->and($cart->prices->discountAmount)->toBe(17.00)
            ->and($cart->prices->grandTotal)->toBe(181.00);
    });
});
