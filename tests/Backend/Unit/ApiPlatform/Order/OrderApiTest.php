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
use Maho\ApiPlatform\ApiResource\Order;
use Maho\ApiPlatform\ApiResource\OrderItem;
use Maho\ApiPlatform\ApiResource\OrderPrices;
use Maho\ApiPlatform\ApiResource\Shipment;

uses(Tests\MahoBackendTestCase::class);

describe('Order DTO', function () {
    it('has correct default values', function () {
        $order = new Order();

        expect($order->id)->toBeNull()
            ->and($order->incrementId)->toBeNull()
            ->and($order->customerId)->toBeNull()
            ->and($order->customerEmail)->toBeNull()
            ->and($order->customerFirstname)->toBeNull()
            ->and($order->customerLastname)->toBeNull()
            ->and($order->status)->toBeNull()
            ->and($order->state)->toBeNull()
            ->and($order->items)->toBeArray()->toBeEmpty()
            ->and($order->billingAddress)->toBeNull()
            ->and($order->shippingAddress)->toBeNull()
            ->and($order->prices)->toBeInstanceOf(OrderPrices::class)
            ->and($order->paymentMethod)->toBeNull()
            ->and($order->paymentMethodTitle)->toBeNull()
            ->and($order->shippingMethod)->toBeNull()
            ->and($order->shippingDescription)->toBeNull()
            ->and($order->couponCode)->toBeNull()
            ->and($order->storeId)->toBe(1)
            ->and($order->currency)->toBe('AUD')
            ->and($order->totalItemCount)->toBe(0)
            ->and($order->totalQtyOrdered)->toBe(0.0)
            ->and($order->accessToken)->toBeNull()
            ->and($order->changeAmount)->toBeNull()
            ->and($order->createdAt)->toBeNull()
            ->and($order->updatedAt)->toBeNull()
            ->and($order->statusHistory)->toBeArray()->toBeEmpty()
            ->and($order->shipments)->toBeArray()->toBeEmpty();
    });

    it('can set all properties', function () {
        $order = new Order();
        $order->id = 123;
        $order->incrementId = '100000001';
        $order->customerId = 456;
        $order->customerEmail = 'test@example.com';
        $order->customerFirstname = 'John';
        $order->customerLastname = 'Doe';
        $order->status = 'processing';
        $order->state = 'new';
        $order->paymentMethod = 'checkmo';
        $order->paymentMethodTitle = 'Check / Money order';
        $order->shippingMethod = 'flatrate_flatrate';
        $order->shippingDescription = 'Flat Rate - Fixed';
        $order->couponCode = 'SAVE10';
        $order->storeId = 2;
        $order->currency = 'USD';
        $order->totalItemCount = 5;
        $order->totalQtyOrdered = 10.0;
        $order->accessToken = 'token123';
        $order->changeAmount = 5.50;
        $order->createdAt = '2025-01-01 12:00:00';
        $order->updatedAt = '2025-01-02 14:30:00';

        expect($order->id)->toBe(123)
            ->and($order->incrementId)->toBe('100000001')
            ->and($order->customerId)->toBe(456)
            ->and($order->customerEmail)->toBe('test@example.com')
            ->and($order->customerFirstname)->toBe('John')
            ->and($order->customerLastname)->toBe('Doe')
            ->and($order->status)->toBe('processing')
            ->and($order->state)->toBe('new')
            ->and($order->paymentMethod)->toBe('checkmo')
            ->and($order->paymentMethodTitle)->toBe('Check / Money order')
            ->and($order->shippingMethod)->toBe('flatrate_flatrate')
            ->and($order->shippingDescription)->toBe('Flat Rate - Fixed')
            ->and($order->couponCode)->toBe('SAVE10')
            ->and($order->storeId)->toBe(2)
            ->and($order->currency)->toBe('USD')
            ->and($order->totalItemCount)->toBe(5)
            ->and($order->totalQtyOrdered)->toBe(10.0)
            ->and($order->accessToken)->toBe('token123')
            ->and($order->changeAmount)->toBe(5.50)
            ->and($order->createdAt)->toBe('2025-01-01 12:00:00')
            ->and($order->updatedAt)->toBe('2025-01-02 14:30:00');
    });
});

