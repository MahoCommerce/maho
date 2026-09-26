<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 conditions and actions trees of cart price rules.
 *
 * @group write
 */

const CPRC_PATH = '/api/rest/v2/cart-price-rules';

afterAll(function (): void {
    foreach (cprcState()['rules'] as $ruleId) {
        $rule = Mage::getModel('salesrule/rule')->load($ruleId);
        if ($rule->getId()) {
            $rule->delete();
        }
    }
    foreach (cprcState()['attributes'] as $attributeId) {
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
        if ($attribute->getId()) {
            $attribute->delete();
        }
    }
    cprcCleanAttributeCaches();
    cleanupTestData();
});

function &cprcState(): array
{
    static $state = ['rules' => [], 'attributes' => []];
    return $state;
}

function cprcTrack(int $ruleId): int
{
    cprcState()['rules'][] = $ruleId;
    return $ruleId;
}

function cprcCategoryId(): int
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()->from(Mage::getSingleton('core/resource')->getTableName('catalog/category'), ['entity_id'])
            ->where('level = ?', 2)->order('entity_id')->limit(1),
    );
}

function cprcRoot(array $children, string $type = 'salesrule/rule_condition_combine'): array
{
    return ['type' => $type, 'aggregator' => 'all', 'value' => true, 'conditions' => $children];
}

function cprcSubtotal(string $value = '50'): array
{
    return ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => $value];
}

/** The example tree: subtotal >= 50 and an item of the category with a quantity of 2 or more. */
function cprcExampleConditions(int $categoryId, string $subtotal = '50'): array
{
    return cprcRoot([
        cprcSubtotal($subtotal),
        [
            'type' => 'salesrule/rule_condition_product_found', 'aggregator' => 'all', 'value' => true,
            'conditions' => [
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'category_ids', 'operator' => '==', 'value' => [(string) $categoryId]],
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'quote_item_qty', 'operator' => '>=', 'value' => '2'],
            ],
        ],
    ]);
}

function cprcCreate(array $fields = []): array
{
    $response = apiPost(CPRC_PATH, $fields + [
        'name' => 'Pest tree rule ' . substr(uniqid(), -6),
        'websiteIds' => [1],
        'customerGroupIds' => [0, 1],
    ], adminToken());
    if (isset($response['json']['id'])) {
        cprcTrack((int) $response['json']['id']);
    }
    return $response;
}

function cprcWithoutLabels(?array $tree): ?array
{
    if ($tree === null) {
        return null;
    }
    unset($tree['label']);
    if (isset($tree['conditions'])) {
        $tree['conditions'] = array_map(cprcWithoutLabels(...), $tree['conditions']);
    }
    return $tree;
}

function cprcErrorFields(array $response): array
{
    return array_column($response['json']['details']['errors'] ?? [], 'field');
}

function cprcCleanAttributeCaches(): void
{
    Mage::app()->getCache()->cleanType('eav');
    Mage::app()->getCache()->clean([Mage_Eav_Model_Entity_Attribute::CACHE_TAG]);
}

