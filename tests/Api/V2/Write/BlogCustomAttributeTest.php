<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 attributeSetId and customAttributes of blog posts and blog categories
 *
 * @group write
 */

const BLOG_ATTR_POST_CODE = 'pest_api_reading_time';
const BLOG_ATTR_CATEGORY_CODE = 'pest_api_mood';
const BLOG_ATTR_CATEGORY_PREFIX = 'Pest API Mood ';
const BLOG_ATTR_LOOSE_CODE = 'pest_api_loose';

/**
 * The set and group fields that put an attribute in the first group of the default set of $entityTypeCode.
 *
 * @return array{attribute_set_id: int, attribute_group_id: int}
 */
function blogAttrTestDefaultGroup(string $entityTypeCode): array
{
    $setId = (int) Mage::getSingleton('eav/config')->getEntityType($entityTypeCode)->getDefaultAttributeSetId();
    return [
        'attribute_set_id' => $setId,
        'attribute_group_id' => (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($setId)
            ->getFirstItem()
            ->getId(),
    ];
}

beforeAll(function (): void {
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
    $eavConfig = Mage::getSingleton('eav/config');

    Mage::getModel('blog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType(Maho_Blog_Model_Post::ENTITY)->getId(),
        'attribute_code' => BLOG_ATTR_POST_CODE,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Reading Time',
        'is_user_defined' => 1,
        'is_global' => 1,
    ] + blogAttrTestDefaultGroup(Maho_Blog_Model_Post::ENTITY))->save();

    Mage::getModel('blog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType(Maho_Blog_Model_Post::ENTITY)->getId(),
        'attribute_code' => BLOG_ATTR_LOOSE_CODE,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'In No Set',
        'is_user_defined' => 1,
        'is_global' => 1,
    ])->save();

    Mage::getModel('blog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType(Maho_Blog_Model_Category::ENTITY)->getId(),
        'attribute_code' => BLOG_ATTR_CATEGORY_CODE,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Mood',
        'is_user_defined' => 1,
        'is_global' => 1,
        'option' => ['value' => ['o1' => ['Calm'], 'o2' => ['Bold']], 'order' => ['o1' => 1, 'o2' => 2]],
    ] + blogAttrTestDefaultGroup(Maho_Blog_Model_Category::ENTITY))->save();
    Mage::app()->cleanCache();
    $eavConfig->clear();

    $attribute = Mage::getModel('blog/resource_eav_attribute')->loadByCode(Maho_Blog_Model_Category::ENTITY, BLOG_ATTR_CATEGORY_CODE);
    foreach ($attribute->getSource()->getAllOptions(false) as $option) {
        if ($option['label'] === 'Calm') {
            $GLOBALS['_blog_attr_calm_id'] = (string) $option['value'];
        }
    }
});

afterAll(function (): void {
    cleanupTestData();
    try {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        foreach (Mage::getResourceModel('blog/category_collection')->addFieldToFilter('name', ['like' => BLOG_ATTR_CATEGORY_PREFIX . '%']) as $category) {
            $category->delete();
        }
        foreach ([[Maho_Blog_Model_Post::ENTITY, BLOG_ATTR_POST_CODE], [Maho_Blog_Model_Post::ENTITY, BLOG_ATTR_LOOSE_CODE], [Maho_Blog_Model_Category::ENTITY, BLOG_ATTR_CATEGORY_CODE]] as [$type, $code]) {
            $attribute = Mage::getModel('blog/resource_eav_attribute')->loadByCode($type, $code);
            if ($attribute->getId()) {
                $attribute->delete();
            }
        }
        Mage::app()->cleanCache();
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('Blog customAttributes (REST)', function (): void {

    it('writes a post attribute, reads it back, and gives the post the default set', function (): void {
        $token = serviceToken(['blog-posts/write', 'blog-posts/read']);
        $create = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Pest Custom Attribute Post',
            'urlKey' => 'pest-custom-attribute-' . uniqid(),
            'content' => '<p>Body</p>',
            'customAttributes' => [BLOG_ATTR_POST_CODE => '5 minutes'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $postId = (int) $create['json']['id'];
        trackCreated('blog_post', $postId);

        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $defaultSetId = (int) Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY)->getDefaultAttributeSetId();

        $read = apiGet("/api/rest/v2/blog-posts/{$postId}", $token);
        expect($read['json']['customAttributes'][BLOG_ATTR_POST_CODE] ?? null)->toBe('5 minutes')
            ->and($read['json']['attributeSetId'])->toBe($defaultSetId);

        $anonymous = apiGet("/api/rest/v2/blog-posts/{$postId}");
        expect($anonymous['json']['customAttributes'] ?? null)->toBeNull();
    });

    it('refuses a code that is not a custom attribute of posts, or that is not in the set of the post', function (): void {
        $response = apiPost('/api/rest/v2/blog-posts', [
            'title' => 'Pest Unknown Attribute Post',
            'urlKey' => 'pest-unknown-attribute-' . uniqid(),
            'content' => '<p>Body</p>',
            'customAttributes' => ['title' => 'x', BLOG_ATTR_LOOSE_CODE => 'y'],
        ], serviceToken(['blog-posts/write']));
        if (in_array($response['status'], [200, 201], true)) {
            trackCreated('blog_post', (int) $response['json']['id']);
        }

        $fields = array_column($response['json']['details']['errors'] ?? [], 'field');
        expect($response['status'])->toBe(422)
            ->and($fields)->toContain('customAttributes.title')
            ->and($fields)->toContain('customAttributes.' . BLOG_ATTR_LOOSE_CODE);
    });

    it('writes a category dropdown with an option id and refuses another value', function (): void {
        $token = serviceToken(['blog-categories/write', 'blog-categories/read']);
        $calm = $GLOBALS['_blog_attr_calm_id'];

        $create = apiPost('/api/rest/v2/blog-categories', [
            'name' => BLOG_ATTR_CATEGORY_PREFIX . uniqid(),
            'customAttributes' => [BLOG_ATTR_CATEGORY_CODE => $calm],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = (int) $create['json']['id'];

        $read = apiGet("/api/rest/v2/blog-categories/{$categoryId}", $token);
        expect((string) ($read['json']['customAttributes'][BLOG_ATTR_CATEGORY_CODE] ?? ''))->toBe($calm);

        $bad = apiPost('/api/rest/v2/blog-categories', [
            'name' => BLOG_ATTR_CATEGORY_PREFIX . uniqid(),
            'customAttributes' => [BLOG_ATTR_CATEGORY_CODE => '999999'],
        ], $token);
        expect($bad['status'])->toBe(422);
    });

    it('refuses a set of another entity type', function (): void {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $productSetId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getDefaultAttributeSetId();

        $response = apiPost('/api/rest/v2/blog-categories', [
            'name' => BLOG_ATTR_CATEGORY_PREFIX . uniqid(),
            'attributeSetId' => $productSetId,
        ], serviceToken(['blog-categories/write']));

        expect($response['status'])->toBeIn([400, 422]);
    });

});
