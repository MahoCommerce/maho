<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 category attribute set and user-defined attribute tests
 *
 * @group write
 */

const CAT_SET_TEST_CODE = 'pest_api_story';

/**
 * A category set based on the default set, with one user-defined text attribute in its first group.
 */
function categorySetTestFixture(): int
{
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

    $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY);
    $set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $type->getId())
        ->setAttributeSetName('Pest API Brand ' . uniqid());
    $set->save();
    $set->initFromSkeleton((int) $type->getDefaultAttributeSetId())->save();

    $groupId = (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
        ->setAttributeSetFilter((int) $set->getId())
        ->setSortOrder()
        ->getFirstItem()
        ->getId();

    Mage::getModel('catalog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => CAT_SET_TEST_CODE,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest API Story',
        'is_user_defined' => 1,
        'is_global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'attribute_set_id' => (int) $set->getId(),
        'attribute_group_id' => $groupId,
    ])->save();
    Mage::app()->cleanCache();

    return (int) $set->getId();
}

beforeAll(function (): void {
    $GLOBALS['_cat_set_test_set_id'] = categorySetTestFixture();
});

afterAll(function (): void {
    cleanupTestData();
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
    $attribute = Mage::getModel('catalog/resource_eav_attribute')
        ->loadByCode(Mage_Catalog_Model_Category::ENTITY, CAT_SET_TEST_CODE);
    if ($attribute->getId()) {
        $attribute->delete();
    }
    Mage::getModel('eav/entity_attribute_set')->setId((int) $GLOBALS['_cat_set_test_set_id'])->delete();
    Mage::app()->cleanCache();
});

describe('Category attribute sets (REST)', function (): void {

    it('gives a new category the set of its parent', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $setId = (int) $GLOBALS['_cat_set_test_set_id'];

        // The parent is in the custom set, so the default set of the entity type cannot pass
        $parent = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Parent ' . uniqid(),
            'attributeSetId' => $setId,
        ], $token);
        expect($parent['status'])->toBeIn([200, 201]);
        trackCreated('category', (int) $parent['json']['id']);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Child ' . uniqid(),
            'parentId' => (int) $parent['json']['id'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        trackCreated('category', (int) $create['json']['id']);

        expect($create['json']['attributeSetId'])->toBe($setId);
    });

    it('creates a category in a set and round-trips a user-defined attribute', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $setId = (int) $GLOBALS['_cat_set_test_set_id'];

        $create = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Brand ' . uniqid(),
            'attributeSetId' => $setId,
            'customAttributes' => [CAT_SET_TEST_CODE => 'Made in Italy'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = (int) $create['json']['id'];
        trackCreated('category', $categoryId);

        $read = apiGet("/api/rest/v2/categories/{$categoryId}", $token);
        expect($read['status'])->toBe(200)
            ->and($read['json']['attributeSetId'])->toBe($setId)
            ->and($read['json']['customAttributes'][CAT_SET_TEST_CODE] ?? null)->toBe('Made in Italy');
    });

    it('rejects a set of another entity type', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $productSetId = (int) Mage::getSingleton('eav/config')
            ->getEntityType(Mage_Catalog_Model_Product::ENTITY)
            ->getDefaultAttributeSetId();

        $response = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Wrong ' . uniqid(),
            'attributeSetId' => $productSetId,
        ], $token);
        if (in_array($response['status'], [200, 201], true)) {
            trackCreated('category', (int) $response['json']['id']);
        }
        expect($response['status'])->toBeIn([400, 422]);
    });

    it('hides customAttributes from an anonymous reader', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $create = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Anonymous ' . uniqid(),
            'isActive' => true,
            'attributeSetId' => (int) $GLOBALS['_cat_set_test_set_id'],
            'customAttributes' => [CAT_SET_TEST_CODE => 'Internal note'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = (int) $create['json']['id'];
        trackCreated('category', $categoryId);

        $read = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($read['status'])->toBe(200)
            ->and($read['json'])->not->toHaveKey('customAttributes');
    });

    it('refuses a category that leaves a required attribute of its set empty or repeats a unique value', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY);
        $set = Mage::getModel('eav/entity_attribute_set')
            ->setEntityTypeId((int) $type->getId())
            ->setAttributeSetName('Pest API Required ' . uniqid());
        $set->save();
        $set->initFromSkeleton((int) $type->getDefaultAttributeSetId())->save();
        $code = 'pest_api_required_' . substr(uniqid(), -6);
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->setData([
            'entity_type_id' => (int) $type->getId(),
            'attribute_code' => $code,
            'backend_type' => 'varchar',
            'frontend_input' => 'text',
            'frontend_label' => 'Pest Required',
            'is_user_defined' => 1,
            'is_required' => 1,
            'is_unique' => 1,
            'is_global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'attribute_set_id' => (int) $set->getId(),
            'attribute_group_id' => (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
                ->setAttributeSetFilter((int) $set->getId())
                ->setSortOrder()
                ->getFirstItem()
                ->getId(),
        ]);
        $attribute->save();
        Mage::app()->cleanCache();
        Mage::getSingleton('eav/config')->clear();

        $create = function (array $customAttributes) use ($token, $set): array {
            $response = apiPost('/api/rest/v2/categories', array_filter([
                'name' => 'Pest Set Required ' . uniqid(),
                'attributeSetId' => (int) $set->getId(),
                'customAttributes' => $customAttributes,
            ], fn($value) => $value !== []), $token);
            if (in_array($response['status'], [200, 201], true)) {
                trackCreated('category', (int) $response['json']['id']);
            }
            return $response;
        };
        $fields = fn(array $response): array => array_column($response['json']['details']['errors'] ?? [], 'field');
        $field = "customAttributes.{$code}";

        try {
            $missing = $create([]);
            $blank = $create([$code => '']);
            $filled = $create([$code => 'Filled']);
            $duplicate = $create([$code => 'Filled']);

            expect($missing['status'])->toBe(422)
                ->and($fields($missing))->toContain($field)
                ->and($blank['status'])->toBe(422)
                ->and($fields($blank))->toContain($field)
                ->and($filled['status'])->toBeIn([200, 201])
                ->and($duplicate['status'])->toBe(422)
                ->and($fields($duplicate))->toContain($field);
        } finally {
            cleanupTestData();
            Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
            $attribute->delete();
            $set->delete();
            Mage::app()->cleanCache();
        }
    });

    it('writes a user-defined attribute through customAttributes and refuses one that is not in the set', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $setId = (int) $GLOBALS['_cat_set_test_set_id'];

        $create = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Custom ' . uniqid(),
            'attributeSetId' => $setId,
            'customAttributes' => [CAT_SET_TEST_CODE => 'Hand made'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        trackCreated('category', (int) $create['json']['id']);

        $read = apiGet('/api/rest/v2/categories/' . (int) $create['json']['id'], $token);
        expect($read['json']['customAttributes'][CAT_SET_TEST_CODE] ?? null)->toBe('Hand made');

        // The default set does not hold the attribute of the fixture
        $outside = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Outside ' . uniqid(),
            'customAttributes' => [CAT_SET_TEST_CODE => 'Lost'],
        ], $token);
        if (in_array($outside['status'], [200, 201], true)) {
            trackCreated('category', (int) $outside['json']['id']);
        }
        $unknown = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Unknown ' . uniqid(),
            'attributeSetId' => $setId,
            'customAttributes' => ['pest_no_such_code' => 'x'],
        ], $token);
        if (in_array($unknown['status'], [200, 201], true)) {
            trackCreated('category', (int) $unknown['json']['id']);
        }

        expect($outside['status'])->toBe(422)
            ->and($unknown['status'])->toBe(422);
    });

});

