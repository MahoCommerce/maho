<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 product attributes: create, update, delete and options.
 *
 * @group write
 */

const PATTR_PATH = '/api/rest/v2/product-attributes';

afterAll(function (): void {
    foreach (pattrIds() as $attributeId) {
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
        if ($attribute->getId()) {
            $attribute->delete();
        }
    }
    cleanupTestData();
});

function &pattrIds(): array
{
    static $ids = [];
    return $ids;
}

function pattrCode(): string
{
    return 'pest_' . strtolower(substr(uniqid(), -8));
}

function pattrCreate(
    array $fields = [],
    #[\SensitiveParameter]
    ?string $token = null,
): array {
    $response = apiPost(PATTR_PATH, $fields + [
        'attributeCode' => pattrCode(),
        'frontendLabel' => 'Pest attribute',
    ], $token ?? adminToken());
    if (isset($response['json']['id'])) {
        pattrIds()[] = (int) $response['json']['id'];
    }
    return $response;
}

function pattrFields(array $response): array
{
    return array_column($response['json']['details']['errors'] ?? [], 'field');
}

function pattrSystemAttributeId(): int
{
    return (int) Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name')->getId();
}

describe('Product attribute access', function (): void {

    it('denies writes without authentication', function (): void {
        expect(apiPost(PATTR_PATH, ['attributeCode' => pattrCode(), 'frontendLabel' => 'x'])['status'])->toBe(401);
        expect(apiDelete(PATTR_PATH . '/' . pattrSystemAttributeId())['status'])->toBe(401);
    });

    it('denies writes to a token without the permission', function (): void {
        $readToken = serviceToken(['product-attributes/read']);
        expect(apiPost(PATTR_PATH, ['attributeCode' => pattrCode(), 'frontendLabel' => 'x'], $readToken)['status'])->toBeForbidden();
        expect(apiPut(PATTR_PATH . '/' . pattrSystemAttributeId(), ['note' => 'x'], $readToken)['status'])->toBeForbidden();
        expect(apiDelete(PATTR_PATH . '/' . pattrSystemAttributeId(), $readToken)['status'])->toBeForbidden();
        expect(apiPost(PATTR_PATH . '/' . pattrSystemAttributeId() . '/options', ['label' => 'x'], $readToken)['status'])->toBeForbidden();
    });

    it('denies an admin whose role does not allow the attributes', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_attr_acl_deny');
        expect(apiPost(PATTR_PATH, ['attributeCode' => pattrCode(), 'frontendLabel' => 'x'], $token)['status'])->toBe(403);
    });
});

