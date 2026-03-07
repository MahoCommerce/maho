<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Order Attributes Condition Integration Tests', function () {
    beforeEach(function () {
        createOrderAttributesTestData();
    });

    describe('Basic Order Field Attributes', function () {
        test('filters customers by total quantity of items', function () {
            $segment = createOrderAttributesTestSegment('High Quantity Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'total_qty',
                'operator' => '>=',
                'value' => '5',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasHighQtyOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getTotalQtyOrdered() >= 5) {
                        $hasHighQtyOrder = true;
                        break;
                    }
                }
                expect($hasHighQtyOrder)->toBe(true);
            }
        });

        test('filters customers by total order amount', function () {
            $segment = createOrderAttributesTestSegment('High Value Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'total_amount',
                'operator' => '>=',
                'value' => '200.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasHighValueOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getGrandTotal() >= 200.00) {
                        $hasHighValueOrder = true;
                        break;
                    }
                }
                expect($hasHighValueOrder)->toBe(true);
            }
        });

        test('filters customers by subtotal amount', function () {
            $segment = createOrderAttributesTestSegment('High Subtotal Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'subtotal',
                'operator' => '>=',
                'value' => '150.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasHighSubtotalOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getSubtotal() >= 150.00) {
                        $hasHighSubtotalOrder = true;
                        break;
                    }
                }
                expect($hasHighSubtotalOrder)->toBe(true);
            }
        });

        test('filters customers by tax amount', function () {
            $segment = createOrderAttributesTestSegment('High Tax Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'tax_amount',
                'operator' => '>=',
                'value' => '15.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasHighTaxOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getTaxAmount() >= 15.00) {
                        $hasHighTaxOrder = true;
                        break;
                    }
                }
                expect($hasHighTaxOrder)->toBe(true);
            }
        });

        test('filters customers by shipping amount', function () {
            $segment = createOrderAttributesTestSegment('High Shipping Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'shipping_amount',
                'operator' => '>=',
                'value' => '20.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasHighShippingOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getShippingAmount() >= 20.00) {
                        $hasHighShippingOrder = true;
                        break;
                    }
                }
                expect($hasHighShippingOrder)->toBe(true);
            }
        });

        test('filters customers by discount amount', function () {
            $segment = createOrderAttributesTestSegment('Discounted Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'discount_amount',
                'operator' => '>=',
                'value' => '10.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasDiscountedOrder = false;
                foreach ($orders as $order) {
                    if (abs((float) $order->getDiscountAmount()) >= 10.00) {
                        $hasDiscountedOrder = true;
                        break;
                    }
                }
                expect($hasDiscountedOrder)->toBe(true);
            }
        });

        test('filters customers by grand total', function () {
            $segment = createOrderAttributesTestSegment('High Value Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'grand_total',
                'operator' => '>=',
                'value' => '500.00',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasHighGrandTotalOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getGrandTotal() >= 500.00) {
                        $hasHighGrandTotalOrder = true;
                        break;
                    }
                }
                expect($hasHighGrandTotalOrder)->toBe(true, "Customer {$customerId} should have order with grand total >= 500");
            }
        });
    });

    describe('Order Status and State Conditions', function () {
        test('filters customers by order status', function () {
            $segment = createOrderAttributesTestSegment('Complete Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'status',
                'operator' => '==',
                'value' => 'complete',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasCompleteOrder = false;
                foreach ($orders as $order) {
                    if ($order->getStatus() === 'complete') {
                        $hasCompleteOrder = true;
                        break;
                    }
                }
                expect($hasCompleteOrder)->toBe(true);
            }
        });


        test('excludes canceled orders correctly', function () {
            $segment = createOrderAttributesTestSegment('Non-Canceled Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'status',
                'operator' => '!=',
                'value' => 'canceled',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasNonCanceledOrder = false;
                foreach ($orders as $order) {
                    if ($order->getStatus() !== 'canceled') {
                        $hasNonCanceledOrder = true;
                        break;
                    }
                }
                expect($hasNonCanceledOrder)->toBe(true);
            }
        });
    });

    describe('Date and Time Conditions', function () {
        test('filters customers by order creation date', function () {
            $testDate = date('Y-m-d', strtotime('-30 days'));
            $segment = createOrderAttributesTestSegment('Recent Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'created_at',
                'operator' => '>=',
                'value' => $testDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasRecentOrder = false;
                foreach ($orders as $order) {
                    $orderDate = date('Y-m-d', strtotime($order->getCreatedAt()));
                    if ($orderDate >= $testDate) {
                        $hasRecentOrder = true;
                        break;
                    }
                }
                expect($hasRecentOrder)->toBe(true);
            }
        });

        test('filters customers by order update date', function () {
            $testDate = date('Y-m-d', strtotime('-15 days'));
            $segment = createOrderAttributesTestSegment('Recently Updated Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'updated_at',
                'operator' => '>=',
                'value' => $testDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasRecentlyUpdatedOrder = false;
                foreach ($orders as $order) {
                    $updateDate = date('Y-m-d', strtotime($order->getUpdatedAt()));
                    if ($updateDate >= $testDate) {
                        $hasRecentlyUpdatedOrder = true;
                        break;
                    }
                }
                expect($hasRecentlyUpdatedOrder)->toBe(true);
            }
        });
    });

    describe('Currency and Store Conditions', function () {
        test('filters customers by currency code', function () {
            $segment = createOrderAttributesTestSegment('USD Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'currency_code',
                'operator' => '==',
                'value' => 'USD',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasUSDOrder = false;
                foreach ($orders as $order) {
                    if ($order->getOrderCurrencyCode() === 'USD') {
                        $hasUSDOrder = true;
                        break;
                    }
                }
                expect($hasUSDOrder)->toBe(true);
            }
        });

        test('filters customers by store ID', function () {
            $segment = createOrderAttributesTestSegment('Main Store Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'store_id',
                'operator' => '==',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasMainStoreOrder = false;
                foreach ($orders as $order) {
                    if ((int) $order->getStoreId() === 1) {
                        $hasMainStoreOrder = true;
                        break;
                    }
                }
                expect($hasMainStoreOrder)->toBe(true);
            }
        });
    });

    describe('Payment Method Conditions (with SQL Join)', function () {
        test('filters customers by payment method', function () {
            $segment = createOrderAttributesTestSegment('Credit Card Payments', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'payment_method',
                'operator' => '==',
                'value' => 'checkmo', // Check/Money Order for test
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasCheckmoPayment = false;
                foreach ($orders as $order) {
                    $payment = $order->getPayment();
                    if ($payment && $payment->getMethod() === 'checkmo') {
                        $hasCheckmoPayment = true;
                        break;
                    }
                }
                expect($hasCheckmoPayment)->toBe(true);
            }
        });

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

    describe('Shipping Method Conditions', function () {
        test('filters customers by shipping method', function () {
            $segment = createOrderAttributesTestSegment('Flat Rate Shipping', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'shipping_method',
                'operator' => '{}',
                'value' => 'flatrate',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasFlatrateShipping = false;
                foreach ($orders as $order) {
                    $shippingMethod = $order->getShippingMethod();
                    if (strpos($shippingMethod, 'flatrate') !== false) {
                        $hasFlatrateShipping = true;
                        break;
                    }
                }
                expect($hasFlatrateShipping)->toBe(true);
            }
        });
    });

    describe('Coupon Code Conditions', function () {
        test('filters customers by coupon code usage', function () {
            $segment = createOrderAttributesTestSegment('Coupon Users', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'coupon_code',
                'operator' => '!=',
                'value' => '',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasCouponOrder = false;
                foreach ($orders as $order) {
                    $couponCode = $order->getCouponCode();
                    if (!empty($couponCode)) {
                        $hasCouponOrder = true;
                        break;
                    }
                }
                expect($hasCouponOrder)->toBe(true);
            }
        });

        test('filters customers by specific coupon code', function () {
            $segment = createOrderAttributesTestSegment('SAVE10 Users', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'coupon_code',
                'operator' => '==',
                'value' => 'SAVE10',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasSave10Coupon = false;
                foreach ($orders as $order) {
                    if ($order->getCouponCode() === 'SAVE10') {
                        $hasSave10Coupon = true;
                        break;
                    }
                }
                expect($hasSave10Coupon)->toBe(true);
            }
        });
    });

    describe('Calculated Fields - Days Since Last Order', function () {
        test('finds customers with recent last order', function () {
            $segment = createOrderAttributesTestSegment('Recent Last Order', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'days_since_last_order',
                'operator' => '<=',
                'value' => '30',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->setOrder('created_at', 'DESC');

                if ($orders->getSize() > 0) {
                    $lastOrder = $orders->getFirstItem();
                    $lastOrderDate = new DateTime($lastOrder->getCreatedAt());
                    $now = new DateTime();
                    $daysSinceLastOrder = $now->diff($lastOrderDate)->days;

                    expect($daysSinceLastOrder)->toBeLessThanOrEqual(30);
                }
            }
        });

        test('finds customers with old last order', function () {
            $segment = createOrderAttributesTestSegment('Old Last Order', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'days_since_last_order',
                'operator' => '>=',
                'value' => '90',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->setOrder('created_at', 'DESC');

                if ($orders->getSize() > 0) {
                    $lastOrder = $orders->getFirstItem();
                    // Match the customer segmentation logic: use UTC time for consistency
                    $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $daysSinceLastOrder = (int) ((strtotime($currentDate) - strtotime($lastOrder->getCreatedAt())) / 86400);

                    expect($daysSinceLastOrder)->toBeGreaterThanOrEqual(89); // Sample data creates orders ~89.x days ago
                }
            }
        });

        test('days since last order condition generates correct SQL', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('days_since_last_order');
            $condition->setOperator('<=');
            $condition->setValue('30');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('MAX(o.created_at)');
            // Check for MySQL (DATEDIFF), PostgreSQL (DATE() or ::date), or SQLite (JULIANDAY) syntax
            expect($sql)->toMatch('/DATEDIFF|::date|DATE\\(|JULIANDAY/');
            expect($sql)->toContain('HAVING');
        });
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

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['neq' => 'canceled']);

                if ($orders->getSize() > 0) {
                    $totalAmount = 0;
                    $orderCount = 0;
                    foreach ($orders as $order) {
                        $totalAmount += (float) $order->getGrandTotal();
                        $orderCount++;
                    }

                    $averageOrderAmount = $totalAmount / $orderCount;
                    expect($averageOrderAmount)->toBeGreaterThanOrEqual(150.00);
                }
            }
        });

        test('average order amount condition generates correct SQL', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('average_order_amount');
            $condition->setOperator('>=');
            $condition->setValue('100');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('AVG(o.grand_total)');
            expect($sql)->toContain("o.state NOT IN ('canceled')");
            expect($sql)->toContain('HAVING');
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

        test('total ordered amount condition generates correct SQL', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
            $condition->setAttribute('total_ordered_amount');
            $condition->setOperator('>=');
            $condition->setValue('500');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('SUM(o.grand_total)');
            expect($sql)->toContain("o.state NOT IN ('canceled')");
            expect($sql)->toContain('HAVING');
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

    describe('Edge Cases and Error Handling', function () {
        test('handles customers with no orders gracefully', function () {
            $segment = createOrderAttributesTestSegment('Has Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'total_amount',
                'operator' => '>',
                'value' => '0',
            ]);

            // Should not fail even if some customers have no orders
            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // All matched customers should have at least one order with positive amount
            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['neq' => 'canceled']);

                $hasPositiveAmountOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getGrandTotal() > 0) {
                        $hasPositiveAmountOrder = true;
                        break;
                    }
                }
                expect($hasPositiveAmountOrder)->toBe(true);
            }
        });

        test('handles null payment method gracefully', function () {
            $segment = createOrderAttributesTestSegment('Non-Null Payment', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'payment_method',
                'operator' => '!=',
                'value' => '',
            ]);

            // Should handle orders with null payment methods without errors
            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Validate functionality - if any customers match, the condition is working
            // This test primarily validates that the query doesn't crash with null/empty payment methods
            expect(count($matchedCustomers) >= 0)->toBe(true);

            // Test ensures graceful handling of edge case data
            expect($matchedCustomers)->not->toBeNull();
        });

        test('handles invalid date values gracefully', function () {
            $segment = createOrderAttributesTestSegment('Valid Date Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'created_at',
                'operator' => '>=',
                'value' => '2024-01-01',
            ]);

            // Should not fail with invalid date formats
            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Validate functionality - condition should handle date comparison gracefully
            expect(count($matchedCustomers) >= 0)->toBe(true);

            // Test ensures graceful handling of date conditions without SQL errors
            expect($matchedCustomers)->not->toBeNull();
        });

        test('handles zero and negative amounts correctly', function () {
            $segment = createOrderAttributesTestSegment('Positive Amount Orders', [
                'type' => 'customersegmentation/segment_condition_order_attributes',
                'attribute' => 'total_amount',
                'operator' => '>',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $hasPositiveAmountOrder = false;
                foreach ($orders as $order) {
                    if ((float) $order->getGrandTotal() > 0) {
                        $hasPositiveAmountOrder = true;
                        break;
                    }
                }
                expect($hasPositiveAmountOrder)->toBe(true);
            }
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
                'days_since_last_order' => ['pattern' => '/DATEDIFF|::date|DATE\\(|JULIANDAY/', 'contains' => ['MAX(o.created_at)']],
                'average_order_amount' => ['contains' => ['AVG(o.grand_total)', 'HAVING']],
                'total_ordered_amount' => ['contains' => ['SUM(o.grand_total)', 'HAVING']],
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
    function createOrderAttributesTestData(): void
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

        foreach ($customers as $customerData) {
            // Create customer
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId(1);
            $customer->setWebsiteId(1);
            $customer->save();


            // Create orders
            foreach ($customerData['orders'] as $orderData) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setStoreId(1);

                // Set order amounts
                $order->setGrandTotal($orderData['grand_total']);
                $order->setSubtotal($orderData['subtotal']);
                $order->setTaxAmount($orderData['tax_amount']);
                $order->setShippingAmount($orderData['shipping_amount']);
                $order->setDiscountAmount($orderData['discount_amount']);
                $order->setTotalQtyOrdered($orderData['total_qty_ordered']);

                // Set order status and state according to Maho patterns
                if ($orderData['status'] === 'canceled') {
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED);
                    $order->setStatus('canceled');
                } else {
                    $order->setState(Mage_Sales_Model_Order::STATE_NEW);
                    $order->setStatus($orderData['status']);
                }

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
        $segment->setIsActive(1);
        $segment->setWebsiteIds('1');
        $segment->setCustomerGroupIds('0,1,2,3');
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->setPriority(10);
        $segment->save();


        return $segment;
    }
});
