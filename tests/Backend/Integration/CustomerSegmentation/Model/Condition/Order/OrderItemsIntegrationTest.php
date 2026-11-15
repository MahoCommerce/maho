<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Order Items Condition Integration Tests', function () {
    beforeEach(function () {
        $this->customers = createOrderItemsTestData();
    });

    describe('Product Attribute Conditions', function () {
        test('matches the customers by a field of one of their order items', function (string $attribute, string $operator, string $value, string $member, Closure $matches) {
            $segment = createOrderItemsTestSegment('Order item ' . $attribute, [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => $attribute,
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers[$member]);

            foreach ($matchedCustomers as $customerId) {
                $hasMatchingItem = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['neq' => 'canceled']);
                foreach ($orders as $order) {
                    foreach ($order->getAllItems() as $item) {
                        if ($matches($item)) {
                            $hasMatchingItem = true;
                            break 2;
                        }
                    }
                }
                expect($hasMatchingItem)->toBe(true, "Customer {$customerId} has no item with {$attribute} {$operator} {$value}");
            }
        })->with([
            // LIKE ignores case, so a "T-shirt" matches too
            'product name' => ['product_name', 'like', '%T-Shirt%', 'orderitemstest1', fn($i) => stripos((string) $i->getName(), 'T-Shirt') !== false],
            'product SKU' => ['product_sku', '==', 'TEST-SKU-001', 'orderitemstest1', fn($i) => $i->getSku() === 'TEST-SKU-001'],
            'product type' => ['product_type', '==', 'simple', 'orderitemstest1', fn($i) => $i->getProductType() === 'simple'],
            'quantity ordered' => ['qty_ordered', '>=', '5', 'orderitemstest1', fn($i) => (float) $i->getQtyOrdered() >= 5],
            'row total' => ['row_total', '>=', '100.00', 'orderitemstest2', fn($i) => (float) $i->getRowTotal() >= 100.00],
            'discount amount' => ['discount_amount', '>', '0', 'orderitemstest3', fn($i) => (float) $i->getDiscountAmount() > 0],
        ]);
    });

    describe('Complex Combinations', function () {
        test('combines product and quantity conditions', function () {
            $segment = createOrderItemsTestSegment('High Quantity T-Shirts', [
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'all',
                'value' => 1,
                'conditions' => [
                    [
                        'type' => 'customersegmentation/segment_condition_order_items',
                        'attribute' => 'product_name',
                        'operator' => 'like',
                        'value' => '%T-Shirt%',
                    ],
                    [
                        'type' => 'customersegmentation/segment_condition_order_items',
                        'attribute' => 'qty_ordered',
                        'operator' => '>=',
                        'value' => '3',
                    ],
                ],
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify each matched customer has T-Shirts with quantity >= 3
            foreach ($matchedCustomers as $customerId) {
                $hasHighQtyTShirt = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['neq' => 'canceled']);

                foreach ($orders as $order) {
                    $orderItems = $order->getAllItems();
                    foreach ($orderItems as $item) {
                        if (strpos($item->getName(), 'T-Shirt') !== false && (float) $item->getQtyOrdered() >= 3) {
                            $hasHighQtyTShirt = true;
                            break 2;
                        }
                    }
                }
                expect($hasHighQtyTShirt)->toBe(true);
            }
        });
    });

    describe('Edge Cases and Error Handling', function () {
        test('handles invalid product attribute gracefully', function () {
            $segment = createOrderItemsTestSegment('Invalid Product Attribute', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'product_nonexistent_attribute',
                'operator' => '==',
                'value' => 'test',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Invalid product attribute should return empty results gracefully (no errors)
            // System should handle non-existent attributes by returning no matches
            expect(count($matchedCustomers))->toBe(0);

            // Verify no exceptions were thrown - if we get here, error handling worked
            expect(true)->toBe(true);
        });

        test('excludes canceled orders', function () {
            // Create a customer with both completed and canceled orders
            $customer = createOrderItemsTestCustomer('canceled_orders_customer');

            // Create completed order
            createOrderItemsTestOrder((int) $customer->getId(), [
                ['name' => 'Test Product', 'sku' => 'TEST-001', 'qty' => 2, 'price' => 50.00],
            ], 'complete');

            // Create canceled order
            createOrderItemsTestOrder((int) $customer->getId(), [
                ['name' => 'Test Product', 'sku' => 'TEST-001', 'qty' => 10, 'price' => 50.00],
            ], 'canceled');

            $segment = createOrderItemsTestSegment('High Quantity Orders', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'qty_ordered',
                'operator' => '>=',
                'value' => '5',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            // Customer should NOT match because only the canceled order has qty >= 5
            expect($matchedCustomers)->not->toContain((int) $customer->getId());
        });
    });
});

/**
 * @return array<string, int> customer IDs keyed by the local part of their email
 */
function createOrderItemsTestData(): array
{
    // Clean up existing test data - be more aggressive
    $orderItems = Mage::getResourceModel('sales/order_item_collection')
        ->addFieldToFilter('sku', ['like' => 'TEST-%']);
    foreach ($orderItems as $item) {
        $item->delete();
    }

    $orders = Mage::getResourceModel('sales/order_collection')
        ->addFieldToFilter('increment_id', ['like' => 'TEST-%']);
    foreach ($orders as $order) {
        $order->delete();
    }

    $customers = Mage::getResourceModel('customer/customer_collection')
        ->addFieldToFilter('email', ['like' => '%orderitemstest%']);
    foreach ($customers as $customer) {
        $customer->delete();
    }

    // Also clean up any stray customers that might interfere
    for ($i = 1; $i <= 10; $i++) {
        $testCustomer = Mage::getModel('customer/customer')->load($i);
        if ($testCustomer->getId() && strpos($testCustomer->getEmail() ?? '', 'orderitemstest') !== false) {
            $testCustomer->delete();
        }
    }

    // Create test customers
    $customer1 = createOrderItemsTestCustomer('orderitemstest1');
    $customer2 = createOrderItemsTestCustomer('orderitemstest2');
    $customer3 = createOrderItemsTestCustomer('orderitemstest3');

    // Create orders with different item configurations

    // Customer 1: High quantity T-Shirt order
    createOrderItemsTestOrder((int) $customer1->getId(), [
        ['name' => 'Cool T-Shirt', 'sku' => 'TEST-SKU-001', 'qty' => 6, 'price' => 25.00, 'type' => 'simple'],
        ['name' => 'Jeans', 'sku' => 'TEST-SKU-002', 'qty' => 2, 'price' => 75.00, 'type' => 'simple', 'discount' => 10.00],
    ]);

    // Customer 2: High value items
    createOrderItemsTestOrder((int) $customer2->getId(), [
        ['name' => 'Premium Jacket', 'sku' => 'TEST-SKU-003', 'qty' => 1, 'price' => 150.00, 'type' => 'configurable'],
        ['name' => 'Basic T-Shirt', 'sku' => 'TEST-SKU-004', 'qty' => 3, 'price' => 20.00, 'type' => 'simple'],
    ]);

    // Customer 3: Discounted items
    createOrderItemsTestOrder((int) $customer3->getId(), [
        ['name' => 'Sale Shirt', 'sku' => 'TEST-SKU-005', 'qty' => 4, 'price' => 30.00, 'type' => 'simple', 'discount' => 15.00],
    ]);

    return [
        'orderitemstest1' => (int) $customer1->getId(),
        'orderitemstest2' => (int) $customer2->getId(),
        'orderitemstest3' => (int) $customer3->getId(),
    ];
}

function createOrderItemsTestCustomer(string $identifier): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1);
    $customer->setEmail($identifier . '@orderitemstest.com');
    $customer->setFirstname('Order Items');
    $customer->setLastname('Test ' . $identifier);
    $customer->setGroupId(1);
    $customer->save();
    return $customer;
}

