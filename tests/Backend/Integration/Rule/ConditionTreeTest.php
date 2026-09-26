<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The metadata document, the validator and the writer of condition trees.
 * The validator is the only guard in front of loadArray(), which creates a model for each type it reads.
 */

function treeMetadata(int $inlineOptionLimit = Mage_Rule_Model_Condition_Metadata::DEFAULT_INLINE_OPTION_LIMIT): array
{
    $metadata = Mage::getModel('salesrule/rule_condition_metadata', ['inline_option_limit' => $inlineOptionLimit]);
    return [$metadata, Mage_Rule_Model_Condition_Metadata::runInLocale('en_US', fn() => $metadata->build())];
}

function treeValidate(array $tree, string $treeKey = 'conditions', ?array $stored = null): array
{
    [$metadata, $document] = treeMetadata();
    return (new Mage_Rule_Model_Condition_TreeValidator($metadata, $document))->validate($treeKey, $tree, $stored);
}

function treeRoot(array $children, string $type = 'salesrule/rule_condition_combine'): array
{
    return ['type' => $type, 'aggregator' => 'all', 'value' => true, 'conditions' => $children];
}

function treeErrorFields(array $result): array
{
    return array_column($result['errors'], 'field');
}

function treeExample(): array
{
    return treeRoot([
        ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '50'],
        [
            'type' => 'salesrule/rule_condition_product_found', 'aggregator' => 'all', 'value' => true,
            'conditions' => [
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'category_ids', 'operator' => '==', 'value' => [(string) treeCategoryId()]],
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'quote_item_qty', 'operator' => '>=', 'value' => '2'],
            ],
        ],
    ]);
}

function treeCategoryId(): int
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()->from(Mage::getSingleton('core/resource')->getTableName('catalog/category'), ['entity_id'])
            ->where('level = ?', 2)->order('entity_id')->limit(1),
    );
}

function treeWithoutLabels(array $tree): array
{
    unset($tree['label']);
    if (isset($tree['conditions'])) {
        $tree['conditions'] = array_map(treeWithoutLabels(...), $tree['conditions']);
    }
    return $tree;
}

