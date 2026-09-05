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
        ['root', 'path', 'store_code', 'name', 'is_anchor', 'description', 'image', 'position'],
        [$root, 'imp-cat/child', '', 'Imp Child', '0', '', '', '2'],
        [$root, 'imp-cat', '', 'Imp Cat', '1', 'Parent text', 'imp-cat.png', '7'],
        [$root, 'imp-cat', $store, 'Imp Cat Store', '', '', '', ''],
    ]);

    $result = (new Categories())->import($path, [Categories::OPTION_MEDIA_DIR => $mediaDir]);
    expect($result->created)->toBe(2)->and($result->updated)->toBe(1);

    $rootId = Mage::app()->getStore(1)->getRootCategoryId();
    $parent = Mage::getResourceModel('catalog/category_collection')
        ->setStoreId(0)
        ->addAttributeToSelect(['name', 'is_anchor', 'image', 'position'])
        ->addAttributeToFilter('url_key', 'imp-cat')
        ->addFieldToFilter('path', ['like' => "%/$rootId/%"])
        ->getFirstItem();
    expect($parent->getName())->toBe('Imp Cat');
    expect((int) $parent->getLevel())->toBe(2);
    expect((int) $parent->getPosition())->toBe(7);
    expect($parent->getImage())->toBe('imp-cat.png');
    expect(is_file(Mage::getBaseDir('media') . '/catalog/category/imp-cat.png'))->toBeTrue();
    $child = Mage::getModel('catalog/category')->getCollection()
        ->setStoreId(0)
        ->addAttributeToSelect('name')
        ->addAttributeToFilter('url_key', 'child')
        ->addFieldToFilter('parent_id', $parent->getId())
        ->getFirstItem();
    expect($child->getName())->toBe('Imp Child');
    expect((int) $child->getIsAnchor())->toBe(0);
    expect(Mage::getModel('catalog/category')->setStoreId(1)->load($parent->getId())->getName())->toBe('Imp Cat Store');

    $again = (new Categories())->import($path, [Categories::OPTION_MEDIA_DIR => $mediaDir]);
    expect($again->created)->toBe(0)->and($again->updated)->toBe(3);
    expect(Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', 'imp-cat')->count())->toBe(1);
    unlink($path);
    unlink($mediaDir . '/imp-cat.png');
    rmdir($mediaDir);
});

it('rejects a bad url key, an unknown root and a missing picture with the line number', function (): void {
    $root = categoriesRootName();
    $importer = new Categories();

    $path = categoriesCsv([['root', 'path', 'name'], [$root, 'Imp Cat', 'Imp Cat']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'line 2: path segment');
    unlink($path);

    $path = categoriesCsv([['root', 'path', 'name'], ['No Such Root', 'imp-cat', 'Imp Cat']]);
    expect(fn() => $importer->import($path))->toThrow(RowException::class, 'No Such Root');
    unlink($path);

    $path = categoriesCsv([['root', 'path', 'name', 'image'], [$root, 'imp-cat', 'Imp Cat', 'nope.png']]);
    expect(fn() => $importer->validate($path, [Categories::OPTION_MEDIA_DIR => sys_get_temp_dir()]))->toThrow(RowException::class, "image 'nope.png' not found");
    unlink($path);

    expect(Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', 'imp-cat')->count())->toBe(0);
});