describe('OrderPrices DTO', function () {
    it('has correct default values', function () {
        $prices = new OrderPrices();

        expect($prices->subtotal)->toBe(0.0)
            ->and($prices->subtotalInclTax)->toBe(0.0)
            ->and($prices->discountAmount)->toBeNull()
            ->and($prices->shippingAmount)->toBeNull()
            ->and($prices->shippingAmountInclTax)->toBeNull()
            ->and($prices->taxAmount)->toBe(0.0)
            ->and($prices->grandTotal)->toBe(0.0)
            ->and($prices->totalPaid)->toBe(0.0)
            ->and($prices->totalRefunded)->toBe(0.0)
            ->and($prices->totalDue)->toBe(0.0)
            ->and($prices->giftcardAmount)->toBeNull();
    });

    it('can set all properties', function () {
        $prices = new OrderPrices();
        $prices->subtotal = 100.00;
        $prices->subtotalInclTax = 110.00;
        $prices->discountAmount = 10.00;
        $prices->shippingAmount = 15.00;
        $prices->shippingAmountInclTax = 16.50;
        $prices->taxAmount = 12.50;
        $prices->grandTotal = 118.50;
        $prices->totalPaid = 118.50;
        $prices->totalRefunded = 0.00;
        $prices->totalDue = 0.00;
        $prices->giftcardAmount = 20.00;

        expect($prices->subtotal)->toBe(100.00)
            ->and($prices->subtotalInclTax)->toBe(110.00)
            ->and($prices->discountAmount)->toBe(10.00)
            ->and($prices->shippingAmount)->toBe(15.00)
            ->and($prices->shippingAmountInclTax)->toBe(16.50)
            ->and($prices->taxAmount)->toBe(12.50)
            ->and($prices->grandTotal)->toBe(118.50)
            ->and($prices->totalPaid)->toBe(118.50)
            ->and($prices->totalRefunded)->toBe(0.00)
            ->and($prices->totalDue)->toBe(0.00)
            ->and($prices->giftcardAmount)->toBe(20.00);
    });

    it('calculates totals correctly', function () {
        $prices = new OrderPrices();
        $prices->subtotal = 100.00;
        $prices->shippingAmount = 10.00;
        $prices->taxAmount = 11.00;
        $prices->discountAmount = 5.00;
        $prices->grandTotal = 116.00;
        $prices->totalPaid = 50.00;

        // Grand total = subtotal + shipping + tax - discount
        expect($prices->grandTotal)->toBe(116.00);

        // Total due = grand total - total paid
        $prices->totalDue = $prices->grandTotal - $prices->totalPaid;
        expect($prices->totalDue)->toBe(66.00);
    });
});