describe('Product attribute lifecycle', function (): void {

    it('creates, reads, updates and deletes a text attribute', function (): void {
        $token = serviceToken(['product-attributes/write', 'product-attributes/delete', 'product-attributes/read']);
        $code = pattrCode();
        $create = pattrCreate([
            'attributeCode' => $code,
            'frontendLabel' => 'Pest text',
            'frontendInput' => 'text',
            'scope' => 'website',
            'isRequired' => false,
            'isSearchable' => true,
            'isFilterable' => 0,
            'isComparable' => true,
            'isVisibleOnFront' => true,
            'usedInProductListing' => true,
            'applyTo' => ['simple', 'virtual'],
            'frontendClass' => 'validate-number',
            'note' => 'A note',
            'position' => 3,
            'defaultValue' => '42',
        ], $token);

        expect($create['status'])->toBe(201);
        $json = $create['json'];
        $id = (int) $json['id'];
        expect($json['attributeCode'])->toBe($code)
            ->and($json['frontendLabel'])->toBe('Pest text')
            ->and($json['frontendInput'])->toBe('text')
            ->and($json['backendType'])->toBe('varchar')
            ->and($json['isUserDefined'])->toBeTrue()
            ->and($json['scope'])->toBe('website')
            ->and($json['isGlobal'])->toBe(2)
            ->and($json['isSearchable'])->toBeTrue()
            ->and($json['isComparable'])->toBeTrue()
            ->and($json['isVisibleOnFront'])->toBeTrue()
            ->and($json['usedInProductListing'])->toBeTrue()
            ->and($json['applyTo'])->toBe(['simple', 'virtual'])
            ->and($json['frontendClass'])->toBe('validate-number')
            ->and($json['note'])->toBe('A note')
            ->and($json['position'])->toBe(3)
            ->and($json['defaultValue'])->toBe('42');

        $get = apiGet(PATTR_PATH . "/{$id}", $token);
        expect($get['status'])->toBe(200)
            ->and($get['json']['attributeCode'])->toBe($code);

        // Only the fields in the body change
        $update = apiPut(PATTR_PATH . "/{$id}", ['frontendLabel' => 'Pest text 2', 'isSearchable' => false, 'applyTo' => []], $token);
        expect($update['status'])->toBe(200)
            ->and($update['json']['frontendLabel'])->toBe('Pest text 2')
            ->and($update['json']['isSearchable'])->toBeFalse()
            ->and($update['json']['applyTo'])->toBe([])
            ->and($update['json']['isComparable'])->toBeTrue()
            ->and($update['json']['scope'])->toBe('website');

        // A client can send back what it read
        expect(apiPut(PATTR_PATH . "/{$id}", $update['json'], $token)['status'])->toBe(200);

        // The code and the input type are fixed after creation
        $fixed = apiPut(PATTR_PATH . "/{$id}", ['attributeCode' => 'other_code', 'frontendInput' => 'select'], $token);
        expect($fixed['status'])->toBe(400)
            ->and(pattrFields($fixed))->toBe(['attributeCode', 'frontendInput']);

        $denied = apiDelete(PATTR_PATH . "/{$id}", serviceToken(['product-attributes/write']));
        expect($denied['status'])->toBeForbidden();

        expect(apiDelete(PATTR_PATH . "/{$id}", $token)['status'])->toBe(204)
            ->and(apiGet(PATTR_PATH . "/{$id}", $token)['status'])->toBe(404);
    });

    it('derives the storage type from the input type', function (): void {
        $expected = ['textarea' => 'text', 'date' => 'datetime', 'boolean' => 'int', 'select' => 'int', 'multiselect' => 'text', 'price' => 'decimal'];
        foreach ($expected as $input => $backendType) {
            $create = pattrCreate(['frontendInput' => $input]);
            expect($create['status'])->toBe(201, "frontendInput {$input}")
                ->and($create['json']['backendType'])->toBe($backendType, "frontendInput {$input}");
        }
    });

    it('lists every wrong field in one answer', function (): void {
        $missing = apiPost(PATTR_PATH, [], adminToken());
        expect($missing['status'])->toBe(400)
            ->and($missing['json']['error'])->toBe('validation_error')
            ->and(pattrFields($missing))->toBe(['attributeCode', 'frontendLabel']);

        $wrong = pattrCreate([
            'attributeCode' => 'Bad-Code',
            'frontendInput' => 'wysiwyg',
            'isSearchable' => 'yes',
            'isFilterable' => 7,
            'scope' => 'planet',
            'applyTo' => ['car'],
            'frontendClass' => 'validate-nothing',
            'unknownField' => 1,
        ]);
        expect($wrong['status'])->toBe(400)
            ->and(pattrFields($wrong))->toEqualCanonicalizing([
                'unknownField', 'attributeCode', 'frontendInput', 'isSearchable', 'isFilterable', 'scope', 'applyTo', 'frontendClass',
            ]);
    });

    it('refuses a reserved code, a used code and a code of more than 30 characters', function (): void {
        expect(pattrFields(pattrCreate(['attributeCode' => 'sku'])))->toBe(['attributeCode']);
        expect(pattrFields(pattrCreate(['attributeCode' => 'name'])))->toBe(['attributeCode']);
        expect(pattrFields(pattrCreate(['attributeCode' => str_repeat('a', 31)])))->toBe(['attributeCode']);
    });

    it('refuses to delete a system attribute', function (): void {
        $response = apiDelete(PATTR_PATH . '/' . pattrSystemAttributeId(), adminToken());
        expect($response['status'])->toBe(422);
        expect(Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name')->getId())->not->toBeNull();
    });

    it('accepts the storefront flags of a system attribute but not its unique flag', function (): void {
        $id = pattrSystemAttributeId();
        $current = apiGet(PATTR_PATH . "/{$id}", adminToken())['json'];

        $update = apiPut(PATTR_PATH . "/{$id}", ['isComparable' => $current['isComparable']], adminToken());
        expect($update['status'])->toBe(200);

        $locked = apiPut(PATTR_PATH . "/{$id}", ['isUnique' => !$current['isUnique']], adminToken());
        expect($locked['status'])->toBe(400)
            ->and(pattrFields($locked))->toBe(['isUnique']);
    });
});

describe('Product attribute options', function (): void {

    it('adds, updates and deletes the options of a select attribute', function (): void {
        $token = adminToken();
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $storeId = (int) Mage::app()->getStore('default')->getId();
        $create = pattrCreate(['frontendInput' => 'select', 'frontendLabel' => 'Pest color']);
        expect($create['status'])->toBe(201);
        $id = (int) $create['json']['id'];
        expect($create['json']['options'])->toBe([]);

        $red = apiPost(PATTR_PATH . "/{$id}/options", ['label' => 'Red', 'sortOrder' => 2], $token);
        expect($red['status'])->toBe(200)
            ->and($red['json']['options'])->toHaveCount(1)
            ->and($red['json']['options'][0]['label'])->toBe('Red');
        $redId = (int) $red['json']['options'][0]['value'];

        $blue = apiPost(PATTR_PATH . "/{$id}/options", ['label' => 'Blue', 'sortOrder' => 1, 'isDefault' => true], $token);
        expect($blue['status'])->toBe(200)
            ->and(array_column($blue['json']['options'], 'label'))->toBe(['Blue', 'Red'])
            ->and($blue['json']['defaultValue'])->toBe((string) $blue['json']['options'][0]['value']);
        $blueId = (int) $blue['json']['options'][0]['value'];

        // Only the fields in the body change, and the default moves to the other option.
        // The option labels of a response are those of the store of the request, so the admin label is checked in the table.
        $update = apiPut(PATTR_PATH . "/{$id}/options/{$redId}", ['label' => 'Dark red', 'isDefault' => true, 'storeLabels' => ['default' => 'Dunkelrot']], $token);
        expect($update['status'])->toBe(200)
            ->and(array_column($update['json']['options'], 'value'))->toBe([(string) $blueId, (string) $redId])
            ->and($update['json']['defaultValue'])->toBe((string) $redId);
        $labels = $read->fetchPairs('SELECT store_id, value FROM eav_attribute_option_value WHERE option_id = ?', [$redId]);
        expect($labels[0] ?? null)->toBe('Dark red')
            ->and($labels[$storeId] ?? null)->toBe('Dunkelrot');

        // An empty store label removes it, and the admin label stays
        $clear = apiPut(PATTR_PATH . "/{$id}/options/{$redId}", ['storeLabels' => ['default' => '']], $token);
        expect($clear['status'])->toBe(200);
        $labels = $read->fetchPairs('SELECT store_id, value FROM eav_attribute_option_value WHERE option_id = ?', [$redId]);
        expect($labels)->toBe([0 => 'Dark red']);

        $wrong = apiPut(PATTR_PATH . "/{$id}/options/{$redId}", ['label' => '', 'storeLabels' => ['no_such_store' => 'x'], 'other' => 1], $token);
        expect($wrong['status'])->toBe(400)
            ->and(pattrFields($wrong))->toEqualCanonicalizing(['other', 'label', 'storeLabels.no_such_store']);

        expect(apiPut(PATTR_PATH . "/{$id}/options/999999999", ['label' => 'x'], $token)['status'])->toBe(404);

        expect(apiDelete(PATTR_PATH . "/{$id}/options/{$redId}", $token)['status'])->toBe(204);
        $after = apiGet(PATTR_PATH . "/{$id}", $token)['json'];
        expect(array_column($after['options'], 'value'))->toBe([(string) $blueId])
            ->and($after['defaultValue'])->toBeEmpty();
    });

    it('refuses options on an attribute without an option list', function (): void {
        $create = pattrCreate(['frontendInput' => 'text']);
        $response = apiPost(PATTR_PATH . "/{$create['json']['id']}/options", ['label' => 'x'], adminToken());
        expect($response['status'])->toBe(422);
    });
});
