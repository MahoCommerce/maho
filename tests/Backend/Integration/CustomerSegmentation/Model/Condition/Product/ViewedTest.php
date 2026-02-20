<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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
            'view_count', 'last_viewed_at', 'days_since_last_view',
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
            expect($sql)->toContain('COUNT(*)');
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
            // Check for MySQL (DATEDIFF), PostgreSQL (DATE() or ::date), or SQLite (JULIANDAY) syntax
            expect($sql)->toMatch('/DATEDIFF|::date|DATE\\(|JULIANDAY/');
            expect($sql)->toMatch('/202[5-9]-/'); // Verify date is properly formatted
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

        $getReportViewedTable = $reflection->getMethod('getReportViewedTable');
        $getReportViewedTable->setAccessible(true);
        expect($getReportViewedTable->invoke($condition))->toContain('report_viewed_product_index');

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
        expect($attributeName)->toContain('Viewed');

        $this->condition->setOperator('{}');
        $this->condition->setValue('Test');

        $stringRepresentation = $this->condition->asString();
        expect($stringRepresentation)->toContain('Viewed');
        expect($stringRepresentation)->toContain('Product Name');
    });

});

// Helper methods for test data setup
function setupTestData()
{
    // Legacy compatibility function - calls the new comprehensive setup
    setupProductViewedTestData();
}

function setupProductViewedTestData(): void
{
    $uniqueId = uniqid('viewed_', true);

    // Ensure we have test categories
    $electronicsCategory = createTestCategory('Electronics', 'electronics-test');
    $clothingCategory = createTestCategory('Clothing', 'clothing-test');

    // Create test products
    $laptop = createTestProduct('Test Laptop Pro', 'test-laptop-pro-' . $uniqueId, (int) $electronicsCategory->getId());
    $shirt = createTestProduct('Test Shirt Cotton', 'test-shirt-cotton-' . $uniqueId, (int) $clothingCategory->getId());
    $phone = createTestProduct('Test Smartphone', 'test-smartphone-' . $uniqueId, (int) $electronicsCategory->getId());

    // Create test customers with varying view patterns
    $customers = [
        // High-frequency viewer (views multiple products frequently)
        [
            'firstname' => 'Heavy',
            'lastname' => 'Viewer',
            'email' => "heavy.viewer.{$uniqueId}@test.com",
            'views' => [
                ['product_id' => $laptop->getId(), 'days_ago' => 2],
                ['product_id' => $laptop->getId(), 'days_ago' => 3],
                ['product_id' => $laptop->getId(), 'days_ago' => 5],
                ['product_id' => $phone->getId(), 'days_ago' => 3],
                ['product_id' => $phone->getId(), 'days_ago' => 4],
            ],
        ],
        // Recent viewer (views in last few days)
        [
            'firstname' => 'Recent',
            'lastname' => 'Viewer',
            'email' => "recent.viewer.{$uniqueId}@test.com",
            'views' => [
                ['product_id' => $shirt->getId(), 'days_ago' => 1],
                ['product_id' => $laptop->getId(), 'days_ago' => 2],
            ],
        ],
        // Old viewer (hasn't viewed recently)
        [
            'firstname' => 'Old',
            'lastname' => 'Viewer',
            'email' => "old.viewer.{$uniqueId}@test.com",
            'views' => [
                ['product_id' => $shirt->getId(), 'days_ago' => 35],
                ['product_id' => $phone->getId(), 'days_ago' => 40],
            ],
        ],
        // Category-specific viewer (only views electronics)
        [
            'firstname' => 'Electronics',
            'lastname' => 'Fan',
            'email' => "electronics.fan.{$uniqueId}@test.com",
            'views' => [
                ['product_id' => $laptop->getId(), 'days_ago' => 7],
                ['product_id' => $phone->getId(), 'days_ago' => 10],
                ['product_id' => $laptop->getId(), 'days_ago' => 12],
            ],
        ],
        // Low-frequency viewer
        [
            'firstname' => 'Light',
            'lastname' => 'Viewer',
            'email' => "light.viewer.{$uniqueId}@test.com",
            'views' => [
                ['product_id' => $shirt->getId(), 'days_ago' => 20],
            ],
        ],
        // Customer with no views (for control)
        [
            'firstname' => 'No',
            'lastname' => 'Views',
            'email' => "no.views.{$uniqueId}@test.com",
            'views' => [],
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

        // Create product view records
        $reportTable = Mage::getSingleton('core/resource')->getTableName('reports/viewed_product_index');
        foreach ($customerData['views'] as $viewData) {
            $viewDate = date('Y-m-d H:i:s', strtotime("-{$viewData['days_ago']} days"));

            Mage::getSingleton('core/resource')->getConnection('core_write')->insertOnDuplicate(
                $reportTable,
                [
                    'visitor_id' => null,
                    'customer_id' => $customer->getId(),
                    'product_id' => $viewData['product_id'],
                    'store_id' => 1,
                    'added_at' => $viewDate,
                ],
                ['added_at'], // Only update the added_at field on duplicate
            );
        }
    }
}

function createTestCategory(string $name, string $urlKey): Mage_Catalog_Model_Category
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

function createTestProduct(string $name, string $sku, int $categoryId): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setName($name);
    $product->setSku($sku);
    $product->setPrice(99.99);
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

function createProductViewedTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
    $segment->setDescription('Product viewed test segment for ' . $name);
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
