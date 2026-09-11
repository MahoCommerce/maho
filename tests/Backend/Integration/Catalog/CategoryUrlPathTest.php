<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function urlPathCleanup(): void
{
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['in' => ['urlpath-root', 'urlpath-top', 'urlpath-child', 'urlpath-first', 'urlpath-second']]) as $category) {
        Mage::getModel('catalog/category')->load($category->getId())->delete();
    }
}

beforeEach(fn() => urlPathCleanup());
afterEach(fn() => urlPathCleanup());

it('builds the url path of a category below a root without a leading slash', function (): void {
    $root = Mage::getModel('catalog/category')->setStoreId(0)->setName('Urlpath Root')->setUrlKey('urlpath-root')->setIsActive(1);
    $root->setAttributeSetId($root->getDefaultAttributeSetId())->setPath((string) Mage_Catalog_Model_Category::TREE_ROOT_ID)->save();
    $top = Mage::getModel('catalog/category')->setStoreId(0)->setName('Urlpath Top')->setUrlKey('urlpath-top')->setIsActive(1);
    $top->setAttributeSetId($top->getDefaultAttributeSetId())->setPath($root->getPath())->save();
    $child = Mage::getModel('catalog/category')->setStoreId(0)->setName('Urlpath Child')->setUrlKey('urlpath-child')->setIsActive(1);
    $child->setAttributeSetId($child->getDefaultAttributeSetId())->setPath($top->getPath())->save();

    expect(Mage::getModel('catalog/category')->load($root->getId())->getUrlPath())->toBe('');
    expect(Mage::getModel('catalog/category')->load($top->getId())->setUrlPath(null)->getUrlPath())->toBe('urlpath-top');
    expect(Mage::getModel('catalog/category')->load($child->getId())->setUrlPath(null)->getUrlPath())->toBe('urlpath-top/urlpath-child');
});

it('keeps the url suffix when a category takes back a url key it used before', function (): void {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    // The default suffix is empty, and without one the broken and the correct request path are identical
    Mage::app()->getStore($storeId)->setConfig(Mage_Catalog_Helper_Category::XML_PATH_CATEGORY_URL_SUFFIX, '.html');
    // The helper caches the suffix per store on first read, so a stale cache would silently make the test pass
    expect(Mage::helper('catalog/category')->getCategoryUrlSuffix($storeId))->toBe('.html');
    Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory(true);

    $root = Mage::getModel('catalog/category')->load(Mage::app()->getStore($storeId)->getRootCategoryId());
    $category = Mage::getModel('catalog/category')->setStoreId(0)->setName('Urlpath Reclaim')->setUrlKey('urlpath-first')->setIsActive(1);
    $category->setAttributeSetId($category->getDefaultAttributeSetId())->setPath($root->getPath())->save();

    // saveAttribute() skips the URL indexer, so each change gets exactly one refresh: a second one repairs the path
    $changeUrlKey = function (string $urlKey) use ($category, $storeId): string {
        $category->setUrlKey($urlKey);
        $category->getResource()->saveAttribute($category, 'url_key');
        Mage::getSingleton('catalog/url')->refreshCategoryRewrite($category->getId(), $storeId, false);

        return Mage::getModel('core/url_rewrite')->setStoreId($storeId)->loadByIdPath('category/' . $category->getId())->getRequestPath();
    };

    expect($changeUrlKey('urlpath-first'))->toBe('urlpath-first.html')
        ->and($changeUrlKey('urlpath-second'))->toBe('urlpath-second.html')
        ->and(Mage::getModel('core/url_rewrite')->setStoreId($storeId)->loadByRequestPath('urlpath-first.html')->getTargetPath())
        ->toBe('urlpath-second.html')
        ->and($changeUrlKey('urlpath-first'))->toBe('urlpath-first.html');
});