function createOrderItemsTestOrder(int $customerId, array $items, string $state = 'complete'): Mage_Sales_Model_Order
{
    static $incrementId = 10000;
    $incrementId++;

    $order = Mage::getModel('sales/order');
    $order->setIncrementId('TEST-' . $incrementId);
    $order->setCustomerId($customerId);
    $order->setStoreId(1);

    // Set order totals
    $grandTotal = 0;
    $subTotal = 0;
    $totalQty = 0;

    foreach ($items as $itemData) {
        $rowTotal = $itemData['qty'] * $itemData['price'];
        $discount = $itemData['discount'] ?? 0;
        $subTotal += $rowTotal;
        $grandTotal += $rowTotal - $discount;
        $totalQty += $itemData['qty'];
    }

    $order->setSubtotal($subTotal);
    $order->setBaseSubtotal($subTotal);
    $order->setGrandTotal($grandTotal);
    $order->setBaseGrandTotal($grandTotal);
    $order->setTotalQtyOrdered($totalQty);
    $order->setCreatedAt(date('Y-m-d H:i:s'));

    // Set state/status after other data is set
    if ($state === 'canceled') {
        $order->setData('state', 'canceled');
        $order->setData('status', 'canceled');
    } else {
        $order->setData('state', 'complete');
        $order->setData('status', 'complete');
    }

    $order->save();

    // Create order items
    foreach ($items as $itemData) {
        $orderItem = Mage::getModel('sales/order_item');
        $orderItem->setOrderId($order->getId());
        $orderItem->setName($itemData['name']);
        $orderItem->setSku($itemData['sku']);
        $orderItem->setQtyOrdered($itemData['qty']);
        $orderItem->setPrice($itemData['price']);
        $orderItem->setRowTotal($itemData['qty'] * $itemData['price']);
        $orderItem->setBaseRowTotal($itemData['qty'] * $itemData['price']);
        $orderItem->setRowTotalInclTax($itemData['qty'] * $itemData['price']);
        $orderItem->setBaseRowTotalInclTax($itemData['qty'] * $itemData['price']);
        $orderItem->setDiscountAmount($itemData['discount'] ?? 0);
        $orderItem->setBaseDiscountAmount($itemData['discount'] ?? 0);
        $orderItem->setProductType($itemData['type'] ?? 'simple');

        // Set a dummy product ID (in real scenario, this would reference actual products)
        $orderItem->setProductId(1);

        try {
            $orderItem->save();

            // Debug: verify the item was saved
            if (!$orderItem->getId()) {
                throw new Exception('Failed to save order item: ' . $itemData['name'] . ' - no ID assigned');
            }

            // Verify the item can be loaded back
            $testLoad = Mage::getModel('sales/order_item')->load($orderItem->getId());
            if (!$testLoad->getId()) {
                throw new Exception('Order item was saved but cannot be loaded back: ' . $itemData['name']);
            }

        } catch (Exception $e) {
            throw new Exception('Exception saving order item: ' . $itemData['name'] . ' - ' . $e->getMessage());
        }
    }

    return $order;
}

function createOrderItemsTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
{
    // Wrap single condition in combine structure if needed
    if (isset($conditions['type']) && $conditions['type'] !== 'customersegmentation/segment_condition_combine') {
        $conditions = [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [$conditions],
        ];
    }

    $segment = Mage::getModel('customersegmentation/segment');
    $segment->setName($name);
    $segment->setIsActive();
    $segment->setWebsiteIds([1]);
    $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
    $segment->save();

    return $segment;
}
