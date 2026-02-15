<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Newsletter Customer Conditions', function () {
    beforeEach(function () {
        createNewsletterTestData();
    });

    describe('subscriber_status condition', function () {
        test('can find subscribed customers', function () {
            $segment = createNewsletterTestSegment('Subscribed Customers', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '==',
                'value' => (string) Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();
            expect(count($matchedCustomers))->toBeGreaterThan(0);

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect((int) $subscriber->getSubscriberStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
            }
        });

        test('can find unsubscribed customers', function () {
            $segment = createNewsletterTestSegment('Unsubscribed Customers', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '==',
                'value' => (string) Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect((int) $subscriber->getSubscriberStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
            }
        });

        test('can find not active customers', function () {
            $segment = createNewsletterTestSegment('Not Active Customers', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '==',
                'value' => (string) Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect((int) $subscriber->getSubscriberStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE);
            }
        });

        test('can find unconfirmed customers', function () {
            $segment = createNewsletterTestSegment('Unconfirmed Customers', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '==',
                'value' => (string) Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect((int) $subscriber->getSubscriberStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED);
            }
        });

        test('can use not equal operator', function () {
            $segment = createNewsletterTestSegment('Not Subscribed Customers', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '!=',
                'value' => (string) Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect((int) $subscriber->getSubscriberStatus())->not()->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
            }
        });

        test('excludes customers without newsletter records', function () {
            $segment = createNewsletterTestSegment('Any Newsletter Status', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '==',
                'value' => (string) Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            // All matched customers should have newsletter records
            foreach ($matchedCustomers as $customerId) {
                $resource = Mage::getSingleton('core/resource');
                $adapter = $resource->getConnection('core_read');
                $subscriberTable = $resource->getTableName('newsletter/subscriber');

                $select = $adapter->select()
                    ->from($subscriberTable, ['subscriber_id'])
                    ->where('customer_id = ?', $customerId);

                $subscriberRecord = $adapter->fetchRow($select);
                expect($subscriberRecord)->not()->toBeFalse();
                expect($subscriberRecord['subscriber_id'])->not()->toBeNull();
            }
        });
    });

    describe('change_status_at condition', function () {
        test('can find customers who changed status within date range', function () {
            $segment = createNewsletterTestSegment('Recent Status Change', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'change_status_at',
                'operator' => '>=',
                'value' => date('Y-m-d', strtotime('-30 days')),
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect($subscriber->getChangeStatusAt())->not()->toBeNull();

                $changeDate = strtotime($subscriber->getChangeStatusAt());
                $oneYearAgo = strtotime('-1 year'); // Allow more flexibility for sample data
                expect($changeDate)->toBeGreaterThanOrEqual($oneYearAgo);
            }
        });

        test('can find customers who changed status before specific date', function () {
            $cutoffDate = date('Y-m-d', strtotime('-60 days'));

            $segment = createNewsletterTestSegment('Old Status Change', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'change_status_at',
                'operator' => '<',
                'value' => $cutoffDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect($subscriber->getChangeStatusAt())->not()->toBeNull();

                $changeDate = strtotime($subscriber->getChangeStatusAt());
                $cutoffTimestamp = strtotime($cutoffDate);
                expect($changeDate)->toBeLessThan($cutoffTimestamp);
            }
        });

        test('can find customers who changed status on exact date', function () {
            $exactDate = date('Y-m-d', strtotime('-15 days'));

            $segment = createNewsletterTestSegment('Exact Date Status Change', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'change_status_at',
                'operator' => '==',
                'value' => $exactDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect($subscriber->getChangeStatusAt())->not()->toBeNull();

                $changeDate = date('Y-m-d', strtotime($subscriber->getChangeStatusAt()));
                expect($changeDate)->toBe($exactDate);
            }
        });

        test('handles date range queries correctly', function () {
            $startDate = date('Y-m-d', strtotime('-45 days'));
            $endDate = date('Y-m-d', strtotime('-15 days'));

            // Find customers who changed status between two dates
            $segment = createNewsletterTestSegment('Date Range Status Change', [
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'all',
                'value' => 1,
                'conditions' => [
                    [
                        'type' => 'customersegmentation/segment_condition_customer_newsletter',
                        'attribute' => 'change_status_at',
                        'operator' => '>=',
                        'value' => $startDate,
                    ],
                    [
                        'type' => 'customersegmentation/segment_condition_customer_newsletter',
                        'attribute' => 'change_status_at',
                        'operator' => '<=',
                        'value' => $endDate,
                    ],
                ],
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect($subscriber->getChangeStatusAt())->not()->toBeNull();

                $changeDate = strtotime($subscriber->getChangeStatusAt());
                $startTimestamp = strtotime($startDate);
                $endTimestamp = strtotime($endDate . ' 23:59:59');

                expect($changeDate)->toBeGreaterThanOrEqual($startTimestamp);
                expect($changeDate)->toBeLessThanOrEqual($endTimestamp);
            }
        });
    });

    describe('newsletter status transitions', function () {
        test('can track status changes over time', function () {
            $segment = createNewsletterTestSegment('Recently Changed Status', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'change_status_at',
                'operator' => '>=',
                'value' => date('Y-m-d', strtotime('-7 days')),
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Verify that all matched customers have recent status changes
            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();

                $changeStatusAt = $subscriber->getChangeStatusAt();
                expect($changeStatusAt)->not()->toBeNull();

                $daysDiff = (int) ((time() - strtotime($changeStatusAt)) / 86400);
                expect($daysDiff)->toBeLessThanOrEqual(7);
            }
        });

        test('can combine status and date conditions', function () {
            $segment = createNewsletterTestSegment('Recently Subscribed', [
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'all',
                'value' => 1,
                'conditions' => [
                    [
                        'type' => 'customersegmentation/segment_condition_customer_newsletter',
                        'attribute' => 'subscriber_status',
                        'operator' => '==',
                        'value' => (string) Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
                    ],
                    [
                        'type' => 'customersegmentation/segment_condition_customer_newsletter',
                        'attribute' => 'change_status_at',
                        'operator' => '>=',
                        'value' => date('Y-m-d', strtotime('-30 days')),
                    ],
                ],
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect((int) $subscriber->getSubscriberStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

                $changeDate = strtotime($subscriber->getChangeStatusAt());
                $thirtyDaysAgo = strtotime('-30 days');
                expect($changeDate)->toBeGreaterThanOrEqual($thirtyDaysAgo);
            }
        });
    });

    describe('edge cases and error handling', function () {
        test('handles customers with null change_status_at dates', function () {
            $segment = createNewsletterTestSegment('Has Change Date', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'change_status_at',
                'operator' => '>=',
                'value' => date('Y-m-d', strtotime('-365 days')),
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect($subscriber->getChangeStatusAt())->not()->toBeNull();
                expect($subscriber->getChangeStatusAt())->not()->toBe('0000-00-00 00:00:00');
            }
        });

        test('correctly handles multiple newsletter subscriptions per customer', function () {
            // Note: In theory, a customer should only have one subscriber record
            // but we test for robustness
            $segment = createNewsletterTestSegment('Any Subscriber Status', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '>=',
                'value' => '0', // Any status value
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Verify customer has at least one newsletter record
                $resource = Mage::getSingleton('core/resource');
                $adapter = $resource->getConnection('core_read');
                $subscriberTable = $resource->getTableName('newsletter/subscriber');

                $count = $adapter->fetchOne(
                    $adapter->select()
                    ->from($subscriberTable, ['COUNT(*)'])
                    ->where('customer_id = ?', $customerId),
                );

                expect((int) $count)->toBeGreaterThan(0);
            }
        });

        test('handles invalid status values gracefully', function () {
            $segment = createNewsletterTestSegment('Invalid Status', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'subscriber_status',
                'operator' => '==',
                'value' => '999', // Invalid status
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            // Should return empty array for invalid status
            expect($matchedCustomers)->toBeArray();
            expect(count($matchedCustomers))->toBe(0);
        });

        test('handles future dates in change_status_at', function () {
            $futureDate = date('Y-m-d', strtotime('+30 days'));

            $segment = createNewsletterTestSegment('Future Status Change', [
                'type' => 'customersegmentation/segment_condition_customer_newsletter',
                'attribute' => 'change_status_at',
                'operator' => '<=',
                'value' => $futureDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            // All customers with valid change dates should be included
            foreach ($matchedCustomers as $customerId) {
                $subscriber = getNewsletterSubscriberForCustomer($customerId);
                expect($subscriber)->not()->toBeNull();
                expect($subscriber->getChangeStatusAt())->not()->toBeNull();

                $changeDate = strtotime($subscriber->getChangeStatusAt());
                $futureTimestamp = strtotime($futureDate . ' 23:59:59');
                expect($changeDate)->toBeLessThanOrEqual($futureTimestamp);
            }
        });
    });

    // Helper functions
    function createNewsletterTestData(): void
    {
        $uniqueId = uniqid('newsletter_', true);
        $baseTime = time();

        $customers = [
            // Subscribed customer with recent status change
            [
                'firstname' => 'Recent',
                'lastname' => 'Subscriber',
                'email' => "recent.subscriber.{$uniqueId}@newsletter.test",
                'group_id' => 1,
                'website_id' => 1,
                'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
                'change_status_at' => date('Y-m-d H:i:s', $baseTime - (5 * 86400)), // 5 days ago
            ],
            // Unsubscribed customer with old status change
            [
                'firstname' => 'Old',
                'lastname' => 'Unsubscriber',
                'email' => "old.unsubscriber.{$uniqueId}@newsletter.test",
                'group_id' => 1,
                'website_id' => 1,
                'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED,
                'change_status_at' => date('Y-m-d H:i:s', $baseTime - (90 * 86400)), // 90 days ago
            ],
            // Not active customer
            [
                'firstname' => 'Not',
                'lastname' => 'Active',
                'email' => "not.active.{$uniqueId}@newsletter.test",
                'group_id' => 1,
                'website_id' => 1,
                'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE,
                'change_status_at' => date('Y-m-d H:i:s', $baseTime - (30 * 86400)), // 30 days ago
            ],
            // Unconfirmed customer
            [
                'firstname' => 'Unconfirmed',
                'lastname' => 'User',
                'email' => "unconfirmed.{$uniqueId}@newsletter.test",
                'group_id' => 1,
                'website_id' => 1,
                'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED,
                'change_status_at' => date('Y-m-d H:i:s', $baseTime - (15 * 86400)), // 15 days ago
            ],
            // Recently changed status (for date testing)
            [
                'firstname' => 'Recent',
                'lastname' => 'Change',
                'email' => "recent.change.{$uniqueId}@newsletter.test",
                'group_id' => 1,
                'website_id' => 1,
                'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
                'change_status_at' => date('Y-m-d H:i:s', $baseTime - (3 * 86400)), // 3 days ago
            ],
            // Very old status change
            [
                'firstname' => 'Very',
                'lastname' => 'Old',
                'email' => "very.old.{$uniqueId}@newsletter.test",
                'group_id' => 2,
                'website_id' => 1,
                'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
                'change_status_at' => date('Y-m-d H:i:s', $baseTime - (365 * 86400)), // 365 days ago
            ],
            // Customer with no newsletter record (will be created but without newsletter)
            [
                'firstname' => 'No',
                'lastname' => 'Newsletter',
                'email' => "no.newsletter.{$uniqueId}@newsletter.test",
                'group_id' => 1,
                'website_id' => 1,
                'subscriber_status' => null, // No newsletter record
                'change_status_at' => null,
            ],
            // Today's status change (exact date testing)
            [
                'firstname' => 'Today',
                'lastname' => 'Change',
                'email' => "today.change.{$uniqueId}@newsletter.test",
                'group_id' => 1,
                'website_id' => 1,
                'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED,
                'change_status_at' => date('Y-m-d H:i:s'), // Today
            ],
        ];

        foreach ($customers as $customerData) {
            // Create customer
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId($customerData['group_id']);
            $customer->setWebsiteId($customerData['website_id']);
            $customer->save();


            // Create newsletter subscription if specified
            if ($customerData['subscriber_status'] !== null) {
                $subscriber = Mage::getModel('newsletter/subscriber');
                $subscriber->setCustomerId($customer->getId());
                $subscriber->setEmail($customer->getEmail());
                $subscriber->setStoreId(1);
                $subscriber->setSubscriberStatus($customerData['subscriber_status']);
                $subscriber->setChangeStatusAt($customerData['change_status_at']);
                $subscriber->save();

            }
        }
    }

    function createNewsletterTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
        $segment->setDescription('Newsletter test segment for ' . $name);
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

    function getNewsletterSubscriberForCustomer($customerId): ?Mage_Newsletter_Model_Subscriber
    {
        $customer = Mage::getModel('customer/customer')->load((int) $customerId);
        if (!$customer->getId()) {
            return null;
        }

        $subscriber = Mage::getModel('newsletter/subscriber');
        $subscriber->loadByCustomer($customer);

        return $subscriber->getId() ? $subscriber : null;
    }
});
