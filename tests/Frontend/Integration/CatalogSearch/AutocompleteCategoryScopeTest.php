<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

const AUTOCOMPLETE_SCOPE_TOKEN = 'Zzautosuggest';

function autocompleteScopeCleanup(): void
{
    $keys = ['zzautosuggest-foreign-root', 'zzautosuggest-foreign', 'zzautosuggest-local'];

    // A category delete is refused outside the admin area.
    Mage::register('isSecureArea', true, true);
    try {
        foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['in' => $keys]) as $category) {
            Mage::getModel('catalog/category')->load($category->getId())->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }
}

function makeAutocompleteCategory(string $name, string $urlKey, string $parentPath): Mage_Catalog_Model_Category
{
    /** @var Mage_Catalog_Model_Category $category */
    $category = Mage::getModel('catalog/category')
        ->setStoreId(0)
        ->setName($name)
        ->setUrlKey($urlKey)
        ->setIsActive(1)
        ->setIncludeInMenu(1);
    $category->setAttributeSetId($category->getDefaultAttributeSetId())->setPath($parentPath)->save();

    return $category;
}

beforeEach(fn() => autocompleteScopeCleanup());
afterEach(fn() => autocompleteScopeCleanup());

it('suggests only the categories of the current store', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    Mage::app()->setCurrentStore($store->getId());

    $storeRoot = Mage::getModel('catalog/category')->load($store->getRootCategoryId());
    expect($storeRoot->getId())->not->toBeEmpty();

    $foreignRoot = makeAutocompleteCategory(
        AUTOCOMPLETE_SCOPE_TOKEN . ' Foreign Root',
        'zzautosuggest-foreign-root',
        (string) Mage_Catalog_Model_Category::TREE_ROOT_ID,
    );
    $foreign = makeAutocompleteCategory(AUTOCOMPLETE_SCOPE_TOKEN . ' Foreign', 'zzautosuggest-foreign', $foreignRoot->getPath());
    $local = makeAutocompleteCategory(AUTOCOMPLETE_SCOPE_TOKEN . ' Local', 'zzautosuggest-local', $storeRoot->getPath());

    Mage::app()->getRequest()->setParam('q', AUTOCOMPLETE_SCOPE_TOKEN);

    /** @var Mage_CatalogSearch_Block_Autocomplete_Category_List $block */
    $block = Mage::app()->getLayout()->createBlock('catalogsearch/autocomplete_category_list');
    $ids = array_map(intval(...), $block->getCategoryCollection()->getAllIds());

    expect($ids)->toContain((int) $local->getId());
    expect($ids)->not->toContain((int) $foreign->getId());
    expect($ids)->not->toContain((int) $foreignRoot->getId());
});
