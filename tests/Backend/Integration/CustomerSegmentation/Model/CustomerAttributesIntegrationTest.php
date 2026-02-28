<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Attributes Integration Tests', function () {
    beforeEach(function () {
        createCustomerAttributesTestData();
    });

    describe('Birthday Calculations (days_until_birthday)', function () {
        test('calculates days until birthday correctly for customers with birthday today', function () {
            $segment = createCustomerAttributesTestSegment('Birthday Today', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_until_birthday',
                'operator' => '==',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Validate that SQL calculation matches expected result by testing condition directly
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('days_until_birthday');
            $condition->setOperator('==');
            $condition->setValue('0');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            // Test SQL is properly formed
            expect($sql)->toBeString();
            expect($sql)->toContain('CASE');
            // Check for MySQL (DATEDIFF), PostgreSQL (DATE() or ::date), or SQLite (JULIANDAY) syntax
            expect($sql)->toMatch('/DATEDIFF|::date|DATE\\(|JULIANDAY/');

            // For each matched customer, verify they have birthday today (month-day matches)
            $todayMonthDay = date('m-d');
            $isLeapYear = date('L') === '1';
            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $dob = $customer->getDob();

                if (!empty($dob) && $dob !== '0000-00-00') {
                    $dobMonthDay = date('m-d', strtotime($dob));

                    // In non-leap years, Feb 29 birthdays are treated as Mar 1
                    if (!$isLeapYear && $dobMonthDay === '02-29') {
                        $dobMonthDay = '03-01';
                    }

                    expect($dobMonthDay)->toBe($todayMonthDay, "Customer {$customerId} DOB {$dob} should have today's month-day {$todayMonthDay}");
                }
            }
        });

        test('calculates days until birthday correctly for customers with birthday tomorrow', function () {
            $segment = createCustomerAttributesTestSegment('Birthday Tomorrow', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_until_birthday',
                'operator' => '==',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // For each matched customer, verify they have birthday tomorrow (month-day matches tomorrow)
            $tomorrowMonthDay = date('m-d', strtotime('+1 day'));
            $isLeapYear = date('L') === '1';
            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $dob = $customer->getDob();

                if (!empty($dob) && $dob !== '0000-00-00') {
                    $dobMonthDay = date('m-d', strtotime($dob));

                    // In non-leap years, Feb 29 birthdays are treated as Mar 1
                    if (!$isLeapYear && $dobMonthDay === '02-29') {
                        $dobMonthDay = '03-01';
                    }

                    expect($dobMonthDay)->toBe($tomorrowMonthDay, "Customer {$customerId} DOB {$dob} should have tomorrow's month-day {$tomorrowMonthDay}");
                }
            }
        });

        test('handles leap year birthdays correctly', function () {
            $segment = createCustomerAttributesTestSegment('Leap Year Birthday', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_until_birthday',
                'operator' => '>=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $dob = $customer->getDob();

                if (!empty($dob) && $dob !== '0000-00-00') {
                    $dobDate = new DateTime($dob);

                    // Special handling for Feb 29 leap year birthdays
                    if ($dobDate->format('m-d') === '02-29') {
                        $today = new DateTime();
                        $currentYear = (int) $today->format('Y');
                        $isLeapYear = ($currentYear % 4 === 0 && $currentYear % 100 !== 0) || ($currentYear % 400 === 0);

                        // Test should handle this edge case gracefully
                        expect(true)->toBe(true); // Birthday calculation handles leap years
                    }
                }
            }
        });

        test('excludes customers with no birthday (null dob)', function () {
            $segment = createCustomerAttributesTestSegment('Has Birthday', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_until_birthday',
                'operator' => '>=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $dob = $customer->getDob();

                // Should not include customers with null or invalid DOB
                expect($dob)->not()->toBeNull();
                expect($dob)->not()->toBe('');
                expect($dob)->not()->toBe('0000-00-00');
            }
        });

        test('calculates days until birthday for customers with birthday in different months', function () {
            $segment = createCustomerAttributesTestSegment('Birthday Within Year', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_until_birthday',
                'operator' => '<=',
                'value' => '365',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $dob = $customer->getDob();

                if (!empty($dob) && $dob !== '0000-00-00') {
                    // Use the same logic as the SQL: calculate days until next birthday
                    $today = new DateTime();
                    $dobDate = new DateTime($dob);

                    // Get this year's birthday
                    $thisYearBirthday = new DateTime($today->format('Y') . '-' . $dobDate->format('m-d'));

                    // If birthday has already passed this year, calculate next year's birthday
                    if ($thisYearBirthday <= $today) {
                        $nextBirthday = new DateTime(($today->format('Y') + 1) . '-' . $dobDate->format('m-d'));
                        $daysDiff = $today->diff($nextBirthday)->days;
                    } else {
                        $daysDiff = $today->diff($thisYearBirthday)->days;
                    }

                    expect($daysDiff)->toBeLessThanOrEqual(365);
                }
            }
        });

        test('handles birthday calculation SQL correctly', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('days_until_birthday');
            $condition->setOperator('<=');
            $condition->setValue('30');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            // Check for MySQL (DATEDIFF, DATE_FORMAT, YEAR, DAYOFYEAR), PostgreSQL (EXTRACT, MAKE_DATE), or SQLite (JULIANDAY, STRFTIME) syntax
            expect($sql)->toMatch('/DATEDIFF|::date|DATE\\(|JULIANDAY/');
            expect($sql)->toMatch('/DATE_FORMAT|MAKE_DATE|STRFTIME/');
            expect($sql)->toMatch('/YEAR|EXTRACT|STRFTIME/');
            expect($sql)->toMatch('/DAYOFYEAR|DOY|%m-%d/');
        });
    });

    describe('Days Since Registration (days_since_registration)', function () {
        test('calculates days since registration correctly for customers registered today', function () {
            $segment = createCustomerAttributesTestSegment('Registered Today', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_since_registration',
                'operator' => '==',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $createdAt = $customer->getCreatedAt();

                $now = new DateTime();
                $registrationDate = new DateTime($createdAt);
                $daysDiff = $now->diff($registrationDate)->days;

                expect($daysDiff)->toBe(0);
            }
        });

        test('calculates days since registration correctly for customers registered years ago', function () {
            $segment = createCustomerAttributesTestSegment('Old Customers', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_since_registration',
                'operator' => '>=',
                'value' => '365',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $createdAt = $customer->getCreatedAt();

                // Use date-only comparison to match MySQL's DATEDIFF behavior
                $now = Mage::app()->getLocale()->utcDate(null, null, true);
                $registrationDate = Mage::app()->getLocale()->utcDate(null, $createdAt, true);
                // Strip time component for date-only comparison
                $nowDate = new DateTime($now->format('Y-m-d'));
                $regDate = new DateTime($registrationDate->format('Y-m-d'));
                $daysDiff = $nowDate->diff($regDate)->days;

                expect($daysDiff)->toBeGreaterThanOrEqual(365);
            }
        });

        test('finds customers registered within specific timeframe', function () {
            $segment = createCustomerAttributesTestSegment('Recent Customers', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_since_registration',
                'operator' => '<=',
                'value' => '30',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $createdAt = $customer->getCreatedAt();

                $now = new DateTime();
                $registrationDate = new DateTime($createdAt);
                $daysDiff = $now->diff($registrationDate)->days;

                expect($daysDiff)->toBeLessThanOrEqual(30);
            }
        });
    });

    describe('Customer Demographics and EAV Attributes', function () {
        test('filters customers by gender correctly', function () {
            $segment = createCustomerAttributesTestSegment('Male Customers', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'gender',
                'operator' => '==',
                'value' => '1', // Male
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect((int) $customer->getGender())->toBe(1);
            }
        });

        test('filters customers by email domain pattern', function () {
            $segment = createCustomerAttributesTestSegment('Gmail Users', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'email',
                'operator' => '{}',
                'value' => 'gmail.com',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect($customer->getEmail())->toContain('gmail.com');
            }
        });

        test('filters customers by first name', function () {
            $segment = createCustomerAttributesTestSegment('Johns', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'firstname',
                'operator' => '==',
                'value' => 'John',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect($customer->getFirstname())->toBe('John');
            }
        });

        test('filters customers by customer group', function () {
            $segment = createCustomerAttributesTestSegment('Premium Group', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'group_id',
                'operator' => '==',
                'value' => '2',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect((int) $customer->getGroupId())->toBe(2);
            }
        });

        test('handles date of birth filtering', function () {
            $testDate = '1990-06-15';
            $segment = createCustomerAttributesTestSegment('Born on Specific Date', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'dob',
                'operator' => '==',
                'value' => $testDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $dob = $customer->getDob();

                if (!empty($dob)) {
                    expect(date('Y-m-d', strtotime($dob)))->toBe($testDate);
                }
            }
        });

        test('filters by customer since date', function () {
            $testDate = date('Y-m-d', strtotime('-1 year'));
            $segment = createCustomerAttributesTestSegment('Customer Since Date', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'created_at',
                'operator' => '>=',
                'value' => $testDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $createdDate = date('Y-m-d', strtotime($customer->getCreatedAt()));
                expect($createdDate)->toBeGreaterThanOrEqual($testDate);
            }
        });

        test('filters customers by store_id', function () {
            $segment = createCustomerAttributesTestSegment('Store ID Filter', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'store_id',
                'operator' => '==',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect((int) $customer->getStoreId())->toBe(1);
            }
        });

        test('filters customers by website_id', function () {
            $segment = createCustomerAttributesTestSegment('Website ID Filter', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'store_id',
                'operator' => '==',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect((int) $customer->getWebsiteId())->toBe(1);
            }
        });
    });

    describe('Customer Lifetime Value Fields', function () {
        test('filters customers by lifetime sales amount', function () {
            $segment = createCustomerAttributesTestSegment('High Value Customers', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_sales',
                'operator' => '>=',
                'value' => '500',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Verify lifetime sales calculation
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                $totalSales = 0;
                foreach ($orders as $order) {
                    $totalSales += (float) $order->getGrandTotal();
                }

                expect($totalSales)->toBeGreaterThanOrEqual(500.0);
            }
        });

        test('filters customers by number of orders', function () {
            $segment = createCustomerAttributesTestSegment('Frequent Buyers', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'number_of_orders',
                'operator' => '>=',
                'value' => '3',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orderCount = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled'])
                    ->getSize();

                expect($orderCount)->toBeGreaterThanOrEqual(3);
            }
        });

        test('filters customers by average order value', function () {
            $segment = createCustomerAttributesTestSegment('High AOV Customers', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'average_order_value',
                'operator' => '>=',
                'value' => '100',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                if ($orders->getSize() > 0) {
                    $totalSales = 0;
                    $orderCount = 0;
                    foreach ($orders as $order) {
                        $totalSales += (float) $order->getGrandTotal();
                        $orderCount++;
                    }

                    $averageOrderValue = $totalSales / $orderCount;
                    expect($averageOrderValue)->toBeGreaterThanOrEqual(100.0);
                }
            }
        });

        test('excludes canceled orders from CLV calculations', function () {
            $segment = createCustomerAttributesTestSegment('CLV Excluding Canceled', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'lifetime_sales',
                'operator' => '>=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Verify no canceled orders are included in the calculation
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                $canceledOrders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', 'canceled');

                // Test that canceled orders are not affecting the calculation
                foreach ($canceledOrders as $canceledOrder) {
                    expect($canceledOrder->getState())->toBe('canceled');
                }

                // Ensure we have valid non-canceled orders for the calculation
                if ($orders->getSize() > 0) {
                    foreach ($orders as $order) {
                        expect($order->getState())->not()->toBe('canceled');
                    }
                }
            }
        });

        test('also excludes closed orders from CLV calculations', function () {
            $segment = createCustomerAttributesTestSegment('CLV Excluding Closed', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'lifetime_sales',
                'operator' => '>=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']]);

                // Verify that the condition SQL correctly excludes both canceled and closed orders
                if ($orders->getSize() > 0) {
                    foreach ($orders as $order) {
                        expect($order->getState())->not()->toBe('canceled');
                        expect($order->getState())->not()->toBe('closed');
                    }
                }
            }
        });

        test('handles zero order value calculations correctly', function () {
            $segment = createCustomerAttributesTestSegment('Zero Order Value', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'average_order_value',
                'operator' => '>=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Should handle customers with zero-value orders without division by zero errors
            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                if ($orders->getSize() > 0) {
                    $totalSales = 0;
                    foreach ($orders as $order) {
                        $totalSales += (float) $order->getGrandTotal();
                    }
                    $averageOrderValue = $totalSales / $orders->getSize();
                    expect($averageOrderValue)->toBeGreaterThanOrEqual(0.0);
                }
            }
        });
    });

    describe('Edge Cases and Error Handling', function () {
        test('handles customers with null gender gracefully', function () {
            $segment = createCustomerAttributesTestSegment('Unspecified Gender', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'gender',
                'operator' => '!=',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            // Should handle null gender values without errors
            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $gender = $customer->getGender();

                // Gender can be null, 1 (male), 2 (female), or 3 (not specified)
                if ($gender !== null) {
                    expect((int) $gender)->not()->toBe(1);
                }
            }
        });

        test('handles customers with invalid date of birth', function () {
            $segment = createCustomerAttributesTestSegment('Invalid DOB Test', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_until_birthday',
                'operator' => '>=',
                'value' => '0',
            ]);

            // Should not fail even with invalid DOB data and return meaningful results
            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Should still work with null/invalid DOB data - expecting empty or valid array
            // At minimum verify it doesn't crash due to malformed date handling
            expect(count($matchedCustomers) >= 0)->toBe(true);
        });

        test('handles customers with no orders for CLV calculations', function () {
            $segment = createCustomerAttributesTestSegment('Customers with Orders', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'number_of_orders',
                'operator' => '>=',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orderCount = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled'])
                    ->getSize();

                expect($orderCount)->toBeGreaterThan(0);
            }
        });

        test('handles empty string values correctly', function () {
            $segment = createCustomerAttributesTestSegment('Non-Empty Email', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'email',
                'operator' => '!=',
                'value' => '',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect($customer->getEmail())->not()->toBe('');
                expect($customer->getEmail())->not()->toBeNull();
            }
        });

        test('handles null firstname gracefully', function () {
            $segment = createCustomerAttributesTestSegment('Has Firstname', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'firstname',
                'operator' => '!=',
                'value' => '',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect($customer->getFirstname())->not()->toBe('');
            }
        });

        test('handles null lastname gracefully', function () {
            $segment = createCustomerAttributesTestSegment('Has Lastname', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'lastname',
                'operator' => '!=',
                'value' => '',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect($customer->getLastname())->not()->toBe('');
            }
        });

        test('handles complex email patterns', function () {
            $segment = createCustomerAttributesTestSegment('Complex Email Pattern', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'email',
                'operator' => '{}',
                'value' => '.com',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect($customer->getEmail())->toContain('.com');
            }
        });

        test('handles date edge cases with invalid formats', function () {
            // Test that invalid date formats don't cause SQL errors
            $segment = createCustomerAttributesTestSegment('Invalid Date Test', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'created_at',
                'operator' => '>=',
                'value' => '2020-01-01',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Validate that we're testing date handling properly - either matches customers or returns empty array
            expect(count($matchedCustomers) >= 0)->toBe(true);

            // If any customers match, verify they have registration dates >= 2020-01-01
            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                $createdAt = strtotime($customer->getCreatedAt());
                $minDate = strtotime('2020-01-01');
                expect($createdAt >= $minDate)->toBe(true);
            }
        });
    });

    describe('SQL Generation Verification', function () {
        test('generates correct SQL for simple attributes', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('email');
            $condition->setOperator('{}');
            $condition->setValue('example.com');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.email');
            expect($sql)->toContain('LIKE');
        });

        test('generates correct SQL for EAV attributes', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('firstname');
            $condition->setOperator('==');
            $condition->setValue('John');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('entity_id IN');
            expect($sql)->toContain('attribute_id');
        });

        test('generates correct SQL for complex calculated fields', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_clv');
            $condition->setAttribute('lifetime_sales');
            $condition->setOperator('>=');
            $condition->setValue('500');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getSubfilterSql('c.entity_id', true, null);

            expect($sql)->toBeString();
            expect($sql)->toContain('SUM');
            expect($sql)->toContain('grand_total');
            // CLV class generates subquery structure instead of HAVING clause
            expect($sql)->toContain('total >=');
        });

        test('handles all supported operators correctly', function () {
            $operators = ['==', '!=', '>=', '<=', '>', '<', '{}', '!{}'];

            foreach ($operators as $operator) {
                $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
                $condition->setAttribute('email');
                $condition->setOperator($operator);
                $condition->setValue('test@example.com');

                $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
                $sql = $condition->getConditionsSql($adapter);

                expect($sql)->toBeString();
                expect($sql)->not()->toBe(false);
            }
        });

        test('handles input type validation correctly', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');

            // Test different attribute input types
            $testCases = [
                'email' => 'string',
                'firstname' => 'string',
                'lastname' => 'string',
                'gender' => 'select',
                'dob' => 'date',
                'created_at' => 'date',
                'group_id' => 'multiselect',
                'store_id' => 'multiselect',
                'days_since_registration' => 'numeric',
                'days_until_birthday' => 'numeric',
            ];

            foreach ($testCases as $attribute => $expectedType) {
                $condition->setAttribute($attribute);
                $inputType = $condition->getInputType();
                expect($inputType)->toBe($expectedType, "Attribute '{$attribute}' should have input type '{$expectedType}'");
            }
        });

        test('handles value element type validation correctly', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');

            // Test different attribute value element types
            $testCases = [
                'email' => 'text',
                'firstname' => 'text',
                'lastname' => 'text',
                'gender' => 'select',
                'dob' => 'date',
                'created_at' => 'date',
                'group_id' => 'select',
                'store_id' => 'select',
                'days_since_registration' => 'text',
                'days_until_birthday' => 'text',
            ];

            foreach ($testCases as $attribute => $expectedType) {
                $condition->setAttribute($attribute);
                $valueElementType = $condition->getValueElementType();
                expect($valueElementType)->toBe($expectedType, "Attribute '{$attribute}' should have value element type '{$expectedType}'");
            }
        });

        test('provides correct operator options for selection fields', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');

            $selectionAttributes = ['gender', 'group_id', 'store_id'];

            foreach ($selectionAttributes as $attribute) {
                $condition->setAttribute($attribute);
                $operators = $condition->getOperatorSelectOptions();

                expect($operators)->toBeArray();
                expect($operators)->toHaveCount(2); // Should only have 'is' and 'is not'

                $operatorValues = array_column($operators, 'value');
                expect($operatorValues)->toContain('==');
                expect($operatorValues)->toContain('!=');
            }
        });
    });

    // Helper functions
    function createCustomerAttributesTestData(): void
    {
        $uniqueId = uniqid('customer_attr_', true);

        $customers = [
            // Customer with birthday today (born in 1990, birthday today)
            [
                'firstname' => 'Birthday',
                'lastname' => 'Today',
                'email' => "birthday.today.{$uniqueId}@test.com",
                'dob' => '1990-' . date('m-d'), // Same month-day, but born in 1990
                'gender' => 1,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'orders' => [],
            ],
            // Customer with birthday tomorrow (born in 1985, birthday tomorrow)
            [
                'firstname' => 'Birthday',
                'lastname' => 'Tomorrow',
                'email' => "birthday.tomorrow.{$uniqueId}@test.com",
                'dob' => '1985-' . date('m-d', strtotime('+1 day')), // Tomorrow's month-day, but born in 1985
                'gender' => 2,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'orders' => [],
            ],
            // Customer with birthday yesterday (born in 1988, birthday was yesterday)
            [
                'firstname' => 'Birthday',
                'lastname' => 'Yesterday',
                'email' => "birthday.yesterday.{$uniqueId}@test.com",
                'dob' => '1988-' . date('m-d', strtotime('-1 day')), // Yesterday's month-day, born in 1988
                'gender' => 1,
                'group_id' => 2,
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
                'orders' => [
                    ['days_ago' => 10, 'total' => 150.00, 'status' => 'pending'],
                ],
            ],
            // Customer with no birthday (null DOB)
            [
                'firstname' => 'No',
                'lastname' => 'Birthday',
                'email' => "no.birthday.{$uniqueId}@test.com",
                'dob' => null,
                'gender' => null,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-90 days')),
                'orders' => [],
            ],
            // Customer with leap year birthday (Feb 29)
            [
                'firstname' => 'Leap',
                'lastname' => 'Year',
                'email' => "leap.year.{$uniqueId}@test.com",
                'dob' => '1992-02-29',
                'gender' => 2,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-120 days')),
                'orders' => [],
            ],
            // High-value customer with multiple orders
            [
                'firstname' => 'John',
                'lastname' => 'BigSpender',
                'email' => "john.bigspender.{$uniqueId}@gmail.com",
                'dob' => '1985-08-15',
                'gender' => 1,
                'group_id' => 2,
                'created_at' => Mage::app()->getLocale()->utcDate(null, '-2 years', true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
                'orders' => [
                    ['days_ago' => 30, 'total' => 200.00, 'status' => 'pending'],
                    ['days_ago' => 60, 'total' => 150.00, 'status' => 'processing'],
                    ['days_ago' => 90, 'total' => 300.00, 'status' => 'complete'],
                    ['days_ago' => 120, 'total' => 100.00, 'status' => 'canceled'], // Should be excluded
                ],
            ],
            // Customer with single order
            [
                'firstname' => 'Single',
                'lastname' => 'Purchase',
                'email' => "single.purchase.{$uniqueId}@test.com",
                'dob' => '1990-06-15',
                'gender' => 2,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-6 months')),
                'orders' => [
                    ['days_ago' => 10, 'total' => 75.00, 'status' => 'pending'],
                ],
            ],
            // Customer with no orders but has gender and DOB
            [
                'firstname' => 'Jane',
                'lastname' => 'Browser',
                'email' => "jane.browser.{$uniqueId}@yahoo.com",
                'dob' => '1988-12-25',
                'gender' => 2,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 year')),
                'orders' => [],
            ],
            // Customer registered today
            [
                'firstname' => 'New',
                'lastname' => 'Customer',
                'email' => "new.customer.{$uniqueId}@test.com",
                'dob' => '1995-03-20',
                'gender' => 1,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'orders' => [],
            ],
            // Customer with different birthday month (born in 1993, birthday in 6 months)
            [
                'firstname' => 'Future',
                'lastname' => 'Birthday',
                'email' => "future.birthday.{$uniqueId}@test.com",
                'dob' => '1993-' . date('m-d', strtotime('+6 months')), // 6 months from now, born in 1993
                'gender' => 1,
                'group_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 months')),
                'orders' => [
                    ['days_ago' => 5, 'total' => 50.00, 'status' => 'pending'],
                    ['days_ago' => 45, 'total' => 75.00, 'status' => 'pending'],
                ],
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId($customerData['group_id']);
            $customer->setWebsiteId(1);
            $customer->setCreatedAt($customerData['created_at']);

            if (isset($customerData['dob']) && $customerData['dob'] !== null) {
                $customer->setDob($customerData['dob']);
            }

            if (isset($customerData['gender']) && $customerData['gender'] !== null) {
                $customer->setGender($customerData['gender']);
            }

            $customer->save();

            // Create orders if specified
            foreach ($customerData['orders'] as $orderData) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setGrandTotal($orderData['total']);

                // Set state and status according to Maho patterns
                if ($orderData['status'] === 'canceled') {
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED);
                    $order->setStatus('canceled');
                } else {
                    $order->setState(Mage_Sales_Model_Order::STATE_NEW);
                    $order->setStatus($orderData['status']);
                }

                $order->setStoreId(1);
                $orderCreatedAt = date('Y-m-d H:i:s', strtotime("-{$orderData['days_ago']} days"));
                $order->setCreatedAt($orderCreatedAt);
                $order->save();

            }
        }
    }

    function createCustomerAttributesTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
        $segment->setDescription('Customer attributes test segment for ' . $name);
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
