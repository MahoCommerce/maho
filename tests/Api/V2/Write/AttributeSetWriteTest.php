<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 attribute sets: create, rename, delete, groups and attribute assignment.
 *
 * @group write
 */

const ASET_PATH = '/api/rest/v2/attribute-sets';

afterAll(function (): void {
    foreach (asetIds() as $setId) {
        $set = Mage::getModel('eav/entity_attribute_set')->load($setId);
        if ($set->getId()) {
            $set->delete();
        }
    }
    foreach (asetAttributeIds() as $attributeId) {
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
        if ($attribute->getId()) {
            $attribute->delete();
        }
    }
    cleanupTestData();
});

function &asetIds(): array
{
    static $ids = [];
    return $ids;
}

function &asetAttributeIds(): array
{
    static $ids = [];
    return $ids;
}

function asetDefaultId(): int
{
    return (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getDefaultAttributeSetId();
}

function asetCreate(
    array $fields = [],
    #[\SensitiveParameter]
    ?string $token = null,
): array {
    $response = apiPost(ASET_PATH, $fields + ['name' => 'Pest set ' . substr(uniqid(), -6)], $token ?? adminToken());
    if (isset($response['json']['id'])) {
        asetIds()[] = (int) $response['json']['id'];
    }
    return $response;
}

function asetCreateAttribute(): array
{
    $response = apiPost('/api/rest/v2/product-attributes', [
        'attributeCode' => 'pest_set_' . strtolower(substr(uniqid(), -6)),
        'frontendLabel' => 'Pest set attribute',
    ], adminToken());
    expect($response['status'])->toBe(201);
    asetAttributeIds()[] = (int) $response['json']['id'];
    return $response['json'];
}

function asetFields(array $response): array
{
    return array_column($response['json']['details']['errors'] ?? [], 'field');
}

describe('Attribute set access', function (): void {

    it('denies writes without authentication', function (): void {
        expect(apiPost(ASET_PATH, ['name' => 'x'])['status'])->toBe(401);
        expect(apiDelete(ASET_PATH . '/' . asetDefaultId())['status'])->toBe(401);
    });

    it('denies writes to a token without the permission', function (): void {
        $readToken = serviceToken(['attribute-sets/read']);
        expect(apiPost(ASET_PATH, ['name' => 'x'], $readToken)['status'])->toBeForbidden();
        expect(apiPut(ASET_PATH . '/' . asetDefaultId(), ['name' => 'x'], $readToken)['status'])->toBeForbidden();
        expect(apiDelete(ASET_PATH . '/' . asetDefaultId(), $readToken)['status'])->toBeForbidden();
        expect(apiPost(ASET_PATH . '/' . asetDefaultId() . '/groups', ['name' => 'x'], $readToken)['status'])->toBeForbidden();
    });

    it('denies an admin whose role does not allow the attribute sets', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_set_acl_deny');
        expect(apiPost(ASET_PATH, ['name' => 'x'], $token)['status'])->toBe(403);
    });
});

describe('Attribute set lifecycle', function (): void {

    it('creates a set from the default skeleton, renames it and deletes it', function (): void {
        $token = serviceToken(['attribute-sets/write', 'attribute-sets/delete', 'attribute-sets/read']);
        $default = apiGet(ASET_PATH . '/' . asetDefaultId(), $token)['json'];

        $create = asetCreate(['name' => 'Pest skeleton copy ' . substr(uniqid(), -6), 'skeletonId' => asetDefaultId()], $token);
        expect($create['status'])->toBe(201);
        $id = (int) $create['json']['id'];
        expect($create['json']['attributeCodes'])->toEqualCanonicalizing($default['attributeCodes'])
            ->and(array_column($create['json']['groups'], 'name'))->toBe(array_column($default['groups'], 'name'))
            ->and($create['json']['groups'][0])->toHaveKeys(['id', 'name', 'sortOrder', 'attributes']);

        $duplicate = apiPost(ASET_PATH, ['name' => $create['json']['attributeSetName']], $token);
        expect($duplicate['status'])->toBe(422)
            ->and(asetFields($duplicate))->toBe(['name']);

        $rename = apiPut(ASET_PATH . "/{$id}", ['name' => 'Pest renamed ' . substr(uniqid(), -6)], $token);
        expect($rename['status'])->toBe(200)
            ->and($rename['json']['attributeSetName'])->toStartWith('Pest renamed');

        expect(apiDelete(ASET_PATH . "/{$id}", serviceToken(['attribute-sets/write']))['status'])->toBeForbidden();
        expect(apiDelete(ASET_PATH . "/{$id}", $token)['status'])->toBe(204)
            ->and(apiGet(ASET_PATH . "/{$id}", $token)['status'])->toBe(404);
    });

    it('rejects a missing name and an unknown skeleton', function (): void {
        $missing = apiPost(ASET_PATH, [], adminToken());
        expect($missing['status'])->toBe(422)
            ->and(asetFields($missing))->toBe(['name']);

        $unknown = asetCreate(['skeletonId' => 999999999]);
        expect($unknown['status'])->toBe(422)
            ->and(asetFields($unknown))->toBe(['skeletonId']);
    });

    it('refuses to delete the default attribute set', function (): void {
        expect(apiDelete(ASET_PATH . '/' . asetDefaultId(), adminToken())['status'])->toBe(409);
        expect(apiGet(ASET_PATH . '/' . asetDefaultId(), adminToken())['status'])->toBe(200);
    });
});