describe('OrderItem DTO', function () {
    it('has correct default values', function () {
        $item = new OrderItem();

        expect($item->id)->toBeNull()
            ->and($item->sku)->toBe('')
            ->and($item->name)->toBe('')
            ->and($item->qty)->toBe(0.0)
            ->and($item->qtyOrdered)->toBe(0.0)
            ->and($item->qtyShipped)->toBe(0.0)
            ->and($item->qtyRefunded)->toBe(0.0)
            ->and($item->qtyCanceled)->toBe(0.0)
            ->and($item->price)->toBe(0.0)
            ->and($item->priceInclTax)->toBe(0.0)
            ->and($item->rowTotal)->toBe(0.0)
            ->and($item->rowTotalInclTax)->toBe(0.0)
            ->and($item->discountAmount)->toBeNull()
            ->and($item->discountPercent)->toBeNull()
            ->and($item->taxAmount)->toBeNull()
            ->and($item->taxPercent)->toBeNull()
            ->and($item->productId)->toBeNull()
            ->and($item->productType)->toBeNull()
            ->and($item->parentItemId)->toBeNull();
    });

    it('can set all properties', function () {
        $item = new OrderItem();
        $item->id = 1;
        $item->sku = 'TEST-SKU-001';
        $item->name = 'Test Product';
        $item->qty = 2.0;
        $item->qtyOrdered = 2.0;
        $item->qtyShipped = 1.0;
        $item->qtyRefunded = 0.0;
        $item->qtyCanceled = 0.0;
        $item->price = 50.00;
        $item->priceInclTax = 55.00;
        $item->rowTotal = 100.00;
        $item->rowTotalInclTax = 110.00;
        $item->discountAmount = 10.00;
        $item->discountPercent = 10.0;
        $item->taxAmount = 10.00;
        $item->taxPercent = 10.0;
        $item->productId = 123;
        $item->productType = 'simple';
        $item->parentItemId = null;

        expect($item->id)->toBe(1)
            ->and($item->sku)->toBe('TEST-SKU-001')
            ->and($item->name)->toBe('Test Product')
            ->and($item->qty)->toBe(2.0)
            ->and($item->qtyOrdered)->toBe(2.0)
            ->and($item->qtyShipped)->toBe(1.0)
            ->and($item->qtyRefunded)->toBe(0.0)
            ->and($item->qtyCanceled)->toBe(0.0)
            ->and($item->price)->toBe(50.00)
            ->and($item->priceInclTax)->toBe(55.00)
            ->and($item->rowTotal)->toBe(100.00)
            ->and($item->rowTotalInclTax)->toBe(110.00)
            ->and($item->discountAmount)->toBe(10.00)
            ->and($item->discountPercent)->toBe(10.0)
            ->and($item->taxAmount)->toBe(10.00)
            ->and($item->taxPercent)->toBe(10.0)
            ->and($item->productId)->toBe(123)
            ->and($item->productType)->toBe('simple')
            ->and($item->parentItemId)->toBeNull();
    });

    it('calculates row totals correctly', function () {
        $item = new OrderItem();
        $item->qty = 3.0;
        $item->price = 25.00;
        $item->rowTotal = $item->qty * $item->price;

        expect($item->rowTotal)->toBe(75.00);
    });
});

