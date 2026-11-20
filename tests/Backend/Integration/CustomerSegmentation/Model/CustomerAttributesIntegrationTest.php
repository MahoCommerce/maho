<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Attributes Integration Tests', function () {
    beforeEach(function () {
        $this->customers = createCustomerAttributesTestData();
    });

    describe('Birthday Calculations (days_until_birthday)', function () {
        test('matches the customers whose birthday is in the given number of days', function (string $days, string $member) {
            $segment = createCustomerAttributesTestSegment('Birthday in ' . $days . ' days', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_until_birthday',
                'operator' => '==',
                'value' => $days,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers[$member])
                ->and($matchedCustomers)->not->toContain($this->customers['No Birthday']);

            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('days_until_birthday');
            $condition->setOperator('==');
            $condition->setValue($days);
            $sql = $condition->getConditionsSql(Mage::getSingleton('core/resource')->getConnection('core_read'));
            // MySQL uses DATEDIFF, PostgreSQL DATE() or ::date, SQLite JULIANDAY
            expect($sql)->toContain('CASE')
                ->and($sql)->toMatch('/DATEDIFF|::date|DATE\\(|JULIANDAY/');

            $fixtureDob = Mage::getModel('customer/customer')->load($this->customers[$member])->getDob();
            $monthDay = date('m-d', strtotime($fixtureDob));
            foreach ($matchedCustomers as $customerId) {
                $dob = Mage::getModel('customer/customer')->load($customerId)->getDob();
                expect($dob)->not->toBeEmpty();

                $dobMonthDay = date('m-d', strtotime($dob));
                // In a year that is not a leap year, the engines move a Feb 29 birthday to Feb 28 or Mar 1
                if ($dobMonthDay === '02-29' && date('L') !== '1') {
                    continue;
                }
                expect($dobMonthDay)->toBe($monthDay, "Customer {$customerId} DOB {$dob}");
            }
        })->with([
            'today' => ['0', 'Birthday Today'],
            'tomorrow' => ['1', 'Birthday Tomorrow'],
        ]);

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
        test('matches the customers by the days since their registration', function (string $operator, string $value, string $member) {
            $segment = createCustomerAttributesTestSegment('Registered ' . $operator . ' ' . $value, [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'days_since_registration',
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers[$member]);

            // The segment counts calendar days in UTC, like DATEDIFF
            $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
            foreach ($matchedCustomers as $customerId) {
                $createdAt = (string) Mage::getModel('customer/customer')->load($customerId)->getCreatedAt();
                $days = $today->diff(new DateTimeImmutable(substr($createdAt, 0, 10), new DateTimeZone('UTC')))->days;
                $passes = match ($operator) {
                    '==' => $days === (int) $value,
                    '>=' => $days >= (int) $value,
                    '<=' => $days <= (int) $value,
                };
                expect($passes)->toBeTrue("Customer {$customerId} registered {$days} days ago");
            }
        })->with([
            'today' => ['==', '0', 'New Customer'],
            'one year or more' => ['>=', '365', 'John BigSpender'],
            'within 30 days' => ['<=', '30', 'New Customer'],
        ]);
    });

    describe('Customer Demographics and EAV Attributes', function () {
        $registeredSince = date('Y-m-d', strtotime('-1 year'));

        test('matches the customers by an attribute', function (string $attribute, string $operator, string $value, ?string $member, Closure $passes) {
            $segment = createCustomerAttributesTestSegment('Attribute ' . $attribute, [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => $attribute,
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            if ($member !== null) {
                expect($matchedCustomers)->toContain($this->customers[$member]);
            }

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                expect($passes($customer))->toBeTrue("Customer {$customerId} does not pass {$attribute} {$operator} {$value}");
            }
        })->with([
            'gender' => ['gender', '==', '1', 'Birthday Today', fn($c) => (int) $c->getGender() === 1],
            'first name' => ['firstname', '==', 'John', 'John BigSpender', fn($c) => $c->getFirstname() === 'John'],
            'customer group' => ['group_id', '==', '2', 'Birthday Yesterday', fn($c) => (int) $c->getGroupId() === 2],
            'date of birth' => ['dob', '==', '1990-06-15', 'Single Purchase', fn($c) => !empty($c->getDob()) && date('Y-m-d', strtotime($c->getDob())) === '1990-06-15'],
            'registered in the last year' => ['created_at', '>=', $registeredSince, 'New Customer', fn($c) => date('Y-m-d', strtotime($c->getCreatedAt())) >= $registeredSince],
            'registered since 2020' => ['created_at', '>=', '2020-01-01', 'New Customer', fn($c) => date('Y-m-d', strtotime($c->getCreatedAt())) >= '2020-01-01'],
            'store view' => ['store_id', '==', '1', null, fn($c) => (int) $c->getStoreId() === 1],
        ]);
    });

    describe('Edge Cases and Error Handling', function () {
        test('leaves the customers without a gender out of a gender that is not male', function () {
            $segment = createCustomerAttributesTestSegment('Unspecified Gender', [
                'type' => 'customersegmentation/segment_condition_customer_attributes',
                'attribute' => 'gender',
                'operator' => '!=',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            expect($matchedCustomers)->toContain($this->customers['Birthday Tomorrow'])
                ->and($matchedCustomers)->not->toContain($this->customers['No Birthday'])
                ->and($matchedCustomers)->not->toContain($this->customers['Birthday Today']);
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
    /**
     * @return array<string, int> customer IDs keyed by "firstname lastname"
     */
    function createCustomerAttributesTestData(): array
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

        $ids = [];
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
            $ids[$customerData['firstname'] . ' ' . $customerData['lastname']] = (int) $customer->getId();

            // Create orders if specified
            foreach ($customerData['orders'] as $orderData) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setGrandTotal($orderData['total']);
                $order->setBaseGrandTotal($orderData['total']);

                $order->setData('state', orderStateForStatus($orderData['status']));
                $order->setStatus($orderData['status']);

                $order->setStoreId(1);
                $orderCreatedAt = date('Y-m-d H:i:s', strtotime("-{$orderData['days_ago']} days"));
                $order->setCreatedAt($orderCreatedAt);
                $order->save();

            }
        }

        return $ids;
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
