<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The manual order of a category (issue #1624, rules 4 and 5): a product that enters the category goes to the
 * top or to the bottom, and a pinned product keeps its slot when other products enter or leave.
 */
function manualOrderProducts(int $count): array
{
    $token = uniqid();
    $ids = [];
    for ($index = 0; $index < $count; $index++) {
        $product = Mage::getModel('catalog/product');
        $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        $product->setName("manual-order-$token-$index");
        $product->setSku("manual-order-$token-$index");
        $product->setPrice(10.00);
        $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
        $product->setAttributeSetId(4);
        $product->setWebsiteIds([1]);
        $product->save();
        $ids[] = (int) $product->getId();
    }

    return $ids;
}

function manualOrderCategory(string $placement = 'top'): Mage_Catalog_Model_Category
{
    $category = Mage::getModel('catalog/category');
    $category->setName('manual-order-' . uniqid());
    $category->setPath('1/2');
    $category->setIsActive(1);
    $category->setIncludeInMenu(0);
    $category->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);
    $category->setAttributeSetId($category->getDefaultAttributeSetId());
    $category->setNewProductPlacement($placement);
    $category->save();

    return $category;
}

/**
 * @param array<int, int> $positions
 * @param list<int>|null $pinned
 */
function manualOrderSave(Mage_Catalog_Model_Category $category, array $positions, ?array $pinned = null): void
{
    $category = Mage::getModel('catalog/category')->load($category->getId());
    $category->setPostedProducts($positions);
    if ($pinned !== null) {
        $category->setPostedPinnedProducts($pinned);
    }
    $category->save();
}

/**
 * @return array<int, array{position: int, is_pinned: int}> by product ID
 */
function manualOrderRows(Mage_Catalog_Model_Category $category): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $rows = $adapter->fetchAll($adapter->select()
        ->from($resource->getTableName('catalog/category_product'), ['product_id', 'position', 'is_pinned'])
        ->where('category_id = ?', (int) $category->getId()));

    $result = [];
    foreach ($rows as $row) {
        $result[(int) $row['product_id']] = ['position' => (int) $row['position'], 'is_pinned' => (int) $row['is_pinned']];
    }

    return $result;
}

/**
 * @return list<int> the product IDs in the order of their positions
 */
function manualOrderList(Mage_Catalog_Model_Category $category): array
{
    $rows = manualOrderRows($category);
    uksort($rows, static fn(int $a, int $b): int => [$rows[$a]['position'], $a] <=> [$rows[$b]['position'], $b]);

    return array_keys($rows);
}

it('puts a product that enters the category at the top when the category says top', function () {
    [$a, $b, $c] = manualOrderProducts(3);
    $category = manualOrderCategory('top');
    manualOrderSave($category, [$a => 1, $b => 2]);

    manualOrderSave($category, [$a => 1, $b => 2, $c => 0]);

    expect(manualOrderList($category))->toBe([$c, $a, $b]);
});

it('puts a product that enters the category at the bottom when the category says bottom', function () {
    [$a, $b, $c] = manualOrderProducts(3);
    $category = manualOrderCategory('bottom');
    manualOrderSave($category, [$a => 1, $b => 2]);

    manualOrderSave($category, [$a => 1, $b => 2, $c => 0]);

    expect(manualOrderList($category))->toBe([$a, $b, $c])
        ->and(manualOrderRows($category)[$c]['position'])->toBe(3);
});

it('keeps the position that the admin types for a product that enters the category', function () {
    [$a, $b, $c] = manualOrderProducts(3);
    $category = manualOrderCategory('bottom');
    manualOrderSave($category, [$a => 1, $b => 2]);

    manualOrderSave($category, [$a => 1, $b => 2, $c => 1]);

    expect(manualOrderRows($category)[$c]['position'])->toBe(1)
        ->and(manualOrderRows($category)[$a]['position'])->toBe(1);
});

it('leaves the positions of the other products unchanged when a product enters and nothing is pinned', function () {
    [$a, $b, $c] = manualOrderProducts(3);
    $category = manualOrderCategory('top');
    manualOrderSave($category, [$a => 10, $b => 20]);

    manualOrderSave($category, [$a => 10, $b => 20, $c => 0]);

    $rows = manualOrderRows($category);
    expect($rows[$a]['position'])->toBe(10)
        ->and($rows[$b]['position'])->toBe(20)
        ->and($rows[$c]['position'])->toBeLessThan(10);
});