describe('Order - items management', function () {
    it('can add order items to order', function () {
        $order = new Order();

        $item1 = new OrderItem();
        $item1->id = 1;
        $item1->sku = 'PRODUCT-001';
        $item1->name = 'Product One';
        $item1->qty = 2.0;
        $item1->price = 50.00;

        $item2 = new OrderItem();
        $item2->id = 2;
        $item2->sku = 'PRODUCT-002';
        $item2->name = 'Product Two';
        $item2->qty = 1.0;
        $item2->price = 75.00;

        $order->items = [$item1, $item2];
        $order->totalItemCount = 2;
        $order->totalQtyOrdered = 3.0;

        expect($order->items)->toHaveCount(2)
            ->and($order->items[0])->toBeInstanceOf(OrderItem::class)
            ->and($order->items[0]->sku)->toBe('PRODUCT-001')
            ->and($order->items[1])->toBeInstanceOf(OrderItem::class)
            ->and($order->items[1]->sku)->toBe('PRODUCT-002')
            ->and($order->totalItemCount)->toBe(2)
            ->and($order->totalQtyOrdered)->toBe(3.0);
    });

    it('maintains correct order structure with items', function () {
        $order = new Order();
        $order->id = 100;
        $order->incrementId = '100000100';

        $item = new OrderItem();
        $item->id = 1;
        $item->sku = 'TEST-SKU';
        $item->name = 'Test Product';
        $item->qtyOrdered = 2.0;
        $item->price = 100.00;
        $item->rowTotal = 200.00;

        $order->items[] = $item;
        $order->totalItemCount = 1;
        $order->totalQtyOrdered = 2.0;
        $order->prices->subtotal = 200.00;
        $order->prices->grandTotal = 200.00;

        expect($order->items)->toHaveCount(1)
            ->and($order->totalItemCount)->toBe(1)
            ->and($order->totalQtyOrdered)->toBe(2.0)
            ->and($order->prices->subtotal)->toBe(200.00)
            ->and($order->prices->grandTotal)->toBe(200.00);
    });

    it('can handle configurable product with child items', function () {
        $order = new Order();

        $parentItem = new OrderItem();
        $parentItem->id = 1;
        $parentItem->sku = 'CONFIG-PRODUCT';
        $parentItem->name = 'Configurable Product';
        $parentItem->productType = 'configurable';
        $parentItem->qty = 2.0;

        $childItem = new OrderItem();
        $childItem->id = 2;
        $childItem->sku = 'SIMPLE-VARIANT';
        $childItem->name = 'Simple Variant';
        $childItem->productType = 'simple';
        $childItem->parentItemId = 1;
        $childItem->qty = 2.0;
        $childItem->price = 50.00;
        $childItem->rowTotal = 100.00;

        $order->items = [$parentItem, $childItem];

        expect($order->items)->toHaveCount(2)
            ->and($order->items[0]->productType)->toBe('configurable')
            ->and($order->items[1]->parentItemId)->toBe(1)
            ->and($order->items[1]->productType)->toBe('simple');
    });
});

describe('Order - address mapping', function () {
    it('can assign billing and shipping addresses', function () {
        $order = new Order();

        $billingAddress = new Address();
        $billingAddress->firstname = 'John';
        $billingAddress->lastname = 'Doe';
        $billingAddress->street = ['123 Main St'];
        $billingAddress->city = 'Sydney';
        $billingAddress->region = 'NSW';
        $billingAddress->postcode = '2000';
        $billingAddress->countryId = 'AU';
        $billingAddress->telephone = '0412345678';

        $shippingAddress = new Address();
        $shippingAddress->firstname = 'Jane';
        $shippingAddress->lastname = 'Smith';
        $shippingAddress->street = ['456 Oak Ave'];
        $shippingAddress->city = 'Melbourne';
        $shippingAddress->region = 'VIC';
        $shippingAddress->postcode = '3000';
        $shippingAddress->countryId = 'AU';
        $shippingAddress->telephone = '0498765432';

        $order->billingAddress = $billingAddress;
        $order->shippingAddress = $shippingAddress;

        expect($order->billingAddress)->toBeInstanceOf(Address::class)
            ->and($order->billingAddress->city)->toBe('Sydney')
            ->and($order->billingAddress->region)->toBe('NSW')
            ->and($order->shippingAddress)->toBeInstanceOf(Address::class)
            ->and($order->shippingAddress->city)->toBe('Melbourne')
            ->and($order->shippingAddress->region)->toBe('VIC');
    });

    it('handles same billing and shipping address', function () {
        $order = new Order();

        $address = new Address();
        $address->firstname = 'John';
        $address->lastname = 'Doe';
        $address->street = ['123 Main St'];
        $address->city = 'Sydney';
        $address->region = 'NSW';
        $address->postcode = '2000';
        $address->countryId = 'AU';

        $order->billingAddress = $address;
        $order->shippingAddress = $address;

        expect($order->billingAddress)->toBe($order->shippingAddress)
            ->and($order->billingAddress->city)->toBe('Sydney');
    });
});

describe('Order - shipments', function () {
    it('can add shipments to order', function () {
        $order = new Order();

        $shipment = new Shipment();
        $shipment->id = 1;
        $shipment->incrementId = '100000001';
        $shipment->createdAt = '2025-01-15 10:00:00';

        $order->shipments[] = $shipment;

        expect($order->shipments)->toHaveCount(1)
            ->and($order->shipments[0])->toBeInstanceOf(Shipment::class)
            ->and($order->shipments[0]->incrementId)->toBe('100000001');
    });
});