describe('condition metadata', function (): void {

    it('describes every type that a child option names, and only condition classes', function (): void {
        [, $document] = treeMetadata();

        expect($document['roots'])->toBe([
            'conditions' => 'salesrule/rule_condition_combine',
            'actions' => 'salesrule/rule_condition_product_combine',
        ]);
        foreach ($document['types'] as $description) {
            foreach ($description['children'] ?? [] as $child) {
                foreach ($child['options'] ?? [$child] as $entry) {
                    expect($document['types'])->toHaveKey($entry['type']);
                }
            }
        }
        expect($document['types'])->toHaveKeys([
            'salesrule/rule_condition_product_found',
            'salesrule/rule_condition_product_subselect',
            'salesrule/rule_condition_address',
            'salesrule/rule_condition_product',
            'salesrule/rule_condition_product_attribute_assigned',
        ]);
        expect($document['types'])->not->toHaveKey('salesrule/rule');
    });

    it('describes combines, leaves, the subselection and the valueless type', function (): void {
        [, $document] = treeMetadata();
        $types = $document['types'];

        $root = $types['salesrule/rule_condition_combine'];
        expect($root['kind'])->toBe('combine')
            ->and($root['sentence'])->toBe('If {aggregator} of these conditions are {value}:')
            ->and(array_column($root['aggregator']['options'], 'value'))->toBe(['all', 'any'])
            ->and($root['value'])->toBe(['default' => true, 'options' => [
                ['value' => true, 'label' => 'TRUE'],
                ['value' => false, 'label' => 'FALSE'],
            ]]);

        expect(array_column($types['salesrule/rule_condition_product_found']['value']['options'], 'label'))->toBe(['FOUND', 'NOT FOUND']);

        $subselect = $types['salesrule/rule_condition_product_subselect'];
        expect(array_column($subselect['attributes'], 'code'))->toBe(['qty', 'base_row_total'])
            ->and($subselect['attributes'][0]['inputType'])->toBe('numeric')
            ->and(count($subselect['attributes'][0]['operators']))->toBe(8)
            ->and($subselect['value']['options'])->toBe([]);

        $address = array_column($types['salesrule/rule_condition_address']['attributes'], null, 'code');
        $operators = array_column($address['base_subtotal']['operators'], 'valueIsList', 'value');
        expect($address['base_subtotal']['inputType'])->toBe('numeric')
            ->and($operators['>='])->toBeFalse()
            ->and($operators['()'])->toBeTrue()
            ->and($address['base_subtotal']['options'])->toBeNull()
            ->and($address['region_id']['options']['paged'])->toBeTrue()
            ->and($address['region_id']['options']['inline'])->toBeNull()
            ->and($address['region_id']['options']['count'])->toBeGreaterThan(100);

        $product = array_column($types['salesrule/rule_condition_product']['attributes'], null, 'code');
        expect($product['category_ids']['inputType'])->toBe('category')
            ->and($product['category_ids']['chooser'])->toBe('category')
            ->and($product['quote_item_qty']['inputType'])->toBe('string')
            ->and($product['quote_item_qty']['numericHint'])->toBeTrue();

        $assigned = $types['salesrule/rule_condition_product_attribute_assigned'];
        expect($assigned['valueless'])->toBeTrue()
            ->and(array_column($assigned['attributes'][0]['operators'], 'value'))->toBe(['is_assigned', 'is_not_assigned']);
    });

    it('gives only the count of a list above the inline limit', function (): void {
        [, $document] = treeMetadata(2);
        $address = array_column($document['types']['salesrule/rule_condition_address']['attributes'], null, 'code');
        expect($address['payment_method']['options']['paged'])->toBeTrue()
            ->and($address['payment_method']['options']['inline'])->toBeNull();
    });

    it('leaves out product attributes that are not used for promo rules', function (): void {
        [, $document] = treeMetadata();
        $codes = array_column($document['types']['salesrule/rule_condition_product']['attributes'], 'code');
        expect($codes)->not->toContain('description');
    });

    it('restores the label flag after it runs in a locale', function (): void {
        Mage_Rule_Model_Condition_Abstract::setTranslateLabels(false);
        $inside = Mage_Rule_Model_Condition_Metadata::runInLocale(
            'en_US',
            fn() => [Mage_Rule_Model_Condition_Abstract::getTranslateLabels(), (int) Mage::app()->getStore()->getId()],
        );
        expect($inside)->toBe([true, 0])
            ->and(Mage_Rule_Model_Condition_Abstract::getTranslateLabels())->toBeFalse();
        Mage_Rule_Model_Condition_Abstract::setTranslateLabels(null);
    });

});

