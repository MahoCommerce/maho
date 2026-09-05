<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Products;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function productsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'products') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function productsCleanup(): void
{
    $collection = Mage::getResourceModel('catalog/product_collection')->addFieldToFilter('sku', ['like' => 'IMP-%']);
    foreach ($collection as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    @unlink(Mage::getBaseDir('media') . '/catalog/product/i/m/imp-pic.png');
}

function productsRootName(): string
{
    return Mage::getModel('catalog/category')->load(Mage::app()->getStore(1)->getRootCategoryId())->getName();
}

beforeEach(fn() => productsCleanup());
afterEach(fn() => productsCleanup());

it('imports a simple product with a picture from the media folder and reruns without a second picture', function (): void {
    $website = Mage::app()->getStore(1)->getWebsite()->getCode();
    $mediaDir = sys_get_temp_dir() . '/imp-products-' . uniqid();
    mkdir($mediaDir);
    imagepng(imagecreatetruecolor(4, 4), $mediaDir . '/imp-pic.png');
    $path = productsCsv([
        ['sku', '_attribute_set', '_type', '_product_websites', '_root_category', 'name', 'price', 'status', 'visibility', 'tax_class_id', 'weight', 'description', 'short_description', 'qty', 'is_in_stock', '_media_image', 'image', 'small_image', 'thumbnail'],
        ['IMP-SIMPLE', 'Default', 'simple', $website, productsRootName(), 'Imp Simple', '12.50', '1', '4', '2', '1', 'Long', 'Short', '5', '1', 'imp-pic.png', 'imp-pic.png', 'imp-pic.png', 'imp-pic.png'],
    ]);
    $options = [Products::OPTION_MEDIA_DIR => $mediaDir];
    $source = file_get_contents($path);

    $result = (new Products())->import($path, $options);
    expect($result->created)->toBe(1);
    expect(file_get_contents($path))->toBe($source);
    expect(is_file($mediaDir . '/imp-pic.png'))->toBeTrue();

    $product = Mage::getModel('catalog/product')->load(Mage::getModel('catalog/product')->getIdBySku('IMP-SIMPLE'));
    expect($product->getName())->toBe('Imp Simple');
    expect((float) $product->getPrice())->toBe(12.5);
    expect($product->getImage())->toBe('/i/m/imp-pic.png');
    expect(is_file(Mage::getBaseDir('media') . '/catalog/product/i/m/imp-pic.png'))->toBeTrue();
    expect(array_map('intval', $product->getWebsiteIds()))->toContain((int) Mage::app()->getStore(1)->getWebsiteId());

    (new Products())->import($path, $options);
    $gallery = Mage::getModel('catalog/product')->load($product->getId())->getMediaGalleryImages();
    expect($gallery->count())->toBe(1);
    expect(is_file(Mage::getBaseDir('media') . '/catalog/product/i/m/imp-pic_1.png'))->toBeFalse();
    expect(Mage::getResourceModel('catalog/product_collection')->addFieldToFilter('sku', 'IMP-SIMPLE')->count())->toBe(1);

    unlink($path);
    unlink($mediaDir . '/imp-pic.png');
    rmdir($mediaDir);
});

it('rejects a missing picture, a category without a root, an injected column and an entity error, with line numbers', function (): void {
    $website = Mage::app()->getStore(1)->getWebsite()->getCode();
    $importer = new Products();
    $options = [Products::OPTION_MEDIA_DIR => sys_get_temp_dir()];
    $header = ['sku', '_attribute_set', '_type', '_product_websites', '_root_category', '_category', 'name', 'price', '_media_image'];

    $path = productsCsv([$header, ['IMP-A', 'Default', 'simple', $website, productsRootName(), '', 'A', '1', 'nope.png']]);
    expect(fn() => $importer->validate($path, $options))->toThrow(RowException::class, "line 2: _media_image 'nope.png' not found");
    unlink($path);

    $path = productsCsv([$header, ['IMP-A', 'Default', 'simple', $website, '', 'Some Cat', 'A', '1', '']]);
    expect(fn() => $importer->validate($path, $options))->toThrow(RowException::class, '_root_category is required');
    unlink($path);

    $path = productsCsv([[...$header, '_media_attribute_id'], ['IMP-A', 'Default', 'simple', $website, productsRootName(), '', 'A', '1', '', '88']]);
    expect(fn() => $importer->validate($path, $options))->toThrow(RowException::class, 'set by the importer');
    unlink($path);

    $path = productsCsv([$header, ['IMP-A', 'Default', 'simple', $website, productsRootName(), '', 'A', 'free', '']]);
    $source = file_get_contents($path);
    expect(fn() => $importer->validate($path, $options))->toThrow(RowException::class, "line 2: Invalid value for 'price' (line 2)");
    expect(file_get_contents($path))->toBe($source);
    unlink($path);

    expect(Mage::getModel('catalog/product')->getIdBySku('IMP-A'))->toBeFalse();
});