it('keeps a pinned product in its slot when another product enters', function () {
    [$a, $b, $c, $d, $e] = manualOrderProducts(5);
    $category = manualOrderCategory('top');
    manualOrderSave($category, [$a => 1, $b => 2, $c => 3, $d => 4], [$b]);

    manualOrderSave($category, [$a => 1, $b => 2, $c => 3, $d => 4, $e => 0]);

    expect(manualOrderList($category))->toBe([$e, $b, $a, $c, $d])
        ->and(manualOrderRows($category)[$b])->toBe(['position' => 2, 'is_pinned' => 1]);
});

it('keeps a pinned product in its slot when another product leaves', function () {
    [$a, $b, $c, $d, $e] = manualOrderProducts(5);
    $category = manualOrderCategory('top');
    manualOrderSave($category, [$a => 1, $b => 2, $c => 3, $d => 4, $e => 5], [$c]);

    manualOrderSave($category, [$b => 2, $c => 3, $d => 4, $e => 5]);

    expect(manualOrderList($category))->toBe([$b, $d, $c, $e])
        ->and(manualOrderRows($category)[$c]['position'])->toBe(3);
});

it('writes the pins, keeps them when the post holds none, and removes them when the post is empty', function () {
    [$a, $b] = manualOrderProducts(2);
    $category = manualOrderCategory();

    manualOrderSave($category, [$a => 1, $b => 2], [$a]);
    expect(manualOrderRows($category)[$a]['is_pinned'])->toBe(1)
        ->and(manualOrderRows($category)[$b]['is_pinned'])->toBe(0);

    manualOrderSave($category, [$a => 1, $b => 2]);
    expect(manualOrderRows($category)[$a]['is_pinned'])->toBe(1);

    manualOrderSave($category, [$a => 1, $b => 2], []);
    expect(manualOrderRows($category)[$a]['is_pinned'])->toBe(0);
});

it('ignores a pin for a product that is not in the category', function () {
    [$a, $b] = manualOrderProducts(2);
    $category = manualOrderCategory();

    manualOrderSave($category, [$a => 1], [$a, $b]);

    expect(array_keys(manualOrderRows($category)))->toBe([$a]);
});

it('puts the products that a dynamic category rule adds at the place that the category says', function (string $placement, string $order) {
    [$a, $b, $c] = manualOrderProducts(3);
    $prefix = substr((string) Mage::getModel('catalog/product')->load($a)->getSku(), 0, -1);

    $category = Mage::getModel('catalog/category');
    $category->setName('manual-order-rule-' . uniqid());
    $category->setPath('1/2');
    $category->setIsActive(1);
    $category->setIncludeInMenu(0);
    $category->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);
    $category->setAttributeSetId($category->getDefaultAttributeSetId());
    $category->setIsDynamic(1);
    $category->setNewProductPlacement($placement);
    $category->setPostedProducts([$a => 1]);
    $category->save();

    $rule = Mage::getModel('catalog/category_dynamic_rule');
    $rule->setCategoryId((int) $category->getId());
    $rule->setIsActive();
    $rule->setParentResolution(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_NONE);
    $rule->getConditions()->loadArray([
        'type' => 'catalog/category_dynamic_rule_condition_combine',
        'aggregator' => 'all',
        'value' => 1,
        'conditions' => [[
            'type' => 'catalog/category_dynamic_rule_condition_product',
            'attribute' => 'sku',
            'operator' => '{}',
            'value' => $prefix,
        ]],
    ]);
    $rule->save();

    Mage::getModel('catalog/category_dynamic_processor')
        ->processDynamicCategory(Mage::getModel('catalog/category')->load($category->getId()));

    $ids = ['a' => $a, 'b' => $b, 'c' => $c];
    expect(manualOrderList($category))->toBe(array_map(static fn(string $key): int => $ids[$key], str_split($order)));
})->with([
    'top' => ['top', 'bca'],
    'bottom' => ['bottom', 'abc'],
]);