describe('condition tree validator', function (): void {

    it('accepts the example tree and returns the stored format', function (): void {
        $result = treeValidate(treeExample());

        expect($result['errors'])->toBe([]);
        $tree = $result['tree'];
        expect($tree['value'])->toBe('1')
            ->and($tree['conditions'][0])->toBe(['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '50'])
            ->and($tree['conditions'][1]['conditions'][0]['value'])->toBe((string) treeCategoryId());
    });

    it('rejects types outside the metadata before any model exists', function (string $type): void {
        $result = treeValidate(treeRoot([['type' => $type, 'attribute' => 'x', 'operator' => '==', 'value' => '1']]));
        expect($result['tree'])->toBeNull()
            ->and(treeErrorFields($result))->toContain('conditions.conditions[0].type');
    })->with([
        'class name' => ['Mage_Core_Model_Config'],
        'alias of a model that is not a condition' => ['core/config'],
        'the rule itself' => ['salesrule/rule'],
        'namespaced class' => ['\\Maho\\DataObject'],
        'action of catalog rules' => ['salesrule/rule_action_product'],
    ]);

    it('rejects a root of the wrong type and a cart condition in the actions tree', function (): void {
        $wrongRoot = treeValidate(treeRoot([]), 'actions');
        expect(treeErrorFields($wrongRoot))->toBe(['actions.type']);

        $address = treeValidate(treeRoot([
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '5'],
        ], 'salesrule/rule_condition_product_combine'), 'actions');
        expect(treeErrorFields($address))->toBe(['actions.conditions[0].type']);
    });

    it('rejects unknown attributes, operators, keys and wrong value shapes', function (): void {
        $result = treeValidate(treeRoot([
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'grand_total', 'operator' => '>=', 'value' => '5'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '{}', 'value' => '5'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '()', 'value' => '5'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => ['5']],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '5', 'is_value_processed' => true],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '5', 'conditions' => []],
        ]));

        expect(treeErrorFields($result))->toBe([
            'conditions.conditions[0].attribute',
            'conditions.conditions[1].operator',
            'conditions.conditions[2].value',
            'conditions.conditions[3].value',
            'conditions.conditions[4].is_value_processed',
            'conditions.conditions[5].conditions',
        ]);
    });

    it('ignores the read-only label', function (): void {
        $tree = treeExample();
        $tree['label'] = 'If ALL of these conditions are TRUE:';
        expect(treeValidate($tree)['errors'])->toBe([]);
    });

    it('checks the content of values', function (): void {
        $result = treeValidate(treeRoot([
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '5,5'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'country_id', 'operator' => '==', 'value' => 'XX'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'postcode', 'operator' => '==', 'value' => "12\n34"],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'postcode', 'operator' => '==', 'value' => '<b>1</b>'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'postcode', 'operator' => '==', 'value' => str_repeat('1', 256)],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'postcode', 'operator' => '==', 'value' => ''],
            ['type' => 'salesrule/rule_condition_product_found', 'value' => true, 'conditions' => [
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'category_ids', 'operator' => '==', 'value' => ['999999', 'abc']],
            ]],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'country_id', 'operator' => '==', 'value' => 'US'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'region_id', 'operator' => '==', 'value' => '12'],
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '()', 'value' => ['1', 2.5, 3]],
        ]));

        expect(treeErrorFields($result))->toBe([
            'conditions.conditions[0].value',
            'conditions.conditions[1].value',
            'conditions.conditions[2].value',
            'conditions.conditions[3].value',
            'conditions.conditions[4].value',
            'conditions.conditions[5].value',
            'conditions.conditions[6].conditions[0].value[1]',
            'conditions.conditions[6].conditions[0].value[0]',
        ]);
    });

    it('skips the content check for a condition that is equal to a stored one', function (): void {
        $legacy = ['type' => 'salesrule/rule_condition_address', 'attribute' => 'country_id', 'operator' => '==', 'value' => 'XX'];
        $stored = treeRoot([$legacy + ['label' => 'Shipping Country is XX']]);

        $kept = treeValidate(treeRoot([$legacy, ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '1']]), 'conditions', $stored);
        expect($kept['errors'])->toBe([])
            ->and($kept['tree']['conditions'][0]['value'])->toBe('XX');

        $changed = treeValidate(treeRoot([['operator' => '!='] + $legacy]), 'conditions', $stored);
        expect(treeErrorFields($changed))->toBe(['conditions.conditions[0].value']);

        $badShape = treeValidate(treeRoot([['value' => ['XX']] + $legacy]), 'conditions', $stored);
        expect(treeErrorFields($badShape))->toBe(['conditions.conditions[0].value']);
    });

    it('limits the depth and the size of a tree', function (): void {
        $deep = treeRoot([]);
        for ($level = 0; $level < 10; $level++) {
            $deep = treeRoot([$deep]);
        }
        $depth = treeValidate($deep);
        expect($depth['tree'])->toBeNull()
            ->and($depth['errors'][0]['message'])->toContain('levels');

        $leaf = ['type' => 'salesrule/rule_condition_address', 'attribute' => 'base_subtotal', 'operator' => '>=', 'value' => '1'];
        $large = treeValidate(treeRoot(array_fill(0, 250, $leaf)));
        expect($large['tree'])->toBeNull()
            ->and($large['errors'][0]['field'])->toBe('conditions.conditions[249]');

        $list = treeValidate(treeRoot([['operator' => '()', 'value' => array_fill(0, 1001, '1')] + $leaf]));
        expect(treeErrorFields($list))->toBe(['conditions.conditions[0].value']);
    });

    it('validates the value of a subselection and keeps it as text', function (): void {
        $subselect = ['type' => 'salesrule/rule_condition_product_subselect', 'attribute' => 'qty', 'operator' => '>=', 'value' => 3, 'aggregator' => 'all', 'conditions' => []];
        $result = treeValidate(treeRoot([$subselect, ['value' => 'many'] + $subselect]));

        expect(treeErrorFields($result))->toBe(['conditions.conditions[1].value']);
        expect(treeValidate(treeRoot([$subselect]))['tree']['conditions'][0])->toBe([
            'type' => 'salesrule/rule_condition_product_subselect',
            'attribute' => 'qty',
            'operator' => '>=',
            'value' => '3',
            'aggregator' => 'all',
            'conditions' => [],
        ]);
    });

});