function cprcCreateAttribute(string $code, string $backendType, bool $usedForPromoRules): void
{
    $attribute = Mage::getModel('catalog/resource_eav_attribute');
    $attribute->setData([
        'entity_type_id' => (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId(),
        'attribute_code' => $code,
        'backend_type' => $backendType,
        'frontend_input' => 'price',
        'frontend_label' => 'Pest ' . $code,
        'is_user_defined' => 1,
        'is_global' => 1,
        'is_visible' => 1,
        'is_used_for_promo_rules' => $usedForPromoRules ? 1 : 0,
    ])->save();
    cprcState()['attributes'][] = (int) $attribute->getId();
    cprcCleanAttributeCaches();
}

describe('Cart price rule trees', function (): void {

    it('reads a tree that the admin built, in the same format that the API writes', function (): void {
        $categoryId = cprcCategoryId();

        /** @var Mage_SalesRule_Model_Rule $adminRule */
        $adminRule = Mage::getModel('salesrule/rule');
        $adminRule->loadPost([
            'name' => 'Pest admin tree ' . substr(uniqid(), -6),
            'is_active' => 0,
            'website_ids' => [1],
            'customer_group_ids' => [0, 1],
            'coupon_type' => Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON,
            'simple_action' => 'by_percent',
            'discount_amount' => 10,
            'conditions' => [
                '1' => ['type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
                '1--1' => ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '50'],
                '1--2' => ['type' => 'salesrule/rule_condition_product_found', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
                '1--2--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'category_ids', 'operator' => '==', 'value' => (string) $categoryId],
                '1--2--2' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'quote_item_qty', 'operator' => '>=', 'value' => '2'],
            ],
            'actions' => [
                '1' => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
                '1--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'category_ids', 'operator' => '==', 'value' => (string) $categoryId],
            ],
        ]);
        $adminRule->save();
        cprcTrack((int) $adminRule->getId());

        $adminGet = apiGet(CPRC_PATH . '/' . $adminRule->getId(), adminToken());
        expect($adminGet['status'])->toBe(200)
            ->and(cprcWithoutLabels($adminGet['json']['conditions']))->toBe(cprcExampleConditions($categoryId))
            ->and($adminGet['json']['conditions']['conditions'][0]['label'])->toBe('Subtotal equals or greater than 50');

        $apiCreate = cprcCreate([
            'conditions' => cprcExampleConditions($categoryId),
            'actions' => cprcRoot([
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'category_ids', 'operator' => '==', 'value' => [(string) $categoryId]],
            ], 'salesrule/rule_condition_product_combine'),
        ]);
        expect($apiCreate['status'])->toBe(201)
            ->and(cprcWithoutLabels($apiCreate['json']['conditions']))->toBe(cprcWithoutLabels($adminGet['json']['conditions']))
            ->and(cprcWithoutLabels($apiCreate['json']['actions']))->toBe(cprcWithoutLabels($adminGet['json']['actions']));

        // The stored arrays are equal too, apart from the flag that asArray() adds
        $stored = function (int $ruleId): array {
            $rule = Mage::getModel('salesrule/rule')->load($ruleId);
            $strip = function (array $node) use (&$strip): array {
                unset($node['is_value_processed']);
                $node['value'] = is_bool($node['value']) ? ($node['value'] ? '1' : '0') : $node['value'];
                $node['conditions'] = array_map($strip, $node['conditions'] ?? []);
                return $node;
            };
            return $strip($rule->getConditions()->asArray());
        };
        expect($stored((int) $apiCreate['json']['id']))->toEqual($stored((int) $adminRule->getId()));
    });

    it('replaces a tree without a merge and keeps the trees that the body leaves out', function (): void {
        $created = cprcCreate([
            'conditions' => cprcRoot([cprcSubtotal('10')]),
            'actions' => cprcRoot([
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'quote_item_qty', 'operator' => '>=', 'value' => '3'],
            ], 'salesrule/rule_condition_product_combine'),
        ]);
        expect($created['status'])->toBe(201);
        $id = $created['json']['id'];

        $replaced = apiPut(CPRC_PATH . "/{$id}", ['conditions' => cprcRoot([cprcSubtotal('20')])], adminToken());
        expect($replaced['status'])->toBe(200)
            ->and(cprcWithoutLabels($replaced['json']['conditions']))->toBe(cprcRoot([cprcSubtotal('20')]))
            ->and(cprcWithoutLabels($replaced['json']['actions']))->toBe(cprcWithoutLabels($created['json']['actions']));

        $renamed = apiPut(CPRC_PATH . "/{$id}", ['name' => 'Pest renamed tree rule'], adminToken());
        expect($renamed['status'])->toBe(200)
            ->and($renamed['json']['conditions'])->toBe($replaced['json']['conditions'])
            ->and($renamed['json']['actions'])->toBe($replaced['json']['actions']);

        $reset = apiPut(CPRC_PATH . "/{$id}", ['actions' => null], adminToken());
        expect($reset['status'])->toBe(200)
            ->and($reset['json']['actions']['conditions'])->toBe([])
            ->and($reset['json']['conditions'])->toBe($replaced['json']['conditions']);

        // A client can send back the tree that it read, labels included
        $echo = apiPut(CPRC_PATH . "/{$id}", ['conditions' => $reset['json']['conditions']], adminToken());
        expect($echo['status'])->toBe(200)
            ->and($echo['json']['conditions'])->toBe($reset['json']['conditions']);
    });

    it('rejects a cart condition in the actions tree', function (): void {
        $response = cprcCreate(['actions' => cprcRoot([cprcSubtotal()], 'salesrule/rule_condition_product_combine')]);

        expect($response['status'])->toBe(400)
            ->and($response['json']['error'])->toBe('validation_error')
            ->and(cprcErrorFields($response))->toBe(['actions.conditions[0].type']);
    });

    it('rejects types that could create other classes, and saves nothing', function (string $type): void {
        $name = 'Pest injection ' . substr(uniqid(), -6);
        $nested = cprcRoot([cprcSubtotal(), cprcRoot([['type' => $type, 'attribute' => 'x', 'operator' => '==', 'value' => '1']])]);

        $atRoot = cprcCreate(['name' => $name, 'conditions' => ['type' => $type, 'conditions' => []]]);
        $inside = cprcCreate(['name' => $name, 'conditions' => $nested]);
        $inActions = cprcCreate(['name' => $name, 'actions' => cprcRoot([['type' => $type]], 'salesrule/rule_condition_product_combine')]);

        expect($atRoot['status'])->toBe(400)
            ->and(cprcErrorFields($atRoot))->toBe(['conditions.type'])
            ->and($inside['status'])->toBe(400)
            ->and(cprcErrorFields($inside))->toBe(['conditions.conditions[1].conditions[0].type'])
            ->and($inActions['status'])->toBe(400)
            ->and(cprcErrorFields($inActions))->toBe(['actions.conditions[0].type']);

        $saved = Mage::getResourceModel('salesrule/rule_collection')->addFieldToFilter('name', $name);
        expect($saved->getSize())->toBe(0);
    })->with([
        'class name' => ['Mage_Core_Model_Config'],
        'model alias' => ['core/config'],
        'the rule model' => ['salesrule/rule'],
        'namespaced class' => [\Maho\DataObject::class],
    ]);

    it('rejects unknown attributes, operators, keys and wrong value shapes with the path of each error', function (): void {
        $response = cprcCreate(['conditions' => cprcRoot([
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'grand_total', 'operator' => '>=', 'value' => '1'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => 'LIKE', 'value' => '1'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '()', 'value' => '1'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => ['1']],
            cprcSubtotal() + ['is_value_parsed' => true],
            cprcSubtotal('abc'),
        ])]);

        expect($response['status'])->toBe(400)
            ->and(cprcErrorFields($response))->toBe([
                'conditions.conditions[0].attribute',
                'conditions.conditions[1].operator',
                'conditions.conditions[2].value',
                'conditions.conditions[3].value',
                'conditions.conditions[4].is_value_parsed',
                'conditions.conditions[5].value',
            ]);
    });

    it('limits the depth and the size of a tree', function (): void {
        $deep = cprcRoot([]);
        for ($level = 0; $level < 10; $level++) {
            $deep = cprcRoot([$deep]);
        }
        $depth = cprcCreate(['conditions' => $deep]);
        expect($depth['status'])->toBe(400)
            ->and($depth['json']['message'])->toContain('levels');

        $large = cprcCreate(['conditions' => cprcRoot(array_fill(0, 250, cprcSubtotal()))]);
        expect($large['status'])->toBe(400)
            ->and(cprcErrorFields($large))->toBe(['conditions.conditions[249]']);
    });

    it('loads a list value of a decimal attribute', function (): void {
        $code = 'pest_cpr_decimal_' . substr(uniqid(), -5);
        cprcCreateAttribute($code, 'decimal', true);

        $response = cprcCreate(['actions' => cprcRoot([
            ['type' => 'salesrule/rule_condition_product', 'attribute' => $code, 'operator' => '()', 'value' => ['10', '20.5']],
        ], 'salesrule/rule_condition_product_combine')]);

        expect($response['status'])->toBe(201)
            ->and($response['json']['actions']['conditions'][0]['value'])->toBe(['10', '20.5']);
    });

    it('accepts an unchanged stored condition that the checks of today reject', function (): void {
        $legacy = ['type' => 'salesrule/rule_condition_address', 'attribute' => 'country_id', 'operator' => '==', 'value' => 'XX'];
        $rule = Mage::getModel('salesrule/rule');
        $rule->setData([
            'name' => 'Pest legacy tree ' . substr(uniqid(), -6),
            'is_active' => 0,
            'website_ids' => [1],
            'customer_group_ids' => [1],
            'coupon_type' => Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON,
            'simple_action' => 'by_percent',
            'discount_amount' => 5,
            'conditions_serialized' => json_encode(cprcRoot([$legacy])),
        ])->save();
        $id = cprcTrack((int) $rule->getId());

        $read = apiGet(CPRC_PATH . "/{$id}", adminToken());
        expect($read['json']['conditions']['conditions'][0]['value'])->toBe('XX');

        $conditions = $read['json']['conditions'];
        $conditions['conditions'][] = cprcSubtotal('5');
        $kept = apiPut(CPRC_PATH . "/{$id}", ['conditions' => $conditions], adminToken());
        expect($kept['status'])->toBe(200)
            ->and(cprcWithoutLabels($kept['json']['conditions'])['conditions'])->toBe([$legacy, cprcSubtotal('5')]);

        $changed = apiPut(CPRC_PATH . "/{$id}", ['conditions' => cprcRoot([['operator' => '!='] + $legacy])], adminToken());
        expect($changed['status'])->toBe(400)
            ->and(cprcErrorFields($changed))->toBe(['conditions.conditions[0].value']);
    });

    it('applies a rule from the API to a cart that matches its trees, and not to one that does not', function (): void {
        $product = loadSimplePricedProduct();
        $categoryIds = $product->getCategoryIds();
        if ($categoryIds === []) {
            $this->markTestSkipped('The priced product has no category');
        }
        $categoryId = (int) reset($categoryIds);
        $code = 'PESTTREE' . strtoupper(substr(uniqid(), -6));

        $created = cprcCreate([
            'isActive' => true,
            'couponType' => 'specific',
            'couponCode' => $code,
            'simpleAction' => 'by_percent',
            'discountAmount' => 10,
            'conditions' => cprcExampleConditions($categoryId, (string) round((float) $product->getPrice() * 2 - 0.01, 2)),
            'actions' => cprcRoot([
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'category_ids', 'operator' => '==', 'value' => [(string) $categoryId]],
            ], 'salesrule/rule_condition_product_combine'),
        ]);
        expect($created['status'])->toBe(201);

        $cart = function (int $qty) use ($product, $code): Mage_Sales_Model_Quote {
            $quote = Mage::getModel('sales/quote');
            $quote->setStoreId(1);
            $quote->addProduct($product, $qty);
            $quote->getShippingAddress()->setCountryId('US')->setRegionId(12)->setPostcode('90210');
            $quote->setCouponCode($code);
            $quote->collectTotals();
            return $quote;
        };

        $matching = $cart(2);
        expect($matching->getCouponCode())->toBe($code)
            ->and((float) $matching->getShippingAddress()->getDiscountAmount())->toBeLessThan(0.0);

        $single = $cart(1);
        expect((string) $single->getCouponCode())->toBe('')
            ->and((float) $single->getShippingAddress()->getDiscountAmount())->toBe(0.0);
    });

});
