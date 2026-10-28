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

        $create = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Default ' . uniqid(),
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        trackCreated('category', (int) $create['json']['id']);

        $parent = apiGet('/api/rest/v2/categories/' . (int) $create['json']['parentId'], $token);
        expect($create['json']['attributeSetId'])->not->toBeNull()
            ->and($create['json']['attributeSetId'])->toBe($parent['json']['attributeSetId']);
    });

    it('creates a category in a set and round-trips a user-defined attribute', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $setId = (int) $GLOBALS['_cat_set_test_set_id'];

        $create = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Set Brand ' . uniqid(),
            'attributeSetId' => $setId,
            'customAttributesWrite' => [CAT_SET_TEST_CODE => 'Made in Italy'],
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
            'customAttributesWrite' => [CAT_SET_TEST_CODE => 'Internal note'],
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
                'customAttributesWrite' => $customAttributes,
            ], fn($value) => $value !== []), $token);
            if (in_array($response['status'], [200, 201], true)) {
                trackCreated('category', (int) $response['json']['id']);
            }
            return $response;
        };
        $fields = fn(array $response): array => array_column($response['json']['details']['errors'] ?? [], 'field');
        $field = lcfirst(str_replace('_', '', ucwords($code, '_')));

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

});