describe('condition tree writer', function (): void {

    it('replaces a tree and reads it back in the wire format', function (): void {
        [$metadata, $document] = treeMetadata();
        $clean = (new Mage_Rule_Model_Condition_TreeValidator($metadata, $document))->validate('conditions', treeExample())['tree'];
        $writer = new Mage_Rule_Model_Condition_TreeWriter($document);

        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = Mage::getModel('salesrule/rule');
        $rule->setConditionsSerialized((string) json_encode(treeRoot([
            ['type' => 'salesrule/rule_condition_address', 'attribute' => 'total_qty', 'operator' => '>=', 'value' => '9'],
        ])));
        $writer->replaceTree($rule, 'conditions', $clean);

        $wire = Mage_Rule_Model_Condition_Metadata::runInLocale('en_US', fn() => $writer->readTree($rule, 'conditions'));
        expect(treeWithoutLabels($wire))->toBe([
            'type' => 'salesrule/rule_condition_combine',
            'aggregator' => 'all',
            'value' => true,
            'conditions' => treeExample()['conditions'],
        ]);
        expect($wire['label'])->toBe('If ALL of these conditions are TRUE:')
            ->and($wire['conditions'][0]['label'])->toBe('Subtotal equals or greater than 50')
            ->and($wire['conditions'][1]['label'])->toBe('If an item is FOUND in the cart with ALL of these conditions true:');
    });

    it('loads a list value of a decimal attribute without a type error', function (): void {
        [, $document] = treeMetadata();
        $rule = Mage::getModel('salesrule/rule');
        (new Mage_Rule_Model_Condition_TreeWriter($document))->replaceTree($rule, 'actions', [
            'type' => 'salesrule/rule_condition_product_combine',
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [
                ['type' => 'salesrule/rule_condition_product', 'attribute' => 'price', 'operator' => '()', 'value' => '10,20.5'],
            ],
        ]);
        expect($rule->getActions()->asArray()['conditions'][0]['value'])->toBe('10,20.5');
    });

    it('refuses a tree that does not load completely', function (): void {
        [, $document] = treeMetadata();
        $rule = Mage::getModel('salesrule/rule');
        $writer = new Mage_Rule_Model_Condition_TreeWriter($document);
        expect(fn() => $writer->replaceTree($rule, 'conditions', treeRoot([['type' => 'salesrule/no_such_condition']])))
            ->toThrow(RuntimeException::class);
    });

});