describe('Custom attributes (REST)', function (): void {

    it('lists the custom attributes of categories with the sets that hold them', function (): void {
        $token = serviceToken(['custom-attributes/read']);
        $setId = (int) $GLOBALS['_cat_set_test_set_id'];

        $list = apiGet('/api/rest/v2/custom-attributes?entityType=catalog_category', $token);
        expect($list['status'])->toBe(200);
        $items = $list['json']['member'] ?? $list['json']['hydra:member'] ?? $list['json'];
        $attribute = array_values(array_filter($items, fn(array $item): bool => ($item['code'] ?? '') === CAT_SET_TEST_CODE))[0] ?? null;

        expect($attribute)->not->toBeNull()
            ->and($attribute['entityType'])->toBe('catalog_category')
            ->and($attribute['input'])->toBe('text')
            ->and(array_column($attribute['attributeSets'], 'id'))->toBe([$setId])
            ->and(array_column($items, 'code'))->not->toContain('name');

        $item = apiGet('/api/rest/v2/custom-attributes/' . (int) $attribute['id'], $token);
        expect($item['status'])->toBe(200)
            ->and($item['json']['code'])->toBe(CAT_SET_TEST_CODE);
    });

    it('refuses a list without an entity type or with a type that has no custom attributes', function (): void {
        $token = serviceToken(['custom-attributes/read']);

        expect(apiGet('/api/rest/v2/custom-attributes', $token)['status'])->toBe(400)
            ->and(apiGet('/api/rest/v2/custom-attributes?entityType=catalog_product', $token)['status'])->toBe(400)
            ->and(apiGet('/api/rest/v2/custom-attributes?entityType=catalog_category')['status'])->toBeIn([401, 403]);
    });

    it('types customAttributes in the OpenAPI document with the attributes of each entity type', function (): void {
        $schemas = apiGet('/api/docs.json')['json']['components']['schemas'] ?? [];
        $typed = array_filter(
            $schemas,
            fn(array $schema, string $name): bool => str_starts_with($name, 'Category')
                && isset($schema['properties']['customAttributes']['properties'][CAT_SET_TEST_CODE]),
            ARRAY_FILTER_USE_BOTH,
        );
        $property = array_values($typed)[0]['properties']['customAttributes'] ?? [];

        expect($typed)->not->toBeEmpty()
            ->and($property['additionalProperties'] ?? null)->toBeFalse()
            ->and($property['properties'][CAT_SET_TEST_CODE]['type'] ?? null)->toBe(['string', 'null'])
            ->and($property['properties'])->not->toHaveKey('name');
    });

});
