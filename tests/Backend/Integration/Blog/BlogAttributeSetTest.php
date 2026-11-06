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
        'is_html_allowed_on_front' => 1,
    ]);

    $loaded = Mage::getModel('blog/resource_eav_attribute')->load($attribute->getId());
    expect((int) $loaded->getData('is_visible_on_front'))->toBe(1)
        ->and((int) $loaded->getData('is_html_allowed_on_front'))->toBe(1);
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

it('lists the visible values in the order of the attribute set', function () {
    $later = 'pest_later_' . $this->suffix;
    $sooner = 'pest_sooner_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $later, ['is_visible_on_front' => 1, 'sort_order' => 900]);
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $sooner, ['is_visible_on_front' => 1, 'sort_order' => 800]);
    $post = Mage::getModel('blog/post')->setData(['entity_id' => 1, $later => 'B', $sooner => 'A']);

    $codes = array_column(Mage::app()->getLayout()->createBlock('eav/frontend_attributes')->setData('entity', $post)->getAttributeValues(), 'code');

    expect(array_values(array_intersect($codes, [$later, $sooner])))->toBe([$sooner, $later]);
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

it('shows the first group on the main tab of the post edit page and adds a tab for each other group', function () {
    $setId = (int) Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY)->getDefaultAttributeSetId();
    $group = Mage::getModel('eav/entity_attribute_group')
        ->setAttributeSetId($setId)
        ->setAttributeGroupName('Pest Group ' . $this->suffix)
        ->setSortOrder(99);
    $group->save();
    $this->records[] = $group;
    $main = $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, 'pest_main_' . $this->suffix);
    $other = $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, 'pest_tab_' . $this->suffix, [
        'attribute_group_id' => (int) $group->getId(),
    ]);
    $post = Mage::getModel('blog/post');
    Mage::register('blog_post', $post);

    try {
        $tabs = Mage::app()->getLayout()->createBlock('blog/adminhtml_post_edit_tabs');
        $method = new ReflectionMethod($tabs, 'addAttributeTabs');
        $method->invoke($tabs);
        $attributeTabs = array_values(array_filter($tabs->getTabsIds(), fn(string $id): bool => str_starts_with($id, 'attribute_group_')));
        $mainCodes = array_map(
            fn($attribute) => $attribute->getAttributeCode(),
            Maho_Blog_Block_Adminhtml_Edit_Tabs::getMainTabAttributes($post),
        );
    } finally {
        Mage::unregister('blog_post');
    }

    expect($attributeTabs)->toBe(['attribute_group_' . $group->getId()])
        ->and($mainCodes)->toContain($main->getAttributeCode())
        ->and($mainCodes)->not->toContain($other->getAttributeCode());
});

it('lists only the attributes that an admin added on the blog attribute pages', function () {
    $attribute = $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, 'pest_list_' . $this->suffix);
    $type = Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY);

    $codes = Mage::helper('eav')->getManagedAttributeCollection($type)->getColumnValues('attribute_code');

    expect($codes)->toContain($attribute->getAttributeCode())
        ->and($codes)->not->toContain('image')
        ->and($codes)->not->toContain('store_id');
});

it('saves every blog attribute with the global scope', function () {
    $attribute = $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, 'pest_scope_' . $this->suffix, [
        'is_global' => Maho_Blog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    ]);

    $loaded = Mage::getModel('blog/resource_eav_attribute')->load($attribute->getId());
    expect((int) $loaded->getData('is_global'))->toBe(Maho_Blog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL);
});

