<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 catalog price rules: fields, conditions, lists, apply and access.
 *
 * @group write
 */

const CATR_PATH = '/api/rest/v2/catalog-price-rules';

afterAll(function (): void {
    foreach (catrRuleIds() as $ruleId) {
        $rule = Mage::getModel('catalogrule/rule')->load($ruleId);
        if ($rule->getId()) {
            $rule->delete();
        }
    }
    cleanupTestData();
});

function &catrRuleIds(): array
{
    static $ids = [];
    return $ids;
}

function catrCreate(
    array $fields = [],
    #[\SensitiveParameter]
    ?string $token = null,
): array {
    $response = apiPost(CATR_PATH, $fields + [
        'name' => 'Pest catalog rule ' . substr(uniqid(), -6),
        'websiteIds' => [1],
        'customerGroupIds' => [0, 1],
    ], $token ?? adminToken());
    if (isset($response['json']['id'])) {
        catrRuleIds()[] = (int) $response['json']['id'];
    }
    return $response;
}

function catrFields(array $response): array
{
    return array_column($response['json']['details']['errors'] ?? [], 'field');
}

function catrMembers(array $response): array
{
    return $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];
}

describe('Catalog price rule access', function (): void {

    it('denies every operation without authentication', function (): void {
        expect(apiGet(CATR_PATH)['status'])->toBe(401);
        expect(apiPost(CATR_PATH, ['name' => 'x'])['status'])->toBe(401);
        expect(apiPost(CATR_PATH . '/apply', [])['status'])->toBe(401);
    });

    it('denies a token without the permission', function (): void {
        $readToken = serviceToken(['catalog-price-rules/read']);
        expect(apiGet(CATR_PATH, $readToken)['status'])->toBe(200);
        expect(apiPost(CATR_PATH, ['name' => 'x'], $readToken)['status'])->toBeForbidden();
        expect(apiPost(CATR_PATH . '/apply', [], $readToken)['status'])->toBeForbidden();
        expect(apiGet(CATR_PATH, serviceToken(['cart-price-rules/read']))['status'])->toBeForbidden();
    });

    it('denies an admin whose role does not allow the catalog rules', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_catr_acl_deny');
        expect(apiGet(CATR_PATH, $token)['status'])->toBe(403);
    });
});

