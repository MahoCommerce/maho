<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Categories;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function categoriesCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'categories') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function categoriesRootName(): string
{
    return Mage::getModel('catalog/category')->load(Mage::app()->getStore(1)->getRootCategoryId())->getName();
}

function categoriesFind(string $urlKey): Mage_Catalog_Model_Category
{
    $root = Mage::app()->getStore(1)->getRootCategoryId();
    $id = Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToFilter('url_key', $urlKey)
        ->addFieldToFilter('path', ['like' => "%/$root/%"])
        ->getFirstItem()
        ->getId();
    return Mage::getModel('catalog/category')->setStoreId(0)->load($id);
}

function categoriesCleanup(): void
{
    $root = Mage::app()->getStore(1)->getRootCategoryId();
    $collection = Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToFilter('url_key', 'imp-cat')
        ->addFieldToFilter('path', ['like' => "%/$root/%"]);
    foreach ($collection as $category) {
        Mage::getModel('catalog/category')->load($category->getId())->delete();
    }
    @unlink(Mage::getBaseDir('media') . '/catalog/category/imp-cat.png');
}

beforeEach(fn() => categoriesCleanup());
afterEach(fn() => categoriesCleanup());

it('creates a tree below the root, applies store overrides and reruns without duplicates', function (): void {
    $root = categoriesRootName();
    $store = Mage::app()->getStore(1)->getCode();
    $mediaDir = sys_get_temp_dir() . '/imp-cat-media-' . uniqid();
    mkdir($mediaDir);
    imagepng(imagecreatetruecolor(4, 4), $mediaDir . '/imp-cat.png');
    $path = categoriesCsv([
        ['_root', '_path', '_store', 'name', 'is_anchor', 'description', 'image', 'position', 'display_mode', 'landing_page'],
        [$root, 'imp-cat/child', '', 'Imp Child', '0', '', '', '2', '', ''],
        [$root, 'imp-cat', '', 'Imp Cat', '1', 'Parent text', 'imp-cat.png', '7', 'Static block and products', ''],
        [$root, 'imp-cat', $store, 'Imp Cat Store', '', '', '', '', '', ''],
    ]);

    expect((new Categories())->import($path, [Categories::OPTION_MEDIA_DIR => $mediaDir])->created)->toBe(2);

    $parent = categoriesFind('imp-cat');
    expect($parent->getName())->toBe('Imp Cat');
    expect((int) $parent->getLevel())->toBe(2);
    expect((int) $parent->getPosition())->toBe(7);
    expect($parent->getDisplayMode())->toBe(Mage_Catalog_Model_Category::DM_MIXED);
    expect($parent->getImage())->toBe('imp-cat.png');
    expect(is_file(Mage::getBaseDir('media') . '/catalog/category/imp-cat.png'))->toBeTrue();
    $child = Mage::getModel('catalog/category')->getCollection()
        ->setStoreId(0)
        ->addAttributeToSelect(['name', 'is_anchor'])
        ->addAttributeToFilter('url_key', 'child')
        ->addFieldToFilter('parent_id', $parent->getId())
        ->getFirstItem();
    expect($child->getName())->toBe('Imp Child');
    expect((int) $child->getIsAnchor())->toBe(0);
    expect((int) $parent->getChildrenCount())->toBe(1);
    expect(Mage::getModel('catalog/category')->setStoreId(1)->load($parent->getId())->getName())->toBe('Imp Cat Store');

    (new Categories())->import($path, [Categories::OPTION_MEDIA_DIR => $mediaDir]);
    expect(Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', 'imp-cat')->count())->toBe(1);
    unlink($path);
    unlink($mediaDir . '/imp-cat.png');
    rmdir($mediaDir);
});

it('reads back the file of export:categories', function (): void {
    $root = categoriesRootName();
    $source = categoriesCsv([['_root', '_path', 'name', 'description'], [$root, 'imp-cat', 'Imp Cat', 'Exported text']]);
    (new Categories())->import($source);
    $exported = tempnam(sys_get_temp_dir(), 'categories-export') . '.csv';
    Mage::getModel('importexport/export')
        ->setData(['entity' => 'catalog_category', 'file_format' => 'csv', 'export_filter' => ['url_key' => 'imp-cat']])
        ->setDestination($exported)
        ->exportFile();
    categoriesCleanup();

    (new Categories())->import($exported);

    expect(categoriesFind('imp-cat')->getDescription())->toBe('Exported text');
    unlink($source);
    unlink($exported);
});

it('rejects a bad url key, an unknown root and a missing picture with the line number', function (): void {
    $root = categoriesRootName();
    $importer = new Categories();

    $path = categoriesCsv([['_root', '_path', 'name'], [$root, 'Imp Cat', 'Imp Cat']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "Category path \"$root/Imp Cat\" is invalid (line 2)");
    unlink($path);

    $path = categoriesCsv([['_root', '_path', 'name'], ['No Such Root', 'imp-cat', 'Imp Cat']]);
    expect(fn() => $importer->import($path))->toThrow(RowException::class, 'Parent category for path "No Such Root/imp-cat" not found');
    unlink($path);

    $path = categoriesCsv([['_root', '_path', 'name', 'image'], [$root, 'imp-cat', 'Imp Cat', 'nope.png']]);
    expect(fn() => $importer->validate($path, [Categories::OPTION_MEDIA_DIR => sys_get_temp_dir()]))->toThrow(RowException::class, 'Image "nope.png" not found');
    unlink($path);

    expect(Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', 'imp-cat')->count())->toBe(0);
});
