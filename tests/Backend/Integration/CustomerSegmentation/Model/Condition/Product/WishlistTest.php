<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Product Wishlist Condition Integration Tests', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_product_wishlist');
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

        // Set up test data - just verify tables exist
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
            expect($sql)->toContain('items_count');
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
            expect($sql)->toContain('DATEDIFF');
            expect($sql)->toContain('2025-'); // Verify date is properly formatted
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
            expect($sql)->toContain('w.shared = \'1\'');

            // Test No (not shared)
            $this->condition->setValue('0');
            $sql = $this->condition->getConditionsSql($this->adapter);
            expect($sql)->toContain('w.shared = \'0\'');
        });

        test('handles wishlist items count aggregation', function () {
            $this->condition->setAttribute('wishlist_items_count');
            $this->condition->setOperator('>=');
            $this->condition->setValue('10');

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toContain('COUNT(*)');
            expect($sql)->toContain('GROUP BY');
            expect($sql)->toContain('HAVING');
            expect($sql)->toContain('items_count >= \'10\'');
        });

        test('handles date-based wishlist conditions', function () {
            $this->condition->setAttribute('days_since_added');
            $this->condition->setOperator('<');
            $this->condition->setValue('7'); // Added in last 7 days

            $sql = $this->condition->getConditionsSql($this->adapter);

            expect($sql)->toContain('DATEDIFF');
            expect($sql)->toContain('MAX(wi.added_at)');
            expect($sql)->toContain('< \'7\'');
        });
    });

});

// Helper method to set up test data
function setupWishlistTestData()
{
    // This would normally set up test customers, products, and wishlist records
    // For now, we'll just ensure the tables exist and are accessible
    $tables = [
        'wishlist',
        'wishlist_item',
        'catalog_product_entity',
        'catalog_product_entity_varchar',
        'catalog_category_product',
    ];

    foreach ($tables as $table) {
        $tableName = Mage::getSingleton('core/resource')->getTableName($table);
        expect($tableName)->toBeString();
    }
}