it('sanitizes an HTML attribute value of a blog category on save', function () {
    $code = 'pest_cat_html_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Category::ENTITY, $code, [
        'backend_type' => 'text',
        'frontend_input' => 'textarea',
        'is_visible_on_front' => 1,
        'is_html_allowed_on_front' => 1,
    ]);
    $category = Mage::getModel('blog/category')->setData([
        'name' => 'Pest Html ' . $this->suffix,
        'url_key' => 'pest-cat-html-' . $this->suffix,
        'is_active' => 1,
        'stores' => [0],
        $code => '<p onclick="x()">A</p><script>alert(1)</script>',
    ]);
    $category->save();
    $this->records[] = $category;

    $saved = (string) Mage::getModel('blog/category')->load($category->getId())->getData($code);

    expect($saved)->toContain('<p>A</p>')
        ->and($saved)->not->toContain('<script')
        ->and($saved)->not->toContain('onclick');
});

it('sanitizes a stored value on the storefront when an admin allows HTML for its attribute later', function () {
    $codes = ['html' => 'pest_late_html_' . $this->suffix, 'wysiwyg' => 'pest_late_wys_' . $this->suffix];
    $attributes = array_map(fn(string $code) => $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $code, [
        'backend_type' => 'text',
        'frontend_input' => 'textarea',
        'is_visible_on_front' => 1,
    ]), $codes);
    $post = Mage::getModel('blog/post')->setData([
        'title' => 'Pest Late ' . $this->suffix,
        'url_key' => 'pest-late-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
    ] + array_fill_keys($codes, '<p>A</p><script>alert(1)</script>'));
    $post->save();
    $this->records[] = $post;

    $attributes['html']->setData('is_html_allowed_on_front', 1)->save();
    $attributes['wysiwyg']->setData('is_html_allowed_on_front', 1)->setData('is_wysiwyg_enabled', 1)->save();
    Mage::getSingleton('eav/config')->clear();
    Mage::getResourceSingleton('blog/post')->unsetAttributes();
    $block = Mage::app()->getLayout()->createBlock('blog/attributes')
        ->setData('entity', Mage::getModel('blog/post')->load($post->getId()));
    $rows = $block->getAttributeValues();
    $html = array_map(fn(string $code) => $block->getValueHtml($rows[array_search($code, array_column($rows, 'code'), true)]), $codes);

    expect($html['html'])->toContain('<p>A</p>')
        ->and($html['html'])->not->toContain('<script')
        ->and($html['wysiwyg'])->toContain('<p>A</p>')
        ->and($html['wysiwyg'])->not->toContain('<script');
});

it('keeps the output of a CMS block in a WYSIWYG value, because the value is sanitized before its directives resolve', function () {
    $code = 'pest_embed_attr_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $code, [
        'backend_type' => 'text',
        'frontend_input' => 'textarea',
        'is_visible_on_front' => 1,
        'is_html_allowed_on_front' => 1,
        'is_wysiwyg_enabled' => 1,
    ]);
    $cmsBlock = Mage::getModel('cms/block')
        ->setTitle('Pest embed')
        ->setIdentifier('pest_embed_' . $this->suffix)
        ->setIsActive(true)
        ->setStores([0])
        ->setContent('<p>x</p>');
    $cmsBlock->save();
    $this->records[] = $cmsBlock;
    // The save of a CMS block removes the iframe, so the test writes it into the table
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('cms/block'),
        ['content' => '<iframe src="https://www.youtube.com/embed/x"></iframe>'],
        ['block_id = ?' => (int) $cmsBlock->getId()],
    );
    $post = Mage::getModel('blog/post')->setData([
        'title' => 'Pest Embed ' . $this->suffix,
        'url_key' => 'pest-embed-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
        $code => '<p>A</p>{{block id="pest_embed_' . $this->suffix . '"}}',
    ]);
    $post->save();
    $this->records[] = $post;

    $block = Mage::app()->getLayout()->createBlock('blog/attributes')
        ->setData('entity', Mage::getModel('blog/post')->load($post->getId()));
    $rows = $block->getAttributeValues();
    $html = $block->getValueHtml($rows[array_search($code, array_column($rows, 'code'), true)]);

    expect($html)->toContain('<p>A</p>')
        ->and($html)->toContain('<iframe src="https://www.youtube.com/embed/x"');
});

