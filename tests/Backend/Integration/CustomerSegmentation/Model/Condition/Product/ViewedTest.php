<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Product Viewed Condition Integration Tests', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_product_viewed');
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        
        // Set up test data - just verify tables exist
        setupTestData();
    });

    test('can create new condition instance', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Product_Viewed::class);
        expect($this->condition->getType())->toBe('customersegmentation/segment_condition_product_viewed');
    });

    test('extends abstract condition correctly', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
    });

    test('has correct new child select options', function () {
        $options = $this->condition->getNewChildSelectOptions();

        expect($options)->toHaveKey('value');
        expect($options['value'])->toBe('customersegmentation/segment_condition_product_viewed');
        expect($options)->toHaveKey('label');
        expect($options['label'])->toBeString();
    });

    test('loads all required attributes correctly', function () {
        $this->condition->loadAttributeOptions();
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBe(7);

        // Check all required attributes exist
        $expectedAttributes = [
            'product_id', 'product_name', 'product_sku', 'category_id', 
            'view_count', 'last_viewed_at', 'days_since_last_view'
        ];
        
        foreach ($expectedAttributes as $attribute) {
            expect($options)->toHaveKey($attribute);
        }
        
        // Test attribute labels
        expect($options['product_id'])->toBeString();
        expect($options['product_name'])->toBeString();
        expect($options['product_sku'])->toBeString();
    });

    test('returns correct input types for attributes', function () {
        $numericAttributes = ['product_id', 'category_id', 'view_count', 'days_since_last_view'];
        foreach ($numericAttributes as $attr) {
            $this->condition->setAttribute($attr);
            expect($this->condition->getInputType())->toBe('numeric', "Attribute {$attr} should be numeric");
        }

        $this->condition->setAttribute('last_viewed_at');
        expect($this->condition->getInputType())->toBe('date');

        $this->condition->setAttribute('product_name');
        expect($this->condition->getInputType())->toBe('string');

        $this->condition->setAttribute('product_sku');
        expect($this->condition->getInputType())->toBe('string');
    });

    test('returns correct value element types for attributes', function () {
        $this->condition->setAttribute('last_viewed_at');
        expect($this->condition->getValueElementType())->toBe('date');

        $this->condition->setAttribute('category_id');
        expect($this->condition->getValueElementType())->toBe('select');

        $this->condition->setAttribute('product_id');
        expect($this->condition->getValueElementType())->toBe('text');
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
            expect($sql)->toContain('report_viewed_product_index');
            expect($sql)->toContain('rv.product_id');
            expect($sql)->toContain('customer_id IS NOT NULL');
        });

        test('generates correct SQL for product_name condition with joins', function () {
            $this->condition->setAttribute('product_name');
            $this->condition->setOperator('{}');
            $this->condition->setValue('Test Product');

            $sql = $this->condition->getConditionsSql($this->adapter);
            
            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('report_viewed_product_index');
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
            expect($sql)->toContain('report_viewed_product_index');
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
            expect($sql)->toContain('report_viewed_product_index');
            expect($sql)->toContain('catalog_category_product');
            expect($sql)->toContain('ccp.category_id');
        });

        test('generates correct SQL for view_count aggregation condition', function () {
            $this->condition->setAttribute('view_count');
            $this->condition->setOperator('>=');
            $this->condition->setValue('5');

            $sql = $this->condition->getConditionsSql($this->adapter);
            
            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('report_viewed_product_index');
            expect($sql)->toContain('COUNT(*)');
            expect($sql)->toContain('GROUP BY');
            expect($sql)->toContain('HAVING');
            expect($sql)->toContain('view_count');
        });

        test('generates correct SQL for last_viewed_at condition', function () {
            $this->condition->setAttribute('last_viewed_at');
            $this->condition->setOperator('>=');
            $this->condition->setValue('2025-01-01');

            $sql = $this->condition->getConditionsSql($this->adapter);
            
            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('report_viewed_product_index');
            expect($sql)->toContain('MAX(rv.added_at)');
            expect($sql)->toContain('GROUP BY');
            expect($sql)->toContain('HAVING');
        });

        test('generates correct SQL for days_since_last_view condition with utcDate preserved', function () {
            $this->condition->setAttribute('days_since_last_view');
            $this->condition->setOperator('<=');
            $this->condition->setValue('30');

            $sql = $this->condition->getConditionsSql($this->adapter);
            
            expect($sql)->toBeString();
            expect($sql)->toContain('e.entity_id IN');
            expect($sql)->toContain('report_viewed_product_index');
            expect($sql)->toContain('MAX(rv.added_at)');
            expect($sql)->toContain('DATEDIFF');
            expect($sql)->toContain('2025-'); // Verify date is properly formatted
            expect($sql)->toContain('GROUP BY');
            expect($sql)->toContain('HAVING');
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
            expect($sql)->toContain('rv.product_id =');
        });

        test('handles array values for IN operator', function () {
            $this->condition->setAttribute('product_id');
            $this->condition->setOperator('()');
            $this->condition->setValue('1,2,3');

            $sql = $this->condition->getConditionsSql($this->adapter);
            
            expect($sql)->toBeString();
            expect($sql)->toContain('rv.product_id IN');
        });
    });

    test('table name methods return correct table names', function () {
        $condition = new Maho_CustomerSegmentation_Model_Segment_Condition_Product_Viewed();
        
        // Use reflection to test protected methods
        $reflection = new ReflectionClass($condition);
        
        $getReportViewedTable = $reflection->getMethod('_getReportViewedTable');
        $getReportViewedTable->setAccessible(true);
        expect($getReportViewedTable->invoke($condition))->toContain('report_viewed_product_index');
        
        $getProductTable = $reflection->getMethod('_getProductTable');
        $getProductTable->setAccessible(true);
        expect($getProductTable->invoke($condition))->toContain('catalog_product_entity');
        
        $getProductVarcharTable = $reflection->getMethod('_getProductVarcharTable');
        $getProductVarcharTable->setAccessible(true);
        expect($getProductVarcharTable->invoke($condition))->toContain('catalog_product_entity_varchar');
        
        $getCatalogCategoryProductTable = $reflection->getMethod('_getCatalogCategoryProductTable');
        $getCatalogCategoryProductTable->setAccessible(true);
        expect($getCatalogCategoryProductTable->invoke($condition))->toContain('catalog_category_product');
        
        $getNameAttributeId = $reflection->getMethod('_getNameAttributeId');
        $getNameAttributeId->setAccessible(true);
        $nameAttrId = $getNameAttributeId->invoke($condition);
        expect($nameAttrId)->toBeGreaterThan(0);
    });

    test('attribute name and string representation work correctly', function () {
        $this->condition->setAttribute('product_name');
        
        $attributeName = $this->condition->getAttributeName();
        expect($attributeName)->toContain('Viewed');
        
        $this->condition->setOperator('{}');
        $this->condition->setValue('Test');
        
        $stringRepresentation = $this->condition->asString();
        expect($stringRepresentation)->toContain('Viewed');
        expect($stringRepresentation)->toContain('Product Name');
    });

});

// Helper method to set up test data
function setupTestData() {
    // This would normally set up test customers, products, and viewed product records
    // For now, we'll just ensure the tables exist and are accessible
    $tables = [
        'report_viewed_product_index',
        'catalog_product_entity',
        'catalog_product_entity_varchar',
        'catalog_category_product'
    ];
    
    foreach ($tables as $table) {
        $tableName = Mage::getSingleton('core/resource')->getTableName($table);
        expect($tableName)->toBeString();
    }
}