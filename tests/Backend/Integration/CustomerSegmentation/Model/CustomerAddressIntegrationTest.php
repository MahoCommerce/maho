<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Address Integration Tests', function () {
    beforeEach(function () {
        $this->customers = createCustomerAddressTestData();
    });

    describe('Basic Address Attribute Filtering', function () {
        test('matches the customers by a field of one of their addresses', function (string $attribute, string $operator, string $value, string $member, Closure $matches) {
            $segment = createCustomerAddressTestSegment('Address ' . $attribute, [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => $attribute,
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers[$member]);

            foreach ($matchedCustomers as $customerId) {
                $hasMatchingAddress = false;
                foreach (getCustomerAddresses((int) $customerId) as $address) {
                    if ($matches($address)) {
                        $hasMatchingAddress = true;
                        break;
                    }
                }
                expect($hasMatchingAddress)->toBe(true, "Customer {$customerId} has no address with {$attribute} {$operator} {$value}");
            }
        })->with([
            'first name' => ['firstname', '==', 'John', 'Single', fn($a) => $a->getFirstname() === 'John'],
            'last name' => ['lastname', '==', 'Smith', 'Single', fn($a) => $a->getLastname() === 'Smith'],
            'company' => ['company', '{}', 'Acme', 'Single', fn($a) => str_contains($a->getCompany() ?? '', 'Acme')],
            'street' => ['street', '{}', 'Main St', 'Single', fn($a) => str_contains($a->getStreetFull() ?? '', 'Main St')],
            'city' => ['city', '==', 'New York', 'Single', fn($a) => $a->getCity() === 'New York'],
            'postcode' => ['postcode', '{}', '10001', 'Single', fn($a) => str_contains($a->getPostcode() ?? '', '10001')],
            'telephone' => ['telephone', '{}', '555-0123', 'Single', fn($a) => str_contains($a->getTelephone() ?? '', '555-0123')],
            'US country' => ['country_id', '==', 'US', 'Single', fn($a) => $a->getCountryId() === 'US'],
            'Canadian country' => ['country_id', '==', 'CA', 'Canadian', fn($a) => $a->getCountryId() === 'CA'],
            'region text' => ['region', '==', 'Free Region Name', 'Single', fn($a) => $a->getRegion() === 'Free Region Name'],
            'UK region text' => ['region', '==', 'London', 'British', fn($a) => $a->getRegion() === 'London'],
        ]);
    });

    describe('Complex Region Logic Testing', function () {
        test('matches customers by directory region name lookup', function () {
            // First get a valid region ID and name from the directory
            $regionCollection = Mage::getResourceModel('directory/region_collection')
                ->addCountryFilter('US')
                ->setPageSize(1);

            $region = $regionCollection->getFirstItem();
            if (!$region || !$region->getId()) {
                $this->markTestSkipped('No regions found in directory for testing');
                return;
            }

            $regionName = $region->getName();

            $segment = createCustomerAddressTestSegment('Directory Region', [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => 'region',
                'operator' => '==',
                'value' => $regionName,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $addresses = getCustomerAddresses((int) $customerId);
                $hasMatchingAddress = false;
                foreach ($addresses as $address) {
                    // Check if address region matches either by text or directory lookup
                    $regionMatch = false;
                    if ($address->getRegion() === $regionName) {
                        $regionMatch = true;
                    } elseif ($address->getRegionId()) {
                        $addressRegion = Mage::getModel('directory/region')->load($address->getRegionId());
                        if ($addressRegion->getName() === $regionName) {
                            $regionMatch = true;
                        }
                    }
                    if ($regionMatch) {
                        $hasMatchingAddress = true;
                        break;
                    }
                }
                expect($hasMatchingAddress)->toBe(true);
            }
        });

        test('region condition uses OR logic for both text and directory lookup', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');
            $condition->setAttribute('region');
            $condition->setOperator('==');
            $condition->setValue('California');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('region_attr');
            expect($sql)->toContain('dr.default_name');
            expect($sql)->toContain(' OR ');
            expect($sql)->toContain('LEFT JOIN');
        });
    });

    describe('Multiple Addresses per Customer', function () {
        test('matches customers with multiple addresses when one matches', function () {
            $segment = createCustomerAddressTestSegment('Multi Address Match', [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => 'city',
                'operator' => '==',
                'value' => 'Los Angeles',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            expect($matchedCustomers)->toContain($this->customers['Multiple'])
                ->and($matchedCustomers)->not->toContain($this->customers['Single']);
        });

        test('does not match customers when none of their addresses match', function () {
            $segment = createCustomerAddressTestSegment('No Address Match', [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => 'city',
                'operator' => '==',
                'value' => 'Nonexistent City',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();
            expect(count($matchedCustomers))->toBe(0);
        });
    });

    describe('Edge Cases and Error Handling', function () {
        test('excludes customers with no addresses', function () {
            $segment = createCustomerAddressTestSegment('Has Addresses', [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => 'city',
                'operator' => '!=',
                'value' => '',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $addresses = getCustomerAddresses((int) $customerId);
                expect(count($addresses))->toBeGreaterThan(0);
            }
        });

        test('handles empty field values gracefully', function () {
            $segment = createCustomerAddressTestSegment('Non-Empty Company', [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => 'company',
                'operator' => '!=',
                'value' => '',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $addresses = getCustomerAddresses((int) $customerId);
                $hasNonEmptyCompany = false;
                foreach ($addresses as $address) {
                    if (!empty($address->getCompany())) {
                        $hasNonEmptyCompany = true;
                        break;
                    }
                }
                expect($hasNonEmptyCompany)->toBe(true);
            }
        });

        test('matches nobody when a field must not contain an empty value', function () {
            $segment = createCustomerAddressTestSegment('Has Telephone', [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => 'telephone',
                'operator' => '!{}',
                'value' => null,
            ]);

            expect($segment->getMatchingCustomerIds())->toBe([]);
        });

        test('handles pattern matching with special characters', function () {
            $segment = createCustomerAddressTestSegment('Special Chars', [
                'type' => 'customersegmentation/segment_condition_customer_address',
                'attribute' => 'street',
                'operator' => '{}',
                'value' => "St. John's",
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            expect($matchedCustomers)->toContain($this->customers['Special'])
                ->and($matchedCustomers)->not->toContain($this->customers['Single']);
        });
    });

    describe('SQL Generation Verification', function () {
        test('generates correct SQL for basic address attributes', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');
            $condition->setAttribute('city');
            $condition->setOperator('==');
            $condition->setValue('New York');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('attr.value');
            expect($sql)->toContain('customer_address_entity');
        });

        test('generates correct SQL for region with JOIN', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');
            $condition->setAttribute('region');
            $condition->setOperator('{}');
            $condition->setValue('California');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('LEFT JOIN');
            expect($sql)->toContain('directory_country_region');
            expect($sql)->toContain('rid_attr.value = dr.region_id');
        });

        test('handles multiple operators correctly', function () {
            $operators = ['==', '!=', '{}', '!{}', '()', '!()'];

            foreach ($operators as $operator) {
                $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');
                $condition->setAttribute('firstname');
                $condition->setOperator($operator);
                $condition->setValue('John');

                $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
                $sql = $condition->getConditionsSql($adapter);

                expect($sql)->toBeString();
                expect(strlen($sql))->toBeGreaterThan(0);
            }
        });
    });

    describe('Address Attribute Display Configuration', function () {
        test('provides correct attribute options', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');
            $condition->loadAttributeOptions();

            $options = $condition->getAttributeOption();

            $expectedAttributes = [
                'firstname', 'lastname', 'company', 'street', 'city',
                'region', 'postcode', 'country_id', 'telephone',
            ];

            foreach ($expectedAttributes as $attr) {
                expect(isset($options[$attr]))->toBe(true);
                expect($options[$attr])->toBeString();
                expect(strlen($options[$attr]))->toBeGreaterThan(0);
            }
        });

        test('provides correct input types for address attributes', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');

            // Select input types
            $condition->setAttribute('country_id');
            expect($condition->getInputType())->toBe('select');

            $condition->setAttribute('region');
            expect($condition->getInputType())->toBe('select');

            // String input types
            $textAttributes = ['firstname', 'lastname', 'company', 'street', 'city', 'postcode', 'telephone'];
            foreach ($textAttributes as $attr) {
                $condition->setAttribute($attr);
                expect($condition->getInputType())->toBe('string');
            }
        });

        test('provides select options for country and region', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');

            $condition->setAttribute('country_id');
            $options = $condition->getValueSelectOptions();
            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(0);

            $condition->setAttribute('region');
            $options = $condition->getValueSelectOptions();
            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(0);
        });
    });

    describe('String Representation', function () {
        test('generates readable string representation', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_address');
            $condition->setAttribute('city');
            $condition->setOperator('==');
            $condition->setValue('New York');

            $string = $condition->asString();
            expect($string)->toContain('Address');
            expect($string)->toContain('City');
            expect($string)->toContain('New York');
        });
    });
});

// Helper functions for test data creation and management
/**
 * @return array<string, int> customer IDs keyed by their last name
 */
function createCustomerAddressTestData(): array
{
    $uniqueId = uniqid('addr_', true);

    $customers = [
        // Customer with single US address
        [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'Single',
                'email' => "customer.single.{$uniqueId}@test.com",
            ],
            'addresses' => [
                [
                    'firstname' => 'John',
                    'lastname' => 'Smith',
                    'company' => 'Acme Corp',
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'region' => 'Free Region Name',
                    'region_id' => null,
                    'postcode' => '10001',
                    'country_id' => 'US',
                    'telephone' => '555-0123',
                ],
            ],
        ],
        // Customer with multiple addresses
        [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'Multiple',
                'email' => "customer.multiple.{$uniqueId}@test.com",
            ],
            'addresses' => [
                [
                    'firstname' => 'John',
                    'lastname' => 'Smith',
                    'company' => 'Acme Industries',
                    'street' => '456 Oak Ave',
                    'city' => 'Los Angeles',
                    'region' => 'California',
                    'region_id' => null,
                    'postcode' => '90210',
                    'country_id' => 'US',
                    'telephone' => '555-0456',
                ],
                [
                    'firstname' => 'Jane',
                    'lastname' => 'Smith',
                    'company' => '',
                    'street' => '789 Pine Dr',
                    'city' => 'San Francisco',
                    'region' => 'California',
                    'region_id' => null,
                    'postcode' => '94102',
                    'country_id' => 'US',
                    'telephone' => '555-0789',
                ],
            ],
        ],
        // Customer with Canadian address
        [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'Canadian',
                'email' => "customer.canadian.{$uniqueId}@test.com",
            ],
            'addresses' => [
                [
                    'firstname' => 'Pierre',
                    'lastname' => 'Dubois',
                    'company' => 'Maple Leaf Co',
                    'street' => '101 Queen St W',
                    'city' => 'Toronto',
                    'region' => 'Ontario',
                    'region_id' => null,
                    'postcode' => 'M5H 2N2',
                    'country_id' => 'CA',
                    'telephone' => '416-555-0101',
                ],
            ],
        ],
        // Customer with UK address (free-form region)
        [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'British',
                'email' => "customer.british.{$uniqueId}@test.com",
            ],
            'addresses' => [
                [
                    'firstname' => 'William',
                    'lastname' => 'Shakespeare',
                    'company' => 'Globe Theatre',
                    'street' => '21 New Globe Walk',
                    'city' => 'London',
                    'region' => 'London',
                    'region_id' => null,
                    'postcode' => 'SE1 9DT',
                    'country_id' => 'GB',
                    'telephone' => '+44 20 7902 1400',
                ],
            ],
        ],
        // Customer with address having special characters
        [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'Special',
                'email' => "customer.special.{$uniqueId}@test.com",
            ],
            'addresses' => [
                [
                    'firstname' => 'O\'Connor',
                    'lastname' => 'Smith-Jones',
                    'company' => 'St. John\'s & Associates',
                    'street' => '100 St. John\'s Road, Apt #5',
                    'city' => 'St. John\'s',
                    'region' => 'Newfoundland and Labrador',
                    'region_id' => null,
                    'postcode' => 'A1C 5S7',
                    'country_id' => 'CA',
                    'telephone' => '709-555-0100',
                ],
            ],
        ],
        // Customer with empty/null fields
        [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'Minimal',
                'email' => "customer.minimal.{$uniqueId}@test.com",
            ],
            'addresses' => [
                [
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'company' => null,
                    'street' => '123 Any St',
                    'city' => 'Anytown',
                    'region' => '',
                    'region_id' => null,
                    'postcode' => '12345',
                    'country_id' => 'US',
                    'telephone' => null,
                ],
            ],
        ],
        // Customer with no addresses
        [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'NoAddress',
                'email' => "customer.noaddress.{$uniqueId}@test.com",
            ],
            'addresses' => [],
        ],
    ];

    // Get a real region from the directory to test directory lookup
    $regionCollection = Mage::getResourceModel('directory/region_collection')
        ->addCountryFilter('US')
        ->setPageSize(1);

    $directoryRegion = $regionCollection->getFirstItem();
    if ($directoryRegion && $directoryRegion->getId()) {
        // Add customer with directory region ID
        $customers[] = [
            'customer' => [
                'firstname' => 'Customer',
                'lastname' => 'Directory',
                'email' => "customer.directory.{$uniqueId}@test.com",
            ],
            'addresses' => [
                [
                    'firstname' => 'Directory',
                    'lastname' => 'Test',
                    'company' => 'Directory Testing Corp',
                    'street' => '555 Directory Ave',
                    'city' => 'Directory City',
                    'region' => $directoryRegion->getName(),
                    'region_id' => (int) $directoryRegion->getId(),
                    'postcode' => '55555',
                    'country_id' => $directoryRegion->getCountryId(),
                    'telephone' => '555-5555',
                ],
            ],
        ];
    }

    $ids = [];
    foreach ($customers as $customerData) {
        $customer = Mage::getModel('customer/customer');
        $customer->setFirstname($customerData['customer']['firstname']);
        $customer->setLastname($customerData['customer']['lastname']);
        $customer->setEmail($customerData['customer']['email']);
        $customer->setGroupId(1);
        $customer->setWebsiteId(1);
        $customer->save();
        $ids[$customerData['customer']['lastname']] = (int) $customer->getId();

        // Create addresses
        foreach ($customerData['addresses'] as $addressData) {
            $address = Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId());
            $address->setFirstname($addressData['firstname']);
            $address->setLastname($addressData['lastname']);

            if (isset($addressData['company'])) {
                $address->setCompany($addressData['company']);
            }

            $address->setStreet($addressData['street']);
            $address->setCity($addressData['city']);

            if (isset($addressData['region'])) {
                $address->setRegion($addressData['region']);
            }

            if (isset($addressData['region_id']) && $addressData['region_id']) {
                $address->setRegionId($addressData['region_id']);
            }

            $address->setPostcode($addressData['postcode']);
            $address->setCountryId($addressData['country_id']);

            if (isset($addressData['telephone'])) {
                $address->setTelephone($addressData['telephone']);
            }

            $address->save();
        }
    }

    return $ids;
}

function createCustomerAddressTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
    $segment->setDescription('Customer address test segment for ' . $name);
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

function getCustomerAddresses(int $customerId): array
{
    $addressCollection = Mage::getResourceModel('customer/address_collection')
        ->addAttributeToSelect('*')
        ->addAttributeToFilter('parent_id', $customerId);

    return $addressCollection->getItems();
}
