<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for issue #1140: dynamic categories.
 *
 * 1. Manual product positions must survive a recalculation.
 * 2. Stock lives outside EAV, so it needs its own condition type.
 * 3. A rule that matches simples can be told to assign their parents instead.
 */
function dynProduct(string $sku, array $stockData = [], array $data = []): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
    $product->setName($sku);
    $product->setSku($sku);
    $product->setPrice(10.00);
    $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setAttributeSetId(4);
    $product->setWebsiteIds([1]);
    $product->addData($data);
    if ($stockData !== []) {
        $product->setStockData($stockData + ['use_config_manage_stock' => 0, 'manage_stock' => 1]);
    }
    $product->save();

    return $product;
}

/**
 * catalog_product_relation is what parent resolution reads, so seed it directly rather than
 * building real configurables with attribute sets the fixture store may not have.
 */
function dynRelate(int $parentId, int $childId): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->insert(
        Mage::getSingleton('core/resource')->getTableName('catalog/product_relation'),
        ['parent_id' => $parentId, 'child_id' => $childId],
    );
}

function dynCategory(array $productPositions = []): Mage_Catalog_Model_Category
{
    $category = Mage::getModel('catalog/category');
    $category->setName('dyncat-' . uniqid());
    $category->setPath('1/2');
    $category->setIsActive(1);
    $category->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);
    $category->setAttributeSetId($category->getDefaultAttributeSetId());
    $category->setIsDynamic(1);
    if ($productPositions !== []) {
        $category->setPostedProducts($productPositions);
    }
    $category->save();

    return Mage::getModel('catalog/category')->load($category->getId());
}

/**
 * Persist a rule for the category. $conditions is the flat condition list of a single "all/true"
 * combine, in the same shape loadArray() gets from the serialized column.
 */
function dynRule(
    Mage_Catalog_Model_Category $category,
    array $conditions,
    string $parentResolution = Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_NONE,
): Mage_Catalog_Model_Category_Dynamic_Rule {
    $rule = Mage::getModel('catalog/category_dynamic_rule');
    $rule->setCategoryId((int) $category->getId());
    $rule->setIsActive(1);
    $rule->setParentResolution($parentResolution);
    $rule->getConditions()->loadArray([
        'type' => 'catalog/category_dynamic_rule_condition_combine',
        'aggregator' => 'all',
        'value' => 1,
        'conditions' => $conditions,
    ]);
    $rule->save();

    return $rule;
}

function dynStockCondition(string $attribute, string $operator, string $value): array
{
    return [
        'type' => 'catalog/category_dynamic_rule_condition_stock',
        'attribute' => $attribute,
        'operator' => $operator,
        'value' => $value,
    ];
}

function dynSkuCondition(string $operator, string $value): array
{
    return [
        'type' => 'catalog/category_dynamic_rule_condition_product',
        'attribute' => 'sku',
        'operator' => $operator,
        'value' => $value,
    ];
}

function dynProcess(Mage_Catalog_Model_Category $category): void
{
    Mage::getModel('catalog/category_dynamic_processor')
        ->processDynamicCategory(Mage::getModel('catalog/category')->load($category->getId()));
}

/**
 * @return array<int, int> productId => position
 */
function dynPositions(Mage_Catalog_Model_Category $category): array
{
    $category = Mage::getModel('catalog/category')->load($category->getId());
    $positions = $category->getResource()->getProductsPosition($category);

    return is_array($positions) ? array_map('intval', $positions) : [];
}

// ---------------------------------------------------------------------------
// 1. Manual positions must survive recalculation
// ---------------------------------------------------------------------------

it('keeps manual product positions when the matched set changes', function () {
    $token = uniqid();
    $first  = dynProduct("dyn-pos-a-$token");
    $second = dynProduct("dyn-pos-b-$token");

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('{}', 'dyn-pos-')]);

    // Products land in the category, then an admin orders them by hand
    dynProcess($category);
    expect(array_keys(dynPositions($category)))
        ->toEqualCanonicalizing([(int) $first->getId(), (int) $second->getId()]);

    $category->setPostedProducts([(int) $first->getId() => 7, (int) $second->getId() => 3]);
    $category->save();

    // A third product entering the category is what used to trigger the reset
    $third = dynProduct("dyn-pos-c-$token");
    dynProcess($category);

    $positions = dynPositions($category);
    expect($positions[(int) $first->getId()])->toBe(7)
        ->and($positions[(int) $second->getId()])->toBe(3)
        ->and($positions[(int) $third->getId()])->toBe(0);
});

it('still removes products that stop matching', function () {
    $token = uniqid();
    $keep = dynProduct("dyn-rm-keep-$token");
    $drop = dynProduct("dyn-rm-drop-$token");

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('{}', 'dyn-rm-')]);
    dynProcess($category);
    expect(dynPositions($category))->toHaveCount(2);

    $drop->setSku("dyn-gone-$token")->save();
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $keep->getId()]);
});

// ---------------------------------------------------------------------------
// 2. Stock conditions
// ---------------------------------------------------------------------------

