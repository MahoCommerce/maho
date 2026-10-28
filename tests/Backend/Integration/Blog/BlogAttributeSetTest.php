<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A user-defined text attribute of $entityTypeCode in the first group of its default set.
 */
function blogSetTestAttribute(string $entityTypeCode, string $code, array $extra = []): Maho_Blog_Model_Resource_Eav_Attribute
{
    $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
    $setId = (int) $type->getDefaultAttributeSetId();
    $attribute = Mage::getModel('blog/resource_eav_attribute')->setData($extra + [
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => $code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => $code,
        'is_user_defined' => 1,
        'is_global' => 1,
        'attribute_set_id' => $setId,
        'attribute_group_id' => (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->getFirstItem()
            ->getId(),
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    return $attribute;
}

beforeEach(function () {
    if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
        $this->markTestSkipped('Maho_Blog is disabled');
    }
    $this->suffix = substr(uniqid(), -6);
    $this->records = [];
});

afterEach(function () {
    foreach (array_reverse($this->records) as $record) {
        if ($record->getId()) {
            $record->delete();
        }
    }
    Mage::getSingleton('eav/config')->clear();
});

it('gives each blog entity type a default set', function () {
    foreach ([Maho_Blog_Model_Post::ENTITY, Maho_Blog_Model_Category::ENTITY] as $code) {
        $type = Mage::getSingleton('eav/config')->getEntityType($code);
        expect((int) $type->getDefaultAttributeSetId())->toBeGreaterThan(0)
            ->and($type->getAttributeModel())->toBe('blog/resource_eav_attribute')
            ->and($type->getAdditionalAttributeTable())->toBe('blog/eav_attribute');
    }
});

it('saves the frontend flags of a blog attribute in the additional table', function () {
    $attribute = $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Category::ENTITY, 'pest_flag_' . $this->suffix, [
        'is_visible_on_front' => 1,
        'position' => 7,
    ]);

    $loaded = Mage::getModel('blog/resource_eav_attribute')->load($attribute->getId());
    expect((int) $loaded->getData('is_visible_on_front'))->toBe(1)
        ->and((int) $loaded->getData('position'))->toBe(7);
});

it('stores a user-defined value of a post and of a category, in the default set', function () {
    $postCode = 'pest_time_' . $this->suffix;
    $categoryCode = 'pest_tag_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $postCode);
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Category::ENTITY, $categoryCode);

    $category = Mage::getModel('blog/category')->setData([
        'name' => 'Pest Category ' . $this->suffix,
        'is_active' => 1,
        'stores' => [0],
        $categoryCode => 'First',
    ]);
    $category->save();
    $this->records[] = $category;
    $category->setData($categoryCode, 'Second')->save();

    $post = Mage::getModel('blog/post')->setData([
        'title' => 'Pest Post ' . $this->suffix,
        'url_key' => 'pest-post-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
        $postCode => '5 minutes',
    ]);
    $post->save();
    $this->records[] = $post;

    $category = Mage::getModel('blog/category')->load($category->getId());
    $post = Mage::getModel('blog/post')->load($post->getId());
    expect($category->getData($categoryCode))->toBe('Second')
        ->and($category->getAttributeSetId())->toBe((int) Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Category::ENTITY)->getDefaultAttributeSetId())
        ->and($post->getData($postCode))->toBe('5 minutes')
        ->and(array_keys($post->getAttributes()))->toContain($postCode);
});

it('refuses a set of another entity type', function () {
    $productSetId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getDefaultAttributeSetId();
    $category = Mage::getModel('blog/category')->setData([
        'name' => 'Pest Wrong Set ' . $this->suffix,
        'is_active' => 1,
        'stores' => [0],
        'attribute_set_id' => $productSetId,
    ]);

    expect(fn() => $category->save())->toThrow(Mage_Core_Exception::class);
    $this->records[] = $category;
});

it('lists a value that is visible on the storefront', function () {
    $code = 'pest_front_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $code, [
        'is_visible_on_front' => 1,
        'frontend_label' => 'Reading Time',
    ]);
    $post = Mage::getModel('blog/post')->setData([
        'title' => 'Pest Front ' . $this->suffix,
        'url_key' => 'pest-front-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
        $code => '<b>5</b> minutes',
    ]);
    $post->save();
    $this->records[] = $post;

    $block = Mage::app()->getLayout()->createBlock('eav/frontend_attributes')
        ->setData('entity', Mage::getModel('blog/post')->load($post->getId()));
    $rows = $block->getAttributeValues();

    expect(array_column($rows, 'code'))->toContain($code)
        ->and($block->getValueHtml($rows[array_search($code, array_column($rows, 'code'), true)]))->toBe('&lt;b&gt;5&lt;/b&gt; minutes');
});

it('lists a Yes/No value of 0 as No and leaves out an empty one', function () {
    $code = 'pest_flag_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $code, [
        'backend_type' => 'int',
        'frontend_input' => 'boolean',
        'source_model' => 'eav/entity_attribute_source_boolean',
        'is_visible_on_front' => 1,
    ]);
    $codes = function (?int $value) use ($code): array {
        $post = Mage::getModel('blog/post')->setData(['entity_id' => 1, $code => $value]);
        $rows = Mage::app()->getLayout()->createBlock('eav/frontend_attributes')->setData('entity', $post)->getAttributeValues();
        return array_column($rows, 'value', 'code');
    };

    expect($codes(0))->toHaveKey($code)
        ->and($codes(0)[$code])->toBe('No')
        ->and($codes(null))->not->toHaveKey($code);
});

it('adds a tab for each group that holds a user-defined attribute to the post edit page', function () {
    $code = 'pest_tab_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $code);
    $post = Mage::getModel('blog/post');
    Mage::register('blog_post', $post);

    try {
        $tabs = Mage::app()->getLayout()->createBlock('blog/adminhtml_post_edit_tabs');
        $method = new ReflectionMethod($tabs, 'addAttributeTabs');
        $method->invoke($tabs);
        $attributeTabs = array_filter($tabs->getTabsIds(), fn(string $id): bool => str_starts_with($id, 'attribute_group_'));
    } finally {
        Mage::unregister('blog_post');
    }

    expect($attributeTabs)->toHaveCount(1);
});
