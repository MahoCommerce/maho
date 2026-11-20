<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Order Attributes Condition Integration Tests', function () {
    beforeEach(function () {
        $this->customers = createOrderAttributesTestData();
    });

    describe('Basic Order Field Attributes', function () {
        $createdSince = date('Y-m-d', strtotime('-30 days'));
        $updatedSince = date('Y-m-d', strtotime('-15 days'));

        test('matches the customers by a field of one of their orders', function (string $attribute, string $operator, string $value, ?string $member, Closure $matches) {
            $segment = createOrderAttributesTestSegment('Order ' . $attribute, [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => $attribute,
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            if ($member !== null) {
                expect($matchedCustomers)->toContain($this->customers[$member]);
            }

            foreach ($matchedCustomers as $customerId) {
                $hasMatchingOrder = false;
                foreach (Mage::getResourceModel('sales/order_collection')->addFieldToFilter('customer_id', $customerId) as $order) {
                    if ($matches($order)) {
                        $hasMatchingOrder = true;
                        break;
                    }
                }
                expect($hasMatchingOrder)->toBe(true, "Customer {$customerId} has no order with {$attribute} {$operator} {$value}");
            }
        })->with([
            'total quantity' => ['total_qty', '>=', '5', 'High Value', fn($o) => (float) $o->getTotalQtyOrdered() >= 5],
            'total amount' => ['total_amount', '>=', '200.00', 'High Value', fn($o) => (float) $o->getGrandTotal() >= 200.00],
            'subtotal' => ['subtotal', '>=', '150.00', 'High Value', fn($o) => (float) $o->getSubtotal() >= 150.00],
            'tax amount' => ['tax_amount', '>=', '15.00', 'High Value', fn($o) => (float) $o->getTaxAmount() >= 15.00],
            'shipping amount' => ['shipping_amount', '>=', '20.00', null, fn($o) => (float) $o->getShippingAmount() >= 20.00],
            'discount amount' => ['discount_amount', '>=', '10.00', null, fn($o) => abs((float) $o->getDiscountAmount()) >= 10.00],
            'grand total' => ['grand_total', '>=', '500.00', null, fn($o) => (float) $o->getGrandTotal() >= 500.00],
            'status' => ['status', '==', 'complete', 'High Value', fn($o) => $o->getStatus() === 'complete'],
            'status is not canceled' => ['status', '!=', 'canceled', 'Recent Buyer', fn($o) => $o->getStatus() !== 'canceled'],
            'creation date' => ['created_at', '>=', $createdSince, 'Recent Buyer', fn($o) => date('Y-m-d', strtotime($o->getCreatedAt())) >= $createdSince],
            'update date' => ['updated_at', '>=', $updatedSince, 'Recent Buyer', fn($o) => date('Y-m-d', strtotime($o->getUpdatedAt())) >= $updatedSince],
            'currency' => ['currency_code', '==', 'USD', 'High Value', fn($o) => $o->getOrderCurrencyCode() === 'USD'],
            'store' => ['store_id', '==', '1', 'High Value', fn($o) => (int) $o->getStoreId() === 1],
            'payment method' => ['payment_method', '==', 'checkmo', 'High Value', fn($o) => $o->getPayment() && $o->getPayment()->getMethod() === 'checkmo'],
            'shipping method' => ['shipping_method', '{}', 'flatrate', 'High Value', fn($o) => str_contains((string) $o->getShippingMethod(), 'flatrate')],
            'any coupon' => ['coupon_code', '!=', '', 'High Value', fn($o) => !empty($o->getCouponCode())],
            'specific coupon' => ['coupon_code', '==', 'SAVE10', 'High Value', fn($o) => $o->getCouponCode() === 'SAVE10'],
            'positive amount' => ['total_amount', '>', '0', 'Recent Buyer', fn($o) => (float) $o->getGrandTotal() > 0],
        ]);
    });

    describe('Payment Method Conditions (with SQL Join)', function () {
        test('payment method condition generates correct SQL with join', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('payment_method');
            $condition->setOperator('==');
            $condition->setValue('checkmo');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('JOIN');
            expect($sql)->toContain('sales_flat_order_payment');
            expect($sql)->toContain('p.method');
        });
    });

    describe('Calculated Fields - Days Since Last Order', function () {
        test('matches the customers by the days since their last order', function (string $operator, string $value, string $member, string $other) {
            $segment = createOrderAttributesTestSegment('Last Order ' . $operator . ' ' . $value, [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'days_since_last_order',
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers[$member])
                ->and($matchedCustomers)->not->toContain($this->customers[$other])
                ->and($matchedCustomers)->not->toContain($this->customers['No Orders']);

            // The segment counts calendar days in UTC, like DATEDIFF
            $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->setOrder('created_at', 'DESC');
                expect($orders->getSize())->toBeGreaterThan(0);

                $lastDay = new DateTimeImmutable(substr((string) $orders->getFirstItem()->getCreatedAt(), 0, 10), new DateTimeZone('UTC'));
                $days = $today->diff($lastDay)->days;
                expect($operator === '<=' ? $days <= (int) $value : $days >= (int) $value)->toBeTrue("Customer {$customerId}: {$days} days");
            }
        })->with([
            'within 30 days' => ['<=', '30', 'Recent Buyer', 'Old Customer'],
            '90 days or more' => ['>=', '90', 'Old Customer', 'Recent Buyer'],
        ]);
    });

    describe('Calculated Fields - Average Order Amount', function () {
        test('finds customers with high average order value', function () {
            $segment = createOrderAttributesTestSegment('High AOV Customers', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'average_order_amount',
                'operator' => '>=',
                'value' => '150.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers['High Value'])
                ->and($matchedCustomers)->not->toContain($this->customers['Frequent Buyer']);

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);
                expect($orders->getSize())->toBeGreaterThan(0);

                $totalAmount = 0.0;
                foreach ($orders as $order) {
                    $totalAmount += (float) $order->getBaseGrandTotal();
                }
                expect($totalAmount / $orders->getSize())->toBeGreaterThanOrEqual(150.00);
            }
        });
    });

    describe('Calculated Fields - Total Ordered Amount', function () {
        test('finds customers with high total order amount', function () {
            $segment = createOrderAttributesTestSegment('High Total Customers', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'total_ordered_amount',
                'operator' => '>=',
                'value' => '500.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['neq' => 'canceled']);

                $totalAmount = 0;
                foreach ($orders as $order) {
                    $totalAmount += (float) $order->getGrandTotal();
                }

                expect($totalAmount)->toBeGreaterThanOrEqual(500.00);
            }
        });
    });

    describe('Dynamic Option Loading', function () {
        test('loads order status options dynamically', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('status');

            $options = $condition->getValueSelectOptions();

            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(1); // Should have at least "Please select..." + status options

            $hasStatusOptions = false;
            foreach ($options as $option) {
                if (!empty($option['value']) && $option['value'] !== '') {
                    $hasStatusOptions = true;
                    break;
                }
            }
            expect($hasStatusOptions)->toBe(true);
        });

        test('loads payment method options dynamically', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('payment_method');

            $options = $condition->getValueSelectOptions();

            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(1);

            // Should include at least checkmo (Check/Money Order)
            $hasCheckmo = false;
            foreach ($options as $option) {
                if ($option['value'] === 'checkmo') {
                    $hasCheckmo = true;
                    break;
                }
            }
            expect($hasCheckmo)->toBe(true);
        });

        test('loads shipping method options dynamically', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('shipping_method');

            $options = $condition->getValueSelectOptions();

            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(1);

            // Should include flatrate shipping options
            $hasFlatrate = false;
            foreach ($options as $option) {
                if (strpos($option['value'], 'flatrate') !== false) {
                    $hasFlatrate = true;
                    break;
                }
            }
            expect($hasFlatrate)->toBe(true);
        });

        test('loads currency options dynamically', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('currency_code');

            $options = $condition->getValueSelectOptions();

            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(0);

            // Should include USD (default Maho currency)
            $hasUSD = false;
            foreach ($options as $option) {
                if ($option['value'] === 'USD') {
                    $hasUSD = true;
                    break;
                }
            }
            expect($hasUSD)->toBe(true);
        });

        test('loads store options dynamically', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('store_id');

            $options = $condition->getValueSelectOptions();

            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(1);

            // Should have "Please select..." as first option
            expect($options[0]['value'])->toBe('');
        });
    });

    describe('SQL Generation and Performance', function () {
        test('generates valid SQL for all supported attributes', function () {
            $attributes = [
                'total_qty', 'total_amount', 'subtotal', 'tax_amount', 'shipping_amount',
                'discount_amount', 'grand_total', 'status', 'created_at', 'updated_at',
                'store_id', 'currency_code', 'payment_method', 'shipping_method', 'coupon_code',
                'days_since_last_order', 'average_order_amount', 'total_ordered_amount',
            ];

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

            foreach ($attributes as $attribute) {
                $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
                $condition->setAttribute($attribute);
                $condition->setOperator('>=');

                // Use appropriate values based on attribute type
                $value = match ($attribute) {
                    'created_at', 'updated_at' => '2024-01-01',
                    'status' => 'pending',
                    'currency_code' => 'USD',
                    'payment_method' => 'checkmo',
                    'shipping_method' => 'flatrate_flatrate',
                    'store_id' => '1',
                    default => '1',
                };

                $condition->setValue($value);

                $sql = $condition->getConditionsSql($adapter);

                // Check that SQL is either a string or false
                expect(is_string($sql) || $sql === false)->toBe(true, "Attribute '{$attribute}' must return string or false, got: " . gettype($sql));

                if ($sql !== false && $sql !== null) {
                    expect($sql)->toContain('e.entity_id IN');
                }
            }
        });

        test('SQL contains proper subqueries for calculated fields', function () {
            $calculatedFields = [
                'days_since_last_order' => ['pattern' => '/DATEDIFF|::date|DATE\\(|JULIANDAY/', 'contains' => ['MAX(o.created_at)', 'HAVING']],
                'average_order_amount' => ['contains' => ['AVG(o.base_grand_total)', "o.state NOT IN ('canceled')", 'HAVING']],
                'total_ordered_amount' => ['contains' => ['SUM(o.base_grand_total)', "o.state NOT IN ('canceled')", 'HAVING']],
            ];

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

            foreach ($calculatedFields as $attribute => $fieldConfig) {
                $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
                $condition->setAttribute($attribute);
                $condition->setOperator('>=');
                $condition->setValue('1');

                $sql = $condition->getConditionsSql($adapter);

                expect($sql)->toBeString();

                // Check regex pattern if specified (for MySQL/PostgreSQL compatibility)
                if (isset($fieldConfig['pattern'])) {
                    expect($sql)->toMatch($fieldConfig['pattern']);
                }

                // Check required SQL parts
                foreach ($fieldConfig['contains'] ?? [] as $sqlPart) {
                    expect($sql)->toContain($sqlPart);
                }
            }
        });
    });

    // Helper functions for test data creation
    /**
     * @return array<string, int> customer IDs keyed by "firstname lastname"
     */
    function createOrderAttributesTestData(): array
    {
        $uniqueId = uniqid('order_attr_', true);

        $customers = [
            // Customer with high-value orders
            [
                'firstname' => 'High',
                'lastname' => 'Value',
                'email' => "high.value.{$uniqueId}@test.com",
                'orders' => [
                    [
                        'days_ago' => 10,
                        'grand_total' => 250.00,
                        'subtotal' => 220.00,
                        'tax_amount' => 20.00,
                        'shipping_amount' => 10.00,
                        'discount_amount' => -10.00,
                        'total_qty_ordered' => 8,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => 'SAVE10',
                    ],
                    [
                        'days_ago' => 40,
                        'grand_total' => 180.00,
                        'subtotal' => 165.00,
                        'tax_amount' => 15.00,
                        'shipping_amount' => 10.00,
                        'discount_amount' => 0.00,
                        'total_qty_ordered' => 6,
                        'status' => 'processing',
                        'state' => 'processing',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                    [
                        'days_ago' => 70,
                        'grand_total' => 320.00,
                        'subtotal' => 290.00,
                        'tax_amount' => 25.00,
                        'shipping_amount' => 15.00,
                        'discount_amount' => -20.00,
                        'total_qty_ordered' => 10,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => 'SAVE20',
                    ],
                ],
            ],

            // Customer with single recent order
            [
                'firstname' => 'Recent',
                'lastname' => 'Buyer',
                'email' => "recent.buyer.{$uniqueId}@test.com",
                'orders' => [
                    [
                        'days_ago' => 5,
                        'grand_total' => 120.00,
                        'subtotal' => 110.00,
                        'tax_amount' => 10.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 3,
                        'status' => 'pending',
                        'state' => 'new',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                ],
            ],

            // Customer with old orders
            [
                'firstname' => 'Old',
                'lastname' => 'Customer',
                'email' => "old.customer.{$uniqueId}@test.com",
                'orders' => [
                    [
                        'days_ago' => 120,
                        'grand_total' => 85.00,
                        'subtotal' => 80.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 2,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                    [
                        'days_ago' => 150,
                        'grand_total' => 60.00,
                        'subtotal' => 55.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 1,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                ],
            ],

            // Customer with canceled order (should be excluded from some calculations)
            [
                'firstname' => 'Canceled',
                'lastname' => 'Order',
                'email' => "canceled.order.{$uniqueId}@test.com",
                'orders' => [
                    [
                        'days_ago' => 20,
                        'grand_total' => 100.00,
                        'subtotal' => 95.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 3,
                        'status' => 'canceled',
                        'state' => 'canceled',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                    [
                        'days_ago' => 30,
                        'grand_total' => 75.00,
                        'subtotal' => 70.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 2,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                ],
            ],

            // Customer with multiple small orders
            [
                'firstname' => 'Frequent',
                'lastname' => 'Buyer',
                'email' => "frequent.buyer.{$uniqueId}@test.com",
                'orders' => [
                    [
                        'days_ago' => 15,
                        'grand_total' => 45.00,
                        'subtotal' => 40.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 1,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                    [
                        'days_ago' => 35,
                        'grand_total' => 55.00,
                        'subtotal' => 50.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 2,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                    [
                        'days_ago' => 55,
                        'grand_total' => 35.00,
                        'subtotal' => 30.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 1,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                    [
                        'days_ago' => 75,
                        'grand_total' => 65.00,
                        'subtotal' => 60.00,
                        'tax_amount' => 5.00,
                        'shipping_amount' => 5.00,
                        'discount_amount' => -5.00,
                        'total_qty_ordered' => 2,
                        'status' => 'complete',
                        'state' => 'complete',
                        'currency_code' => 'USD',
                        'payment_method' => 'checkmo',
                        'shipping_method' => 'flatrate_flatrate',
                        'coupon_code' => '',
                    ],
                ],
            ],

            // Customer with no orders
            [
                'firstname' => 'No',
                'lastname' => 'Orders',
                'email' => "no.orders.{$uniqueId}@test.com",
                'orders' => [],
            ],
        ];

        $ids = [];
        foreach ($customers as $customerData) {
            // Create customer
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId(1);
            $customer->setWebsiteId(1);
            $customer->save();
            $ids[$customerData['firstname'] . ' ' . $customerData['lastname']] = (int) $customer->getId();

            // Create orders
            foreach ($customerData['orders'] as $orderData) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setStoreId(1);

                // Set order amounts
                $order->setGrandTotal($orderData['grand_total']);
                $order->setBaseGrandTotal($orderData['grand_total']);
                $order->setSubtotal($orderData['subtotal']);
                $order->setBaseSubtotal($orderData['subtotal']);
                $order->setTaxAmount($orderData['tax_amount']);
                $order->setBaseTaxAmount($orderData['tax_amount']);
                $order->setShippingAmount($orderData['shipping_amount']);
                $order->setBaseShippingAmount($orderData['shipping_amount']);
                $order->setDiscountAmount($orderData['discount_amount']);
                $order->setBaseDiscountAmount($orderData['discount_amount']);
                $order->setTotalQtyOrdered($orderData['total_qty_ordered']);

                $order->setData('state', orderStateForStatus($orderData['status']));
                $order->setStatus($orderData['status']);

                // Set currency
                $order->setOrderCurrencyCode($orderData['currency_code']);
                $order->setBaseCurrencyCode($orderData['currency_code']);

                // Set shipping method
                $order->setShippingMethod($orderData['shipping_method']);

                // Set coupon code if provided
                if (!empty($orderData['coupon_code'])) {
                    $order->setCouponCode($orderData['coupon_code']);
                }

                // Set created date
                $orderCreatedAt = date('Y-m-d H:i:s', strtotime("-{$orderData['days_ago']} days"));
                $order->setCreatedAt($orderCreatedAt);
                $order->setUpdatedAt($orderCreatedAt);

                $order->save();

                // Create payment record
                $payment = Mage::getModel('sales/order_payment');
                $payment->setParentId($order->getId());
                $payment->setMethod($orderData['payment_method']);
                $payment->save();

            }
        }

        return $ids;
    }

    function createOrderAttributesTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
        $segment->setDescription('Order attributes test segment for ' . $name);
        $segment->setIsActive();
        $segment->setWebsiteIds([1]);
        $segment->setCustomerGroupIds([0, 1, 2, 3]);
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->setPriority(10);
        $segment->save();

        return $segment;
    }
});