it('offers the stock conditions in the dynamic category condition dropdown', function () {
    $combine = Mage::getModel('catalog/category_dynamic_rule_condition_combine');

    $stockGroup = null;
    foreach ($combine->getNewChildSelectOptions() as $option) {
        if (($option['label'] ?? null) === 'Stock') {
            $stockGroup = $option['value'];
        }
    }

    expect($stockGroup)->not->toBeNull()
        ->and(array_column($stockGroup, 'value'))->toBe([
            'catalog/category_dynamic_rule_condition_stock|is_salable',
            'catalog/category_dynamic_rule_condition_stock|is_in_stock',
            'catalog/category_dynamic_rule_condition_stock|qty',
            'catalog/category_dynamic_rule_condition_stock|children_qty',
        ]);
});

it('matches on quantity in stock', function () {
    $token = uniqid();
    $plenty = dynProduct("dyn-qty-a-$token", ['qty' => 50, 'is_in_stock' => 1]);
    $few    = dynProduct("dyn-qty-b-$token", ['qty' => 2, 'is_in_stock' => 1]);

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('{}', 'dyn-qty-'), dynStockCondition('qty', '>=', '10')]);
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $plenty->getId()])
        ->and(array_keys(dynPositions($category)))->not->toContain((int) $few->getId());
});

it('matches on in stock', function () {
    $token = uniqid();
    $inStock  = dynProduct("dyn-instock-a-$token", ['qty' => 5, 'is_in_stock' => 1]);
    $outStock = dynProduct("dyn-instock-b-$token", ['qty' => 0, 'is_in_stock' => 0]);

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('{}', 'dyn-instock-'), dynStockCondition('is_in_stock', '==', '1')]);
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $inStock->getId()])
        ->and(array_keys(dynPositions($category)))->not->toContain((int) $outStock->getId());
});

it('matches on salability from the stock status index', function () {
    $token = uniqid();
    $salable    = dynProduct("dyn-sal-a-$token", ['qty' => 5, 'is_in_stock' => 1]);
    $notSalable = dynProduct("dyn-sal-b-$token", ['qty' => 0, 'is_in_stock' => 0]);

    Mage::getSingleton('index/indexer')->getProcessByCode('cataloginventory_stock')->reindexEverything();

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('{}', 'dyn-sal-'), dynStockCondition('is_salable', '==', '1')]);
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $salable->getId()])
        ->and(array_keys(dynPositions($category)))->not->toContain((int) $notSalable->getId());
});

it('matches on the summed quantity of child products', function () {
    $token = uniqid();
    $parent = dynProduct("dyn-childqty-p-$token", ['qty' => 0, 'is_in_stock' => 1]);
    $lonely = dynProduct("dyn-childqty-l-$token", ['qty' => 0, 'is_in_stock' => 1]);
    $childA = dynProduct("dyn-childqty-c1-$token", ['qty' => 4, 'is_in_stock' => 1]);
    $childB = dynProduct("dyn-childqty-c2-$token", ['qty' => 6, 'is_in_stock' => 1]);
    dynRelate((int) $parent->getId(), (int) $childA->getId());
    dynRelate((int) $parent->getId(), (int) $childB->getId());

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('{}', 'dyn-childqty-'), dynStockCondition('children_qty', '>=', '10')]);
    dynProcess($category);

    // The parent's own qty is 0; only the summed children clear the threshold
    expect(array_keys(dynPositions($category)))->toBe([(int) $parent->getId()])
        ->and(array_keys(dynPositions($category)))->not->toContain((int) $lonely->getId());
});

it('treats a product with no children as having zero child quantity', function () {
    $token = uniqid();
    $lonely = dynProduct("dyn-zeroqty-$token", ['qty' => 99, 'is_in_stock' => 1]);

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('{}', 'dyn-zeroqty-'), dynStockCondition('children_qty', '==', '0')]);
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $lonely->getId()]);
});

it('does not let a joined stock column shadow a product attribute of the same name', function () {
    $token = uniqid();
    $product = dynProduct("dyn-shadow-$token", ['qty' => 3, 'is_in_stock' => 1]);

    // sku is selected on the collection alongside the stock join; both must survive
    $category = dynCategory();
    dynRule($category, [
        dynSkuCondition('==', "dyn-shadow-$token"),
        dynStockCondition('qty', '==', '3'),
    ]);
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $product->getId()]);
});

// ---------------------------------------------------------------------------
// 3. Replace matched products with their parents
// ---------------------------------------------------------------------------

it('leaves the matched set alone by default', function () {
    $token = uniqid();
    $parent = dynProduct("dyn-none-p-$token");
    $child  = dynProduct("dyn-none-c-$token");
    dynRelate((int) $parent->getId(), (int) $child->getId());

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('==', "dyn-none-c-$token")]);
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $child->getId()]);
});

it('replaces a matched child with its parent', function () {
    $token = uniqid();
    $parent = dynProduct("dyn-parent-p-$token");
    $child  = dynProduct("dyn-parent-c-$token");
    dynRelate((int) $parent->getId(), (int) $child->getId());

    $category = dynCategory();
    dynRule(
        $category,
        [dynSkuCondition('==', "dyn-parent-c-$token")],
        Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_KEEP_ORPHANS,
    );
    dynProcess($category);

    expect(array_keys(dynPositions($category)))->toBe([(int) $parent->getId()]);
});