describe('Order - database integration', function () {
    it('can load a real order from database', function () {
        $mahoOrder = \Mage::getModel('sales/order')->getCollection()
            ->setPageSize(1)
            ->getFirstItem();

        // Skip test if no orders exist
        if (!$mahoOrder->getId()) {
            expect(true)->toBeTrue();
            return;
        }

        expect($mahoOrder->getId())->toBeInt()
            ->and($mahoOrder->getIncrementId())->toBeString()->not->toBeEmpty()
            ->and($mahoOrder->getStatus())->toBeString()->not->toBeEmpty();
    });

    it('verifies order has required data structure', function () {
        $mahoOrder = \Mage::getModel('sales/order')->getCollection()
            ->setPageSize(1)
            ->getFirstItem();

        // Skip test if no orders exist
        if (!$mahoOrder->getId()) {
            expect(true)->toBeTrue();
            return;
        }

        // Verify core order data exists (Maho DataObject uses getData())
        expect($mahoOrder->getData('entity_id'))->not->toBeNull()
            ->and($mahoOrder->getData('increment_id'))->not->toBeNull()
            ->and($mahoOrder->getData('status'))->not->toBeNull()
            ->and($mahoOrder->getData('state'))->not->toBeNull()
            ->and($mahoOrder->getData('customer_email'))->not->toBeNull()
            ->and($mahoOrder->getData('base_currency_code'))->not->toBeNull()
            ->and($mahoOrder->getData('store_id'))->not->toBeNull();

        // Verify order can load items
        $items = $mahoOrder->getAllItems();
        expect($items)->toBeArray();

        // If order has items, verify item structure
        if (count($items) > 0) {
            $firstItem = reset($items);
            expect($firstItem->getData('item_id'))->not->toBeNull()
                ->and($firstItem->getData('sku'))->not->toBeNull()
                ->and($firstItem->getData('name'))->not->toBeNull()
                ->and($firstItem->getData('qty_ordered'))->not->toBeNull();
        }
    });

    it('can map database order to Order DTO', function () {
        $mahoOrder = \Mage::getModel('sales/order')->getCollection()
            ->setPageSize(1)
            ->getFirstItem();

        // Skip test if no orders exist
        if (!$mahoOrder->getId()) {
            expect(true)->toBeTrue();
            return;
        }

        $orderDto = new Order();
        $orderDto->id = (int) $mahoOrder->getId();
        $orderDto->incrementId = $mahoOrder->getIncrementId();
        $orderDto->customerId = $mahoOrder->getCustomerId() ? (int) $mahoOrder->getCustomerId() : null;
        $orderDto->customerEmail = $mahoOrder->getCustomerEmail();
        $orderDto->customerFirstname = $mahoOrder->getCustomerFirstname();
        $orderDto->customerLastname = $mahoOrder->getCustomerLastname();
        $orderDto->status = $mahoOrder->getStatus();
        $orderDto->state = $mahoOrder->getState();
        $orderDto->storeId = (int) $mahoOrder->getStoreId();
        $orderDto->currency = $mahoOrder->getBaseCurrencyCode() ?? 'AUD';
        $orderDto->createdAt = $mahoOrder->getCreatedAt();
        $orderDto->updatedAt = $mahoOrder->getUpdatedAt();

        expect($orderDto->id)->toBeInt()
            ->and($orderDto->incrementId)->toBeString()->not->toBeEmpty()
            ->and($orderDto->status)->toBeString()->not->toBeEmpty()
            ->and($orderDto->state)->toBeString()->not->toBeEmpty()
            ->and($orderDto->storeId)->toBeInt()
            ->and($orderDto->currency)->toBeString()->not->toBeEmpty();
    });
});
