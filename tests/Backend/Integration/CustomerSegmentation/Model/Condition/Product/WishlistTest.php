<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Product Wishlist Condition Integration Tests', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_product_wishlist');
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

        // Set up test data with actual customers and wishlist items
        setupWishlistTestData();
    });

    test('can create new condition instance', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Product_Wishlist::class);
        expect($this->condition->getType())->toBe('customersegmentation/segment_condition_product_wishlist');
    });

    test('extends abstract condition correctly', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
    });

    test('has correct new child select options', function () {
        $options = $this->condition->getNewChildSelectOptions();

        expect($options)->toHaveKey('value');
        expect($options['value'])->toBe('customersegmentation/segment_condition_product_wishlist');
        expect($options)->toHaveKey('label');
        expect($options['label'])->toBeString();
    });

    test('loads all required attributes correctly', function () {
        $this->condition->loadAttributeOptions();
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBe(8);

        // Check all required attributes exist
        $expectedAttributes = [
            'product_id', 'product_name', 'product_sku', 'category_id',
            'wishlist_items_count', 'added_at', 'days_since_added', 'wishlist_shared',
        ];

        foreach ($expectedAttributes as $attribute) {
            expect($options)->toHaveKey($attribute);
        }

        // Test attribute labels
        expect($options['product_id'])->toBeString();
        expect($options['wishlist_shared'])->toBeString();
    });

    test('returns correct input types for attributes', function () {
        $numericAttributes = ['product_id', 'category_id', 'wishlist_items_count', 'days_since_added'];
        foreach ($numericAttributes as $attr) {
            $this->condition->setAttribute($attr);
            expect($this->condition->getInputType())->toBe('numeric', "Attribute {$attr} should be numeric");
        }

        $this->condition->setAttribute('added_at');
        expect($this->condition->getInputType())->toBe('date');

        $this->condition->setAttribute('wishlist_shared');
        expect($this->condition->getInputType())->toBe('select');

        $this->condition->setAttribute('product_name');
        expect($this->condition->getInputType())->toBe('string');

        $this->condition->setAttribute('product_sku');
        expect($this->condition->getInputType())->toBe('string');
    });

    test('returns correct value element types for attributes', function () {
        $this->condition->setAttribute('added_at');
        expect($this->condition->getValueElementType())->toBe('date');

        $this->condition->setAttribute('wishlist_shared');
        expect($this->condition->getValueElementType())->toBe('select');

        $this->condition->setAttribute('category_id');
        expect($this->condition->getValueElementType())->toBe('select');

        $this->condition->setAttribute('product_id');
        expect($this->condition->getValueElementType())->toBe('text');
    });

    test('wishlist_shared select options are populated correctly', function () {
        $this->condition->setAttribute('wishlist_shared');
        $options = $this->condition->getValueSelectOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBe(3); // Please select, Yes, No

        expect($options[0]['value'])->toBe('');
        expect($options[1]['value'])->toBe('1');
        expect($options[2]['value'])->toBe('0');
    });

    test('category select options are populated correctly', function () {
        $this->condition->setAttribute('category_id');
        $options = $this->condition->getValueSelectOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThanOrEqual(1); // At least the "Please select..." option
        expect($options[0])->toHaveKey('value');
        expect($options[0])->toHaveKey('label');
        expect($options[0]['value'])->toBe('');
    });

    describe('SQL generation tests', function () {
        test('generates correct SQL for product_id condition', function () {
            $this->condition->setAttribute('product_id');
            $this->condition->setOperator('==');
            $this->condition->setValue('123');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist_item');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('wi.product_id');
            expect($sql)->toContain('w.customer_id IS NOT NULL');
        });

        test('generates correct SQL for product_name condition with joins', function () {
            $this->condition->setAttribute('product_name');
            $this->condition->setOperator('{}');
            $this->condition->setValue('Test Product');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist_item');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('catalog_product_entity');
            expect($sql)->toContain('catalog_product_entity_varchar');
            expect($sql)->toContain('pv.value LIKE');
            expect($sql)->toContain('%Test Product%');
        });

        test('generates correct SQL for product_sku condition with product table join', function () {
            $this->condition->setAttribute('product_sku');
            $this->condition->setOperator('==');
            $this->condition->setValue('TEST-SKU');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist_item');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('catalog_product_entity');
            expect($sql)->toContain('p.sku =');
        });

        test('generates correct SQL for category_id condition with category table join', function () {
            $this->condition->setAttribute('category_id');
            $this->condition->setOperator('==');
            $this->condition->setValue('5');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist_item');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('catalog_category_product');
            expect($sql)->toContain('ccp.category_id');
        });

        test('generates correct SQL for wishlist_items_count aggregation condition', function () {
            $this->condition->setAttribute('wishlist_items_count');
            $this->condition->setOperator('>=');
            $this->condition->setValue('5');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist_item');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('COUNT(*)');
            expect($sql)->toContain('GROUP BY');
            expect($sql)->toContain('HAVING');
            expect($sql)->toContain('COUNT(*)');
        });

        test('generates correct SQL for added_at condition', function () {
            $this->condition->setAttribute('added_at');
            $this->condition->setOperator('>=');
            $this->condition->setValue('2025-01-01');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist_item');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('wi.added_at');
        });

        test('generates correct SQL for days_since_added condition with utcDate preserved', function () {
            $this->condition->setAttribute('days_since_added');
            $this->condition->setOperator('<=');
            $this->condition->setValue('30');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist_item');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('MAX(wi.added_at)');
            // Check for MySQL (DATEDIFF), PostgreSQL (DATE() or ::date), or SQLite (JULIANDAY) syntax
            expect($sql)->toMatch('/DATEDIFF|::date|DATE\\(|JULIANDAY/');
            expect($sql)->toMatch('/202[5-9]-/'); // Verify date is properly formatted
            expect($sql)->toContain('GROUP BY');
            expect($sql)->toContain('HAVING');
        });

        test('generates correct SQL for wishlist_shared condition', function () {
            $this->condition->setAttribute('wishlist_shared');
            $this->condition->setOperator('==');
            $this->condition->setValue('1');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('wishlist');
            expect($sql)->toContain('w.shared');
            expect($sql)->toContain('w.customer_id IS NOT NULL');
        });
    });

    describe('Edge cases and error handling', function () {
        test('handles invalid attribute gracefully', function () {
            $this->condition->setAttribute('invalid_attribute');
            $this->condition->setOperator('==');
            $this->condition->setValue('test');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBe(false);
        });

        test('handles empty values correctly', function () {
            $this->condition->setAttribute('product_id');
            $this->condition->setOperator('==');
            $this->condition->setValue('');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('wi.product_id =');
        });

        test('handles array values for IN operator', function () {
            $this->condition->setAttribute('product_id');
            $this->condition->setOperator('()');
            $this->condition->setValue('1,2,3');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('wi.product_id IN');
        });

        test('handles customers with no wishlist items', function () {
            // Test that SQL correctly filters for customers who have wishlists
            $this->condition->setAttribute('wishlist_items_count');
            $this->condition->setOperator('>');
            $this->condition->setValue('0');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('w.customer_id IS NOT NULL');
        });

        test('handles shared wishlist edge cases', function () {
            $this->condition->setAttribute('wishlist_shared');
            $this->condition->setOperator('!=');
            $this->condition->setValue('1');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('w.shared !=');
        });
    });

    test('table name methods return correct table names', function () {
        $condition = new Maho_CustomerSegmentation_Model_Segment_Condition_Product_Wishlist();

        // Use reflection to test protected methods
        $reflection = new ReflectionClass($condition);

        $getWishlistTable = $reflection->getMethod('getWishlistTable');
        $getWishlistTable->setAccessible(true);
        expect($getWishlistTable->invoke($condition))->toContain('wishlist');

        $getWishlistItemTable = $reflection->getMethod('getWishlistItemTable');
        $getWishlistItemTable->setAccessible(true);
        expect($getWishlistItemTable->invoke($condition))->toContain('wishlist_item');

        $getProductTable = $reflection->getMethod('getProductTable');
        $getProductTable->setAccessible(true);
        expect($getProductTable->invoke($condition))->toContain('catalog_product_entity');

        $getProductVarcharTable = $reflection->getMethod('getProductVarcharTable');
        $getProductVarcharTable->setAccessible(true);
        expect($getProductVarcharTable->invoke($condition))->toContain('catalog_product_entity_varchar');

        $getCatalogCategoryProductTable = $reflection->getMethod('getCatalogCategoryProductTable');
        $getCatalogCategoryProductTable->setAccessible(true);
        expect($getCatalogCategoryProductTable->invoke($condition))->toContain('catalog_category_product');

        $getNameAttributeId = $reflection->getMethod('getNameAttributeId');
        $getNameAttributeId->setAccessible(true);
        $nameAttrId = $getNameAttributeId->invoke($condition);
        expect($nameAttrId)->toBeGreaterThan(0);
    });

    test('attribute name and string representation work correctly', function () {
        $this->condition->setAttribute('product_name');

        $attributeName = $this->condition->getAttributeName();
        expect($attributeName)->toContain('Wishlist');

        $this->condition->setOperator('{}');
        $this->condition->setValue('Test');

        $stringRepresentation = $this->condition->asString();
        expect($stringRepresentation)->toContain('Wishlist');
        expect($stringRepresentation)->toContain('Product Name');
    });

    describe('Wishlist-specific functionality', function () {
        test('handles wishlist shared status correctly', function () {
            // Test Yes (shared)
            $this->condition->setAttribute('wishlist_shared');
            $this->condition->setOperator('==');
            $this->condition->setValue('1');

            $sql = $this->condition->getConditionsSql($this->adapter);
            // SQLite may not quote integer values
            expect($sql)->toMatch('/w\.shared = .?1.?/');

            // Test No (not shared)
            $this->condition->setValue('0');
            $sql = $this->condition->getConditionsSql($this->adapter);
            expect($sql)->toMatch('/w\.shared = .?0.?/');
        });

        test('handles wishlist items count aggregation', function () {
            $this->condition->setAttribute('wishlist_items_count');
            $this->condition->setOperator('>=');
            $this->condition->setValue('10');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toContain('COUNT(*)');
            expect($sql)->toContain('GROUP BY');
            expect($sql)->toContain('HAVING');
            // SQLite may not quote integer values
            expect($sql)->toMatch('/COUNT\(\*\) >= .?10.?/');
        });

        test('handles date-based wishlist conditions', function () {
            $this->condition->setAttribute('days_since_added');
            $this->condition->setOperator('<');
            $this->condition->setValue('7'); // Added in last 7 days

            $sql = $this->condition->getConditionsSql($this->adapter);

            // Check for MySQL (DATEDIFF), PostgreSQL (DATE() or ::date), or SQLite (JULIANDAY) syntax
            expect($sql)->toMatch('/DATEDIFF|::date|DATE\\(|JULIANDAY/');
            expect($sql)->toContain('MAX(wi.added_at)');
            // SQLite may not quote integer values
            expect($sql)->toMatch('/< .?7.?/');
        });
    });

    describe('Business Logic Validation - Wishlist Item Counts', function () {
        test('finds customers with high wishlist item counts', function () {
            $segment = createWishlistTestSegment('High Item Count Wishlists', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_items_count',
                'operator' => '>=',
                'value' => '5',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Validate each matched customer actually has high item count
            foreach ($matchedCustomers as $customerId) {
                $itemCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                expect((int) $itemCount)->toBeGreaterThanOrEqual(5, "Customer {$customerId} should have >= 5 wishlist items, but has {$itemCount}");
            }
        });

        test('finds customers with exact wishlist item count', function () {
            $segment = createWishlistTestSegment('Exact Item Count', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_items_count',
                'operator' => '==',
                'value' => '3',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $itemCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                expect((int) $itemCount)->toBe(3, "Customer {$customerId} should have exactly 3 wishlist items, but has {$itemCount}");
            }
        });

        test('excludes customers with low wishlist item counts', function () {
            $segment = createWishlistTestSegment('Low Item Count', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_items_count',
                'operator' => '<',
                'value' => '2',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $itemCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                expect((int) $itemCount)->toBeLessThan(2, "Customer {$customerId} should have < 2 wishlist items, but has {$itemCount}");
            }
        });
    });

    describe('Business Logic Validation - Wishlist Sharing', function () {
        test('finds customers with shared wishlists', function () {
            $segment = createWishlistTestSegment('Shared Wishlists', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_shared',
                'operator' => '==',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $hasSharedWishlist = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist'), ['COUNT(*)'])
                    ->where('customer_id = ?', $customerId)
                    ->where('shared = ?', 1)
                    ->query()
                    ->fetchColumn();

                expect((int) $hasSharedWishlist)->toBeGreaterThan(0, "Customer {$customerId} should have at least one shared wishlist");
            }
        });

        test('finds customers with private wishlists', function () {
            $segment = createWishlistTestSegment('Private Wishlists', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_shared',
                'operator' => '==',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $hasPrivateWishlist = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist'), ['COUNT(*)'])
                    ->where('customer_id = ?', $customerId)
                    ->where('shared = ?', 0)
                    ->query()
                    ->fetchColumn();

                expect((int) $hasPrivateWishlist)->toBeGreaterThan(0, "Customer {$customerId} should have at least one private wishlist");
            }
        });

        test('excludes customers without shared wishlists', function () {
            $segment = createWishlistTestSegment('No Shared Wishlists', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_shared',
                'operator' => '!=',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Customer should either have no wishlists or only private ones
                $sharedWishlistCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist'), ['COUNT(*)'])
                    ->where('customer_id = ?', $customerId)
                    ->where('shared = ?', 1)
                    ->query()
                    ->fetchColumn();

                expect((int) $sharedWishlistCount)->toBe(0, "Customer {$customerId} should not have any shared wishlists");
            }
        });
    });

    describe('Business Logic Validation - Date and Time Calculations', function () {
        test('finds customers with recent wishlist additions', function () {
            $segment = createWishlistTestSegment('Recent Wishlist Additions', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'days_since_added',
                'operator' => '<=',
                'value' => '7', // Last 7 days
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Get the most recent addition for this customer
                $lastAddedDate = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['MAX(wi.added_at)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                if ($lastAddedDate) {
                    $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $daysSinceLastAdded = (int) ((strtotime($currentDate) - strtotime($lastAddedDate)) / 86400);

                    expect($daysSinceLastAdded)->toBeLessThanOrEqual(7, "Customer {$customerId} should have added to wishlist within 7 days, but it was {$daysSinceLastAdded} days ago");
                }
            }
        });

        test('finds customers with old wishlist additions', function () {
            $segment = createWishlistTestSegment('Old Wishlist Additions', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'days_since_added',
                'operator' => '>=',
                'value' => '30', // More than 30 days ago
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $lastAddedDate = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['MAX(wi.added_at)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                if ($lastAddedDate) {
                    $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $daysSinceLastAdded = (int) ((strtotime($currentDate) - strtotime($lastAddedDate)) / 86400);

                    expect($daysSinceLastAdded)->toBeGreaterThanOrEqual(30, "Customer {$customerId} should have last added to wishlist >= 30 days ago, but it was {$daysSinceLastAdded} days ago");
                }
            }
        });

        test('finds customers who added products to wishlist on specific date', function () {
            $testDate = date('Y-m-d', strtotime('-5 days'));
            $segment = createWishlistTestSegment('Specific Date Additions', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'added_at',
                'operator' => '>=',
                'value' => $testDate,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $hasRecentAdditions = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->where('DATE(wi.added_at) >= ?', $testDate)
                    ->query()
                    ->fetchColumn();

                expect((int) $hasRecentAdditions)->toBeGreaterThan(0, "Customer {$customerId} should have added items on or after {$testDate}");
            }
        });
    });

    describe('Business Logic Validation - Product Specific Filtering', function () {
        test('finds customers who added specific product by SKU to wishlist', function () {
            // Get a test product SKU from our test data
            $testSku = 'wishlist-laptop-' . substr(uniqid('wishlist_'), 0, 10); // Use a predictable SKU pattern

            $segment = createWishlistTestSegment('SKU Specific Wishlisters', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'product_sku',
                'operator' => '{}',
                'value' => 'wishlist-laptop', // Pattern match
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $hasLaptopWishlistItems = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->join(['p' => Mage::getSingleton('core/resource')->getTableName('catalog/product')], 'wi.product_id = p.entity_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->where('p.sku LIKE ?', '%wishlist-laptop%')
                    ->query()
                    ->fetchColumn();

                expect((int) $hasLaptopWishlistItems)->toBeGreaterThan(0, "Customer {$customerId} should have laptop products in wishlist");
            }
        });

        test('finds customers who added products with name pattern to wishlist', function () {
            $segment = createWishlistTestSegment('Product Name Pattern Wishlisters', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'product_name',
                'operator' => '{}',
                'value' => 'Wishlist',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $hasWishlistProducts = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->join(['p' => Mage::getSingleton('core/resource')->getTableName('catalog/product')], 'wi.product_id = p.entity_id', [])
                    ->join(['pv' => Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar')], 'p.entity_id = pv.entity_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->where('pv.value LIKE ?', '%Wishlist%')
                    ->where('pv.attribute_id = ?', Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product', 'name'))
                    ->query()
                    ->fetchColumn();

                expect((int) $hasWishlistProducts)->toBeGreaterThan(0, "Customer {$customerId} should have products containing 'Wishlist' in name in their wishlist");
            }
        });

        test('finds customers who added specific product ID to wishlist', function () {
            // Get first available product from our test data
            $productId = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('sku', ['like' => '%wishlist%'])
                ->setPageSize(1)
                ->getFirstItem()
                ->getId();

            if ($productId) {
                $segment = createWishlistTestSegment('Product ID Wishlisters', [
                    'type' => 'customersegmentation/segment_condition_product_wishlist',
                    'attribute' => 'product_id',
                    'operator' => '==',
                    'value' => (string) $productId,
                ]);

                $matchedCustomers = $segment->getMatchingCustomerIds();

                foreach ($matchedCustomers as $customerId) {
                    $hasProductInWishlist = Mage::getSingleton('core/resource')->getConnection('core_read')
                        ->select()
                        ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                        ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                        ->where('w.customer_id = ?', $customerId)
                        ->where('wi.product_id = ?', $productId)
                        ->query()
                        ->fetchColumn();

                    expect((int) $hasProductInWishlist)->toBeGreaterThan(0, "Customer {$customerId} should have product ID {$productId} in wishlist");
                }
            }
        });
    });

    describe('Business Logic Validation - Category Filtering', function () {
        test('finds customers who added products from specific category to wishlist', function () {
            // Get a test category from our setup
            $category = Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToSelect('name')
                ->addAttributeToFilter('name', ['like' => '%Wishlist Electronics%'])
                ->addAttributeToFilter('is_active', 1)
                ->setPageSize(1)
                ->getFirstItem();

            if ($category && $category->getId()) {
                $categoryId = $category->getId();

                $segment = createWishlistTestSegment('Category Specific Wishlisters', [
                    'type' => 'customersegmentation/segment_condition_product_wishlist',
                    'attribute' => 'category_id',
                    'operator' => '==',
                    'value' => (string) $categoryId,
                ]);

                $matchedCustomers = $segment->getMatchingCustomerIds();

                foreach ($matchedCustomers as $customerId) {
                    $hasCategoryProducts = Mage::getSingleton('core/resource')->getConnection('core_read')
                        ->select()
                        ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                        ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                        ->join(['ccp' => Mage::getSingleton('core/resource')->getTableName('catalog/category_product')], 'wi.product_id = ccp.product_id', [])
                        ->where('w.customer_id = ?', $customerId)
                        ->where('ccp.category_id = ?', $categoryId)
                        ->query()
                        ->fetchColumn();

                    expect((int) $hasCategoryProducts)->toBeGreaterThan(0, "Customer {$customerId} should have products from category {$categoryId} in wishlist");
                }
            }
        });

        test('filters customers by multiple category criteria', function () {
            $segment = createWishlistTestSegment('Multi Category Wishlisters', [
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'any',
                'value' => 1,
                'conditions' => [
                    [
                        'type' => 'customersegmentation/segment_condition_product_wishlist',
                        'attribute' => 'category_id',
                        'operator' => '==',
                        'value' => '3', // Assuming category ID 3 exists
                    ],
                    [
                        'type' => 'customersegmentation/segment_condition_product_wishlist',
                        'attribute' => 'category_id',
                        'operator' => '==',
                        'value' => '4', // Assuming category ID 4 exists
                    ],
                ],
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Validate that each customer has wishlist items in category 3 OR 4
            foreach ($matchedCustomers as $customerId) {
                $hasMatchingProducts = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->join(['ccp' => Mage::getSingleton('core/resource')->getTableName('catalog/category_product')], 'wi.product_id = ccp.product_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->where('ccp.category_id IN (?)', [3, 4])
                    ->query()
                    ->fetchColumn();

                expect((int) $hasMatchingProducts)->toBeGreaterThan(0, "Customer {$customerId} should have wishlist items in category 3 or 4");
            }
        });
    });

    describe('Business Logic Validation - Complex Multi-Condition Scenarios', function () {
        test('finds customers with many shared wishlist items added recently', function () {
            $segment = createWishlistTestSegment('Complex Wishlist Users', [
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'all',
                'value' => 1,
                'conditions' => [
                    [
                        'type' => 'customersegmentation/segment_condition_product_wishlist',
                        'attribute' => 'wishlist_items_count',
                        'operator' => '>=',
                        'value' => '3',
                    ],
                    [
                        'type' => 'customersegmentation/segment_condition_product_wishlist',
                        'attribute' => 'wishlist_shared',
                        'operator' => '==',
                        'value' => '1',
                    ],
                    [
                        'type' => 'customersegmentation/segment_condition_product_wishlist',
                        'attribute' => 'days_since_added',
                        'operator' => '<=',
                        'value' => '14',
                    ],
                ],
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Validate item count >= 3
                $itemCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                expect((int) $itemCount)->toBeGreaterThanOrEqual(3, "Customer {$customerId} should have >= 3 wishlist items");

                // Validate has shared wishlist
                $hasSharedWishlist = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist'), ['COUNT(*)'])
                    ->where('customer_id = ?', $customerId)
                    ->where('shared = ?', 1)
                    ->query()
                    ->fetchColumn();

                expect((int) $hasSharedWishlist)->toBeGreaterThan(0, "Customer {$customerId} should have shared wishlist");

                // Validate recent additions
                $lastAddedDate = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['MAX(wi.added_at)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                if ($lastAddedDate) {
                    $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $daysSinceLastAdded = (int) ((strtotime($currentDate) - strtotime($lastAddedDate)) / 86400);
                    expect($daysSinceLastAdded)->toBeLessThanOrEqual(14, "Customer {$customerId} should have recent wishlist additions within 14 days");
                }
            }
        });

        test('excludes customers with only private empty wishlists', function () {
            $segment = createWishlistTestSegment('Active Wishlist Users', [
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'any',
                'value' => 1,
                'conditions' => [
                    [
                        'type' => 'customersegmentation/segment_condition_product_wishlist',
                        'attribute' => 'wishlist_items_count',
                        'operator' => '>',
                        'value' => '0',
                    ],
                    [
                        'type' => 'customersegmentation/segment_condition_product_wishlist',
                        'attribute' => 'wishlist_shared',
                        'operator' => '==',
                        'value' => '1',
                    ],
                ],
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Customer should either have items or shared wishlist (or both)
                $itemCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                $sharedWishlistCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist'), ['COUNT(*)'])
                    ->where('customer_id = ?', $customerId)
                    ->where('shared = ?', 1)
                    ->query()
                    ->fetchColumn();

                $hasItemsOrShared = ((int) $itemCount > 0) || ((int) $sharedWishlistCount > 0);
                expect($hasItemsOrShared)->toBe(true, "Customer {$customerId} should have either wishlist items or shared wishlist");
            }
        });
    });

    describe('Edge Cases and Data Integrity', function () {
        test('handles customers with no wishlist gracefully', function () {
            $segment = createWishlistTestSegment('Has Wishlist', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_items_count',
                'operator' => '>',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            // All matched customers should have at least one wishlist item
            foreach ($matchedCustomers as $customerId) {
                $itemCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                expect((int) $itemCount)->toBeGreaterThan(0, "Customer {$customerId} should have at least one wishlist item");
            }
        });

        test('handles wishlist item count boundaries correctly', function () {
            $segment = createWishlistTestSegment('Exact Wishlist Count', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_items_count',
                'operator' => '==',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $itemCount = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                expect((int) $itemCount)->toBe(1, "Customer {$customerId} should have exactly 1 wishlist item, but has {$itemCount}");
            }
        });

        test('handles date boundary conditions for wishlist', function () {
            $exactDate = date('Y-m-d', strtotime('-10 days'));
            $segment = createWishlistTestSegment('Exact Date Wishlist Additions', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'added_at',
                'operator' => '==',
                'value' => $exactDate,
            ]);

            // Should not crash with exact date matches
            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();
        });

        test('handles multiple wishlists per customer', function () {
            // Some customers might have multiple wishlists in edge cases
            $segment = createWishlistTestSegment('Multiple Wishlists', [
                'type' => 'customersegmentation/segment_condition_product_wishlist',
                'attribute' => 'wishlist_items_count',
                'operator' => '>=',
                'value' => '1',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            // Verify the query handles multiple wishlists correctly by aggregating
            foreach ($matchedCustomers as $customerId) {
                $totalItems = Mage::getSingleton('core/resource')->getConnection('core_read')
                    ->select()
                    ->from(['wi' => Mage::getSingleton('core/resource')->getTableName('wishlist/item')], ['COUNT(*)'])
                    ->join(['w' => Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist')], 'wi.wishlist_id = w.wishlist_id', [])
                    ->where('w.customer_id = ?', $customerId)
                    ->query()
                    ->fetchColumn();

                expect((int) $totalItems)->toBeGreaterThanOrEqual(1, "Customer {$customerId} should have >= 1 total wishlist items across all wishlists");
            }
        });
    });

});

// Helper method to set up comprehensive wishlist test data
function setupWishlistTestData(): void
{
    $uniqueId = uniqid('wishlist_', true);

    // Create test categories
    $electronicsCategory = createWishlistTestCategory('Wishlist Electronics', 'wishlist-electronics-test');
    $clothingCategory = createWishlistTestCategory('Wishlist Clothing', 'wishlist-clothing-test');
    $homeCategory = createWishlistTestCategory('Wishlist Home', 'wishlist-home-test');

    // Create test products
    $laptop = createWishlistTestProduct('Wishlist Laptop Pro', 'wishlist-laptop-' . $uniqueId, (int) $electronicsCategory->getId());
    $phone = createWishlistTestProduct('Wishlist Smartphone', 'wishlist-phone-' . $uniqueId, (int) $electronicsCategory->getId());
    $shirt = createWishlistTestProduct('Wishlist Cotton Shirt', 'wishlist-shirt-' . $uniqueId, (int) $clothingCategory->getId());
    $shoes = createWishlistTestProduct('Wishlist Running Shoes', 'wishlist-shoes-' . $uniqueId, (int) $clothingCategory->getId());
    $lamp = createWishlistTestProduct('Wishlist Table Lamp', 'wishlist-lamp-' . $uniqueId, (int) $homeCategory->getId());

    // Create test customers with varying wishlist patterns
    $customers = [
        // Customer with many items in shared wishlist
        [
            'firstname' => 'Wishlist',
            'lastname' => 'Enthusiast',
            'email' => "wishlist.enthusiast.{$uniqueId}@test.com",
            'wishlist_shared' => true,
            'items' => [
                ['product_id' => $laptop->getId(), 'days_ago' => 3],
                ['product_id' => $phone->getId(), 'days_ago' => 5],
                ['product_id' => $shirt->getId(), 'days_ago' => 7],
                ['product_id' => $shoes->getId(), 'days_ago' => 10],
                ['product_id' => $lamp->getId(), 'days_ago' => 12],
            ],
        ],
        // Customer with few items in private wishlist
        [
            'firstname' => 'Private',
            'lastname' => 'Shopper',
            'email' => "private.shopper.{$uniqueId}@test.com",
            'wishlist_shared' => false,
            'items' => [
                ['product_id' => $laptop->getId(), 'days_ago' => 2],
                ['product_id' => $shirt->getId(), 'days_ago' => 4],
                ['product_id' => $lamp->getId(), 'days_ago' => 6],
            ],
        ],
        // Customer with recent wishlist activity
        [
            'firstname' => 'Recent',
            'lastname' => 'Wisher',
            'email' => "recent.wisher.{$uniqueId}@test.com",
            'wishlist_shared' => true,
            'items' => [
                ['product_id' => $phone->getId(), 'days_ago' => 1],
                ['product_id' => $shoes->getId(), 'days_ago' => 2],
            ],
        ],
        // Customer with old wishlist activity
        [
            'firstname' => 'Old',
            'lastname' => 'Wisher',
            'email' => "old.wisher.{$uniqueId}@test.com",
            'wishlist_shared' => false,
            'items' => [
                ['product_id' => $shirt->getId(), 'days_ago' => 35],
                ['product_id' => $lamp->getId(), 'days_ago' => 40],
            ],
        ],
        // Customer with single wishlist item (boundary case)
        [
            'firstname' => 'Single',
            'lastname' => 'Item',
            'email' => "single.item.{$uniqueId}@test.com",
            'wishlist_shared' => false,
            'items' => [
                ['product_id' => $laptop->getId(), 'days_ago' => 15],
            ],
        ],
        // Customer with shared empty wishlist
        [
            'firstname' => 'Empty',
            'lastname' => 'Shared',
            'email' => "empty.shared.{$uniqueId}@test.com",
            'wishlist_shared' => true,
            'items' => [],
        ],
        // Customer with multiple electronics items (category test)
        [
            'firstname' => 'Electronics',
            'lastname' => 'Lover',
            'email' => "electronics.lover.{$uniqueId}@test.com",
            'wishlist_shared' => true,
            'items' => [
                ['product_id' => $laptop->getId(), 'days_ago' => 8],
                ['product_id' => $phone->getId(), 'days_ago' => 9],
            ],
        ],
        // Customer with no wishlist (control)
        [
            'firstname' => 'No',
            'lastname' => 'Wishlist',
            'email' => "no.wishlist.{$uniqueId}@test.com",
            'wishlist_shared' => false,
            'items' => [],
            'create_wishlist' => false,
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

        // Create wishlist only if needed (some customers have no wishlist)
        if (!isset($customerData['create_wishlist']) || $customerData['create_wishlist'] !== false) {
            // Create wishlist
            $wishlist = Mage::getModel('wishlist/wishlist');
            $wishlist->setCustomerId($customer->getId());
            $wishlist->setShared($customerData['wishlist_shared'] ? 1 : 0);
            $wishlist->save();

            // Add wishlist items
            foreach ($customerData['items'] as $itemData) {
                $wishlistItem = Mage::getModel('wishlist/item');
                $wishlistItem->setWishlistId($wishlist->getId());
                $wishlistItem->setProductId($itemData['product_id']);
                $wishlistItem->setStoreId(1);
                $wishlistItem->setQty(1);

                $addedDate = date('Y-m-d H:i:s', strtotime("-{$itemData['days_ago']} days"));
                $wishlistItem->setAddedAt($addedDate);

                $wishlistItem->save();
            }
        }
    }
}

function createWishlistTestCategory(string $name, string $urlKey): Mage_Catalog_Model_Category
{
    $category = Mage::getModel('catalog/category');
    $category->setName($name);
    $category->setUrlKey($urlKey . '-' . uniqid());
    $category->setIsActive(1);
    $category->setParentId(2); // Default category
    $category->setPath('1/2'); // Root path
    $category->save();
    return $category;
}

function createWishlistTestProduct(string $name, string $sku, int $categoryId): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setName($name);
    $product->setSku($sku);
    $product->setPrice(149.99);
    $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setAttributeSetId(4); // Default attribute set
    $product->setWebsiteIds([1]);
    $product->setCategoryIds([$categoryId]);
    $product->save();

    // Assign product to category
    $category = Mage::getModel('catalog/category')->load($categoryId);
    $category->setPostedProducts([$product->getId() => 0]);
    $category->save();

    return $product;
}

function createWishlistTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
    $segment->setDescription('Product wishlist test segment for ' . $name);
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