it('returns every parent of a child that belongs to more than one', function () {
    $token = uniqid();
    $parentA = dynProduct("dyn-multi-p1-$token");
    $parentB = dynProduct("dyn-multi-p2-$token");
    $child   = dynProduct("dyn-multi-c-$token");
    dynRelate((int) $parentA->getId(), (int) $child->getId());
    dynRelate((int) $parentB->getId(), (int) $child->getId());

    $rule = Mage::getModel('catalog/category_dynamic_rule')
        ->setParentResolution(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_KEEP_ORPHANS);

    expect($rule->resolveParents([(int) $child->getId()]))
        ->toEqualCanonicalizing([(int) $parentA->getId(), (int) $parentB->getId()]);
});

it('dedupes two matched children that share a parent', function () {
    $token = uniqid();
    $parent = dynProduct("dyn-dedup-p-$token");
    $childA = dynProduct("dyn-dedup-c1-$token");
    $childB = dynProduct("dyn-dedup-c2-$token");
    dynRelate((int) $parent->getId(), (int) $childA->getId());
    dynRelate((int) $parent->getId(), (int) $childB->getId());

    $rule = Mage::getModel('catalog/category_dynamic_rule')
        ->setParentResolution(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_KEEP_ORPHANS);

    expect($rule->resolveParents([(int) $childA->getId(), (int) $childB->getId()]))
        ->toBe([(int) $parent->getId()]);
});

it('keeps a matched product with no parent when orphans are kept', function () {
    $token = uniqid();
    $orphan = dynProduct("dyn-keep-o-$token");

    $rule = Mage::getModel('catalog/category_dynamic_rule')
        ->setParentResolution(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_KEEP_ORPHANS);

    expect($rule->resolveParents([(int) $orphan->getId()]))->toBe([(int) $orphan->getId()]);
});

it('drops a matched simple with no parent when orphans are discarded', function () {
    $token = uniqid();
    $parent = dynProduct("dyn-disc-p-$token");
    $child  = dynProduct("dyn-disc-c-$token");
    $orphan = dynProduct("dyn-disc-o-$token");
    dynRelate((int) $parent->getId(), (int) $child->getId());

    $rule = Mage::getModel('catalog/category_dynamic_rule')
        ->setParentResolution(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_DISCARD_ORPHANS);

    expect($rule->resolveParents([(int) $child->getId(), (int) $orphan->getId()]))
        ->toBe([(int) $parent->getId()]);
});

it('keeps a composite product that matched the rule directly even when orphans are discarded', function () {
    $token = uniqid();
    // A configurable that matched on its own data is parentless by nature, not an orphan simple
    $configurable = dynProduct("dyn-comp-$token", [], ['type_id' => Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE]);

    $rule = Mage::getModel('catalog/category_dynamic_rule')
        ->setParentResolution(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_DISCARD_ORPHANS);

    expect($rule->resolveParents([(int) $configurable->getId()]))->toBe([(int) $configurable->getId()]);
});

it('persists the parent resolution setting through the category save path', function () {
    $category = dynCategory();
    $category->setDynamicRuleData([
        'conditions' => [1 => ['type' => 'catalog/category_dynamic_rule_condition_combine', 'aggregator' => 'all', 'value' => 1]],
        'parent_resolution' => Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_DISCARD_ORPHANS,
    ]);
    $category->save();

    $rule = Mage::getResourceModel('catalog/category_dynamic_rule_collection')
        ->addCategoryFilter((int) $category->getId())
        ->getFirstItem();

    expect($rule->getParentResolution())
        ->toBe(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_DISCARD_ORPHANS);
});

it('falls back to no replacement when the posted setting is not a known option', function () {
    $category = dynCategory();
    $category->setDynamicRuleData([
        'conditions' => [1 => ['type' => 'catalog/category_dynamic_rule_condition_combine', 'aggregator' => 'all', 'value' => 1]],
        'parent_resolution' => 'drop everything',
    ]);
    $category->save();

    $rule = Mage::getResourceModel('catalog/category_dynamic_rule_collection')
        ->addCategoryFilter((int) $category->getId())
        ->getFirstItem();

    expect($rule->getParentResolution())
        ->toBe(Mage_Catalog_Model_Category_Dynamic_Rule::PARENT_RESOLUTION_NONE);
});

// ---------------------------------------------------------------------------
// Multi-rule intersection
// ---------------------------------------------------------------------------

it('empties the category when one of several rules matches nothing', function () {
    $token = uniqid();
    $product = dynProduct("dyn-inter-$token");

    $category = dynCategory();
    dynRule($category, [dynSkuCondition('==', "dyn-no-such-sku-$token")]);
    dynRule($category, [dynSkuCondition('==', "dyn-inter-$token")]);
    dynProcess($category);

    expect(dynPositions($category))->toBe([]);
});
