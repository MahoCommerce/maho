<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function pageAttributeTestAttribute(array $data): Mage_Cms_Model_Attribute
{
    $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Cms_Model_Page::ENTITY);
    /** @var Mage_Cms_Model_Attribute $attribute */
    $attribute = Mage::getModel('cms/attribute')->setData($data + [
        'entity_type_id' => (int) $type->getId(),
        'backend_type' => 'static',
        'frontend_label' => $data['attribute_code'],
        'is_user_defined' => 1,
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    return $attribute;
}

function pageAttributeTestPage(array $data): Mage_Cms_Model_Page
{
    $page = Mage::getModel('cms/page')->setData($data + [
        'identifier' => 'pest-page-attribute-' . uniqid(),
        'title' => 'Pest Page Attribute',
        'content' => '<p>Pest</p>',
        'stores' => [0],
        'is_active' => 1,
    ]);
    return $page;
}

beforeEach(function () {
    $suffix = substr(uniqid(), -6);
    $this->codes = ['text' => 'pest_campaign_' . $suffix, 'multi' => 'pest_tags_' . $suffix, 'date' => 'pest_review_' . $suffix];
    $this->attributes = [];
    $this->pages = [];
    $resource = Mage::getSingleton('core/resource');
    $this->connection = $resource->getConnection('core_write');
    $this->table = $resource->getTableName('cms/page');
});

afterEach(function () {
    foreach ($this->pages as $page) {
        if ($page->getId()) {
            $page->delete();
        }
    }
    foreach ($this->attributes as $attribute) {
        if ($attribute->getId()) {
            $attribute->delete();
        }
    }
    foreach ($this->codes as $code) {
        if ($this->connection->tableColumnExists($this->table, $code)) {
            $this->connection->dropColumn($this->table, $code);
        }
    }
    $this->connection->resetDdlCache($this->table);
    Mage::getSingleton('eav/config')->clear();
});

it('gives CMS pages an entity type for their fields', function () {
    $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Cms_Model_Page::ENTITY);

    expect((int) $type->getId())->toBeGreaterThan(0)
        ->and($type->getAttributeModel())->toBe('cms/attribute')
        ->and((int) $type->getDefaultAttributeSetId())->toBeGreaterThan(0);
});

it('refuses a field whose code is a column of cms_page or a data key of a page', function (string $code) {
    expect(fn() => pageAttributeTestAttribute(['attribute_code' => $code, 'frontend_input' => 'text']))
        ->toThrow(Mage_Core_Exception::class);
})->with(['title', 'identifier', 'stores', 'store_id']);

it('adds the column of a new field and keeps it after a delete', function () {
    $code = $this->codes['text'];
    $attribute = pageAttributeTestAttribute(['attribute_code' => $code, 'frontend_input' => 'text']);

    $this->connection->resetDdlCache($this->table);
    expect($this->connection->tableColumnExists($this->table, $code))->toBeTrue()
        ->and(Mage::getResourceSingleton('cms/page')->getCustomAttributes())->toHaveKey($code);

    $attribute->delete();
    Mage::getSingleton('eav/config')->clear();

    $this->connection->resetDdlCache($this->table);
    expect($this->connection->tableColumnExists($this->table, $code))->toBeTrue()
        ->and(Mage::getResourceSingleton('cms/page')->getCustomAttributes())->not->toHaveKey($code);
});

it('saves a text, a multiselect and a date value of a page', function () {
    $this->attributes[] = pageAttributeTestAttribute(['attribute_code' => $this->codes['text'], 'frontend_input' => 'text']);
    $multi = $this->attributes[] = pageAttributeTestAttribute([
        'attribute_code' => $this->codes['multi'],
        'frontend_input' => 'multiselect',
        'option' => ['value' => ['o1' => ['Spring'], 'o2' => ['Summer']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $this->attributes[] = pageAttributeTestAttribute(['attribute_code' => $this->codes['date'], 'frontend_input' => 'date']);
    $optionIds = array_map(strval(...), array_column($multi->getSource()->getAllOptions(false), 'value'));

    $page = $this->pages[] = pageAttributeTestPage([
        $this->codes['text'] => 'SPRING26',
        $this->codes['multi'] => $optionIds,
        $this->codes['date'] => '2026-03-21',
    ]);
    $page->save();

    $loaded = Mage::getModel('cms/page')->load($page->getId());
    expect($loaded->getData($this->codes['text']))->toBe('SPRING26')
        ->and((string) $loaded->getData($this->codes['multi']))->toBe(implode(',', $optionIds))
        ->and(substr((string) $loaded->getData($this->codes['date']), 0, 10))->toBe('2026-03-21');
});

it('keeps the stored value when a save does not send the field', function () {
    $this->attributes[] = pageAttributeTestAttribute(['attribute_code' => $this->codes['text'], 'frontend_input' => 'text']);
    $page = $this->pages[] = pageAttributeTestPage([$this->codes['text'] => 'KEEP']);
    $page->save();

    $update = Mage::getModel('cms/page')->load($page->getId());
    $update->unsetData($this->codes['text'])->setTitle('Pest Page Attribute Renamed')->save();

    expect(Mage::getModel('cms/page')->load($page->getId())->getData($this->codes['text']))->toBe('KEEP');
});

it('refuses a new page without a required field, and uses the default value', function () {
    $attribute = $this->attributes[] = pageAttributeTestAttribute([
        'attribute_code' => $this->codes['text'],
        'frontend_input' => 'text',
        'is_required' => 1,
    ]);

    expect(fn() => pageAttributeTestPage([])->save())->toThrow(Mage_Core_Exception_Input::class);

    $attribute->setDefaultValue('DEFAULT')->save();
    Mage::getSingleton('eav/config')->clear();
    $page = $this->pages[] = pageAttributeTestPage([]);
    $page->save();

    expect(Mage::getModel('cms/page')->load($page->getId())->getData($this->codes['text']))->toBe('DEFAULT');
});

it('refuses the value of a unique field that another page uses', function () {
    $this->attributes[] = pageAttributeTestAttribute([
        'attribute_code' => $this->codes['text'],
        'frontend_input' => 'text',
        'is_unique' => 1,
    ]);
    $first = $this->pages[] = pageAttributeTestPage([$this->codes['text'] => 'ONLY-ONE']);
    $first->save();
    $first->setTitle('Pest Page Attribute Again')->save();

    expect(fn() => pageAttributeTestPage([$this->codes['text'] => 'ONLY-ONE'])->save())
        ->toThrow(Mage_Core_Exception_Input::class);
});

it('shows the Attributes tab only when CMS pages have an attribute', function () {
    Mage::register('cms_page', Mage::getModel('cms/page'), true);
    $tab = fn() => Mage::app()->getLayout()->createBlock('adminhtml/cms_page_edit_tab_attributes');

    expect($tab()->canShowTab())->toBe(Mage::getResourceSingleton('cms/page')->getCustomAttributes() !== []);

    $this->attributes[] = pageAttributeTestAttribute(['attribute_code' => $this->codes['text'], 'frontend_input' => 'text']);
    $design = Mage::getDesign();
    [$area, $package, $theme] = [$design->getArea(), $design->getPackageName(), $design->getTheme('template')];
    $design->setArea('adminhtml')->setPackageName('default')->setTheme('default');
    try {
        expect($tab()->canShowTab())->toBeTrue()
            ->and($tab()->toHtml())->toContain('page_' . $this->codes['text']);
    } finally {
        $design->setArea($area)->setPackageName($package)->setTheme($theme);
        Mage::unregister('cms_page');
    }
});