it('saves a multiselect attribute that allows HTML as a list of option IDs', function () {
    $code = 'pest_multi_' . $this->suffix;
    $attribute = $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $code, [
        'frontend_input' => 'multiselect',
        'backend_model' => 'eav/entity_attribute_backend_array',
        'source_model' => 'eav/entity_attribute_source_table',
        'is_html_allowed_on_front' => 1,
        'option' => ['value' => ['o1' => ['One'], 'o2' => ['Two']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $ids = array_column(
        Mage::getModel('blog/resource_eav_attribute')->load($attribute->getId())->getSource()->getAllOptions(false),
        'value',
    );
    expect($ids)->toHaveCount(2);

    $post = Mage::getModel('blog/post')->setData([
        'title' => 'Pest Multi ' . $this->suffix,
        'url_key' => 'pest-multi-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
        $code => $ids,
    ]);
    $post->save();
    $this->records[] = $post;

    expect((string) Mage::getModel('blog/post')->load($post->getId())->getData($code))->toBe(implode(',', $ids));
});

it('sanitizes an HTML attribute value on save and resolves its directives on the storefront', function () {
    $code = 'pest_html_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $code, [
        'backend_type' => 'text',
        'frontend_input' => 'textarea',
        'is_visible_on_front' => 1,
        'is_html_allowed_on_front' => 1,
        'is_wysiwyg_enabled' => 1,
    ]);
    $post = Mage::getModel('blog/post')->setData([
        'title' => 'Pest Html ' . $this->suffix,
        'url_key' => 'pest-html-' . $this->suffix,
        'content' => '<p>Body</p>',
        'is_active' => 1,
        'stores' => [0],
        $code => '<p onclick="steal()">Visit <a href="{{store url=""}}">us</a></p><script>alert(1)</script>',
    ]);
    $post->save();
    $this->records[] = $post;

    $saved = (string) Mage::getModel('blog/post')->load($post->getId())->getData($code);
    $block = Mage::app()->getLayout()->createBlock('blog/attributes')
        ->setData('entity', Mage::getModel('blog/post')->load($post->getId()));
    $rows = $block->getAttributeValues();
    $html = $block->getValueHtml($rows[array_search($code, array_column($rows, 'code'), true)]);

    expect($saved)->not->toContain('<script')
        ->and($saved)->not->toContain('onclick')
        ->and($saved)->toContain('{{store url=""}}')
        ->and($html)->toContain('<a href="http')
        ->and($html)->not->toContain('{{');
});

it('shows a Text Area value as a text under the table, with its line breaks', function () {
    $story = 'pest_story_' . $this->suffix;
    $short = 'pest_short_' . $this->suffix;
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $story, [
        'backend_type' => 'text',
        'frontend_input' => 'textarea',
        'frontend_label' => 'Story',
        'is_visible_on_front' => 1,
    ]);
    $this->records[] = blogSetTestAttribute(Maho_Blog_Model_Post::ENTITY, $short, ['is_visible_on_front' => 1]);
    $post = Mage::getModel('blog/post')->setData(['entity_id' => 1, $story => "Line one\nLine <two>", $short => 'S']);

    $block = Mage::app()->getLayout()->createBlock('eav/frontend_attributes')->setData('entity', $post);
    $groups = $block->getAttributeGroups();
    $rows = array_merge(...array_column($groups, 'rows'));
    $texts = array_merge(...array_column($groups, 'texts'));
    $text = $texts[array_search($story, array_column($texts, 'code'), true)] ?? [];

    expect(array_column($rows, 'code'))->toContain($short)
        ->and(array_column($rows, 'code'))->not->toContain($story)
        ->and($text['label'] ?? null)->toBe('Story')
        ->and($block->getTextHtml($text))->toBe("Line one<br />\nLine &lt;two&gt;");
});
