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
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['in' => ['urlpath-root', 'urlpath-top', 'urlpath-child']]) as $category) {
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