describe('Attribute set groups and attributes', function (): void {

    it('adds a group, assigns an attribute to it by code and by ID, and removes the attribute', function (): void {
        $token = adminToken();
        $create = asetCreate();
        expect($create['status'])->toBe(201);
        $setId = (int) $create['json']['id'];
        $attribute = asetCreateAttribute();

        $group = apiPost(ASET_PATH . "/{$setId}/groups", ['name' => 'Pest group'], $token);
        expect($group['status'])->toBe(200);
        $groups = array_column($group['json']['groups'], null, 'name');
        expect($groups)->toHaveKey('Pest group');
        $groupId = (int) $groups['Pest group']['id'];

        $again = apiPost(ASET_PATH . "/{$setId}/groups", ['name' => 'Pest group'], $token);
        expect($again['status'])->toBe(422)
            ->and(asetFields($again))->toBe(['name']);

        $assign = apiPost(ASET_PATH . "/{$setId}/attributes", ['attributeCode' => $attribute['attributeCode'], 'groupName' => 'Pest group'], $token);
        expect($assign['status'])->toBe(200)
            ->and($assign['json']['attributeCodes'])->toContain($attribute['attributeCode']);
        $assigned = array_column($assign['json']['groups'], null, 'name')['Pest group']['attributes'];
        expect(array_column($assigned, 'code'))->toBe([$attribute['attributeCode']]);

        // A second assignment moves the attribute to another group
        $firstGroupId = (int) $create['json']['groups'][0]['id'];
        $move = apiPost(ASET_PATH . "/{$setId}/attributes", ['attributeId' => $attribute['id'], 'groupId' => $firstGroupId, 'sortOrder' => 99], $token);
        expect($move['status'])->toBe(200);
        $byName = array_column($move['json']['groups'], null, 'name');
        expect(array_column($byName['Pest group']['attributes'], 'code'))->toBe([])
            ->and(array_column($byName[$create['json']['groups'][0]['name']]['attributes'], 'code'))->toContain($attribute['attributeCode']);

        $wrong = apiPost(ASET_PATH . "/{$setId}/attributes", ['attributeCode' => 'no_such_attribute', 'groupId' => 999999999], $token);
        expect($wrong['status'])->toBe(422)
            ->and(asetFields($wrong))->toBe(['attributeCode', 'groupId']);

        expect(apiDelete(ASET_PATH . "/{$setId}/attributes/{$attribute['id']}", $token)['status'])->toBe(204);
        expect(apiGet(ASET_PATH . "/{$setId}", $token)['json']['attributeCodes'])->not->toContain($attribute['attributeCode']);
        expect(apiDelete(ASET_PATH . "/{$setId}/attributes/{$attribute['id']}", $token)['status'])->toBe(404);
    });

    it('refuses to remove a system attribute from a set', function (): void {
        $create = asetCreate();
        $nameId = (int) Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name')->getId();
        $response = apiDelete(ASET_PATH . "/{$create['json']['id']}/attributes/{$nameId}", adminToken());
        expect($response['status'])->toBe(409);
        expect(apiGet(ASET_PATH . "/{$create['json']['id']}", adminToken())['json']['attributeCodes'])->toContain('name');
    });
});