describe('Catalog price rule fields', function (): void {

    it('creates, reads, updates and deletes a rule', function (): void {
        $token = adminToken();
        $create = catrCreate([
            'name' => 'Pest full catalog rule',
            'description' => 'All fields',
            'isActive' => true,
            'websiteIds' => [1],
            'customerGroupIds' => [1],
            'fromDate' => '2030-01-01',
            'toDate' => '2030-12-31',
            'sortOrder' => 4,
            'simpleAction' => 'by_fixed',
            'discountAmount' => 150,
            'subIsEnable' => true,
            'subSimpleAction' => 'to_percent',
            'subDiscountAmount' => 80,
            'stopRulesProcessing' => true,
        ], $token);

        expect($create['status'])->toBe(201);
        $id = (int) $create['json']['id'];
        $get = apiGet(CATR_PATH . "/{$id}", $token);
        expect($get['status'])->toBe(200);
        $json = $get['json'];
        expect($json['name'])->toBe('Pest full catalog rule')
            ->and($json['description'])->toBe('All fields')
            ->and($json['isActive'])->toBeTrue()
            ->and($json['websiteIds'])->toBe([1])
            ->and($json['customerGroupIds'])->toBe([1])
            ->and($json['fromDate'])->toBe('2030-01-01')
            ->and($json['toDate'])->toBe('2030-12-31')
            ->and($json['sortOrder'])->toBe(4)
            ->and($json['simpleAction'])->toBe('by_fixed')
            ->and((float) $json['discountAmount'])->toBe(150.0)
            ->and($json['subIsEnable'])->toBeTrue()
            ->and($json['subSimpleAction'])->toBe('to_percent')
            ->and((float) $json['subDiscountAmount'])->toBe(80.0)
            ->and($json['stopRulesProcessing'])->toBeTrue()
            ->and($json['conditions']['type'])->toBe('catalogrule/rule_condition_combine');

        // Only the fields in the body change
        $update = apiPut(CATR_PATH . "/{$id}", ['name' => 'Pest full catalog rule 2', 'subSimpleAction' => null], $token);
        expect($update['status'])->toBe(200)
            ->and($update['json']['name'])->toBe('Pest full catalog rule 2')
            ->and($update['json']['subSimpleAction'] ?? null)->toBeNull()
            ->and($update['json']['description'])->toBe('All fields')
            ->and($update['json']['customerGroupIds'])->toBe([1]);

        // A client can send back what it read
        expect(apiPut(CATR_PATH . "/{$id}", $update['json'], $token)['status'])->toBe(200);

        expect(apiDelete(CATR_PATH . "/{$id}", serviceToken(['catalog-price-rules/write']))['status'])->toBeForbidden();
        expect(apiDelete(CATR_PATH . "/{$id}", $token)['status'])->toBe(204)
            ->and(apiGet(CATR_PATH . "/{$id}", $token)['status'])->toBe(404);
    });

    it('is inactive by default and lists every wrong field in one answer', function (): void {
        $create = catrCreate();
        expect($create['status'])->toBe(201)
            ->and($create['json']['isActive'])->toBeFalse()
            ->and($create['json']['simpleAction'])->toBe('by_percent');

        $missing = apiPost(CATR_PATH, ['description' => 'x'], adminToken());
        expect($missing['status'])->toBe(422)
            ->and($missing['json']['error'])->toBe('unprocessable_entity')
            ->and(catrFields($missing))->toBe(['name', 'websiteIds', 'customerGroupIds']);

        $wrong = catrCreate([
            'isActive' => 'yes',
            'sortOrder' => -1,
            'fromDate' => '31/12/2030',
            'simpleAction' => 'cart_fixed',
            'discountAmount' => -5,
            'customerGroupIds' => [999999],
        ]);
        expect($wrong['status'])->toBe(422)
            ->and(catrFields($wrong))->toEqualCanonicalizing(['isActive', 'sortOrder', 'customerGroupIds', 'fromDate', 'simpleAction', 'discountAmount']);

        $unknown = catrCreate(['unknownField' => 1, 'isActive' => 'yes']);
        expect($unknown['status'])->toBe(400)
            ->and(catrFields($unknown))->toBe(['unknownField']);

        $percent = catrCreate(['simpleAction' => 'by_percent', 'discountAmount' => 101]);
        expect($percent['status'])->toBe(422)
            ->and(catrFields($percent))->toBe(['discountAmount']);
    });

    it('stores a conditions tree and resets it with null', function (): void {
        $token = adminToken();
        // The attribute set is always a condition attribute; a product attribute needs isUsedForPriceRules
        $setId = (string) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getDefaultAttributeSetId();
        $create = catrCreate([
            'conditions' => [
                'type' => 'catalogrule/rule_condition_combine',
                'aggregator' => 'all',
                'value' => true,
                'conditions' => [
                    ['type' => 'catalogrule/rule_condition_product', 'attribute' => 'attribute_set_id', 'operator' => '==', 'value' => $setId],
                ],
            ],
        ], $token);
        expect($create['status'])->toBe(201);
        $id = (int) $create['json']['id'];
        $leaf = $create['json']['conditions']['conditions'][0] ?? [];
        expect($leaf['type'] ?? null)->toBe('catalogrule/rule_condition_product')
            ->and($leaf['attribute'] ?? null)->toBe('attribute_set_id')
            ->and($leaf['operator'] ?? null)->toBe('==')
            ->and($leaf['value'] ?? null)->toBe($setId)
            ->and($leaf['label'] ?? '')->not->toBe('');

        $stored = Mage::getModel('catalogrule/rule')->load($id);
        expect($stored->getConditions()->asArray()['conditions'][0]['attribute'] ?? null)->toBe('attribute_set_id');

        $unknownAttribute = apiPut(CATR_PATH . "/{$id}", ['conditions' => [
            'type' => 'catalogrule/rule_condition_combine',
            'aggregator' => 'all',
            'value' => true,
            'conditions' => [['type' => 'catalogrule/rule_condition_product', 'attribute' => 'no_such_attribute', 'operator' => '==', 'value' => '1']],
        ]], $token);
        expect($unknownAttribute['status'])->toBe(422)
            ->and(catrFields($unknownAttribute))->toBe(['conditions.conditions[0].attribute']);

        $wrong = apiPut(CATR_PATH . "/{$id}", ['conditions' => ['type' => 'salesrule/rule_condition_combine']], $token);
        expect($wrong['status'])->toBe(422)
            ->and(catrFields($wrong)[0] ?? '')->toStartWith('conditions');

        $reset = apiPut(CATR_PATH . "/{$id}", ['conditions' => null], $token);
        expect($reset['status'])->toBe(200)
            ->and($reset['json']['conditions']['conditions'] ?? null)->toBe([]);
    });

    it('lists rules with filters and leaves the tree out', function (): void {
        $token = adminToken();
        $name = 'Pest listed catalog rule ' . substr(uniqid(), -6);
        $create = catrCreate(['name' => $name, 'isActive' => true, 'websiteIds' => [1], 'customerGroupIds' => [1]], $token);
        expect($create['status'])->toBe(201);

        $list = apiGet(CATR_PATH . '?search=' . rawurlencode($name) . '&isActive=true&websiteId=1&customerGroupId=1', $token);
        expect($list['status'])->toBe(200);
        $members = catrMembers($list);
        expect(array_column($members, 'id'))->toBe([(int) $create['json']['id']])
            ->and($members[0])->not->toHaveKey('conditions');

        $none = apiGet(CATR_PATH . '?search=' . rawurlencode($name) . '&isActive=false', $token);
        expect(catrMembers($none))->toBe([]);

        expect(apiGet(CATR_PATH . '?sort=price', $token)['status'])->toBe(400);
    });

    it('applies the rules and clears the dirty flag', function (): void {
        $token = serviceToken(['catalog-price-rules/write']);
        $create = catrCreate(['isActive' => true], $token);
        expect($create['status'])->toBe(201);
        expect((int) Mage::getModel('catalogrule/flag')->loadSelf()->getState())->toBe(1);

        $apply = apiPost(CATR_PATH . '/apply', [], $token);
        expect($apply['status'])->toBe(200)
            ->and($apply['json'])->toBe(['success' => true]);
        expect((int) Mage::getModel('catalogrule/flag')->loadSelf()->getState())->toBe(0);
    });
});
