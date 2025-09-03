<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Order Items Condition Integration Tests', function () {
    beforeEach(function () {
        createOrderItemsTestData();
    });

    describe('Product Attribute Conditions', function () {
        test('filters customers by product name', function () {
            $segment = createOrderItemsTestSegment('Bought T-Shirt', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'product_name',
                'operator' => 'like',
                'value' => '%T-Shirt%',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify each matched customer has actually bought a product containing "T-Shirt"
            foreach ($matchedCustomers as $customerId) {
                $hasTShirtOrder = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                foreach ($orders as $order) {
                    $orderItems = $order->getAllItems();
                    foreach ($orderItems as $item) {
                        if (strpos($item->getName(), 'T-Shirt') !== false) {
                            $hasTShirtOrder = true;
                            break 2;
                        }
                    }
                }
                expect($hasTShirtOrder)->toBe(true);
            }
        });

        test('filters customers by product SKU', function () {
            $segment = createOrderItemsTestSegment('Bought Specific SKU', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'product_sku',
                'operator' => '==',
                'value' => 'TEST-SKU-001',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify each matched customer has bought the specific SKU
            foreach ($matchedCustomers as $customerId) {
                $hasSkuOrder = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                foreach ($orders as $order) {
                    $orderItems = $order->getAllItems();
                    foreach ($orderItems as $item) {
                        if ($item->getSku() === 'TEST-SKU-001') {
                            $hasSkuOrder = true;
                            break 2;
                        }
                    }
                }
                expect($hasSkuOrder)->toBe(true);
            }
        });

        test('filters customers by product type', function () {
            $segment = createOrderItemsTestSegment('Bought Simple Products', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'product_type',
                'operator' => '==',
                'value' => 'simple',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify each matched customer has bought simple products
            foreach ($matchedCustomers as $customerId) {
                $hasSimpleProductOrder = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                foreach ($orders as $order) {
                    $orderItems = $order->getAllItems();
                    foreach ($orderItems as $item) {
                        $product = Mage::getModel('catalog/product')->load($item->getProductId());
                        if ($product->getTypeId() === 'simple') {
                            $hasSimpleProductOrder = true;
                            break 2;
                        }
                    }
                }
                expect($hasSimpleProductOrder)->toBe(true);
            }
        });
    });

    describe('Order Item Field Conditions', function () {
        test('filters customers by quantity ordered', function () {
            $segment = createOrderItemsTestSegment('High Quantity Items', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'qty_ordered',
                'operator' => '>=',
                'value' => '5',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify each matched customer has ordered at least 5 of some item
            foreach ($matchedCustomers as $customerId) {
                $hasHighQtyOrder = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                foreach ($orders as $order) {
                    $orderItems = $order->getAllItems();
                    foreach ($orderItems as $item) {
                        if ((float) $item->getQtyOrdered() >= 5) {
                            $hasHighQtyOrder = true;
                            break 2;
                        }
                    }
                }
                expect($hasHighQtyOrder)->toBe(true);
            }
        });

        test('filters customers by row total', function () {
            $segment = createOrderItemsTestSegment('High Value Items', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'row_total',
                'operator' => '>=',
                'value' => '100.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify each matched customer has items with row total >= $100
            foreach ($matchedCustomers as $customerId) {
                $hasHighValueItem = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                foreach ($orders as $order) {
                    $orderItems = $order->getAllItems();
                    foreach ($orderItems as $item) {
                        if ((float) $item->getRowTotal() >= 100.00) {
                            $hasHighValueItem = true;
                            break 2;
                        }
                    }
                }
                expect($hasHighValueItem)->toBe(true);
            }
        });

        test('filters customers by discount amount', function () {
            $segment = createOrderItemsTestSegment('Used Item Discounts', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'discount_amount',
                'operator' => '>',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify each matched customer has items with discounts
            foreach ($matchedCustomers as $customerId) {
                $hasDiscountedItem = false;
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                foreach ($orders as $order) {
                    $orderItems = $order->getAllItems();
                    foreach ($orderItems as $item) {
                        if ((float) $item->getDiscountAmount() > 0) {
                            $hasDiscountedItem = true;
                            break 2;
                        }
                    }
                }
                expect($hasDiscountedItem)->toBe(true);
            }
        });
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
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

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
        test('handles empty attribute values gracefully', function () {
            $segment = createOrderItemsTestSegment('Empty Product Name', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'product_name',
                'operator' => '==',
                'value' => '',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();
            // Should return empty array or handle gracefully
        });

        test('handles invalid product attribute gracefully', function () {
            $segment = createOrderItemsTestSegment('Invalid Product Attribute', [
                'type' => 'customersegmentation/segment_condition_order_items',
                'attribute' => 'product_nonexistent_attribute',
                'operator' => '==',
                'value' => 'test',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();
            // Should handle gracefully without errors
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

function createOrderItemsTestData(): void
{
    // Clean up existing test data
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
    $order->setGrandTotal($grandTotal);
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
        $orderItem->setRowTotalInclTax($itemData['qty'] * $itemData['price']);
        $orderItem->setDiscountAmount($itemData['discount'] ?? 0);
        $orderItem->setProductType($itemData['type'] ?? 'simple');

        // Set a dummy product ID (in real scenario, this would reference actual products)
        $orderItem->setProductId(1);

        $orderItem->save();
    }

    return $order;
}

function createOrderItemsTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
{
    $segment = Mage::getModel('customersegmentation/segment');
    $segment->setName($name);
    $segment->setIsActive(1);
    $segment->setWebsiteIds([1]);
    $segment->setConditionsSerialized(serialize($conditions));
    $segment->save();

    return $segment;
}
