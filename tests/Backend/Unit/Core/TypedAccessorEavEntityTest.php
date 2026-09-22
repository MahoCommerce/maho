<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Wave 6 converted the EAV entities. Only the attributes that ship with the
 * schema are typed, so a merchant attribute still reaches __call. Database
 * shaped input must come back typed, an unset key must stay null, and the
 * annotations that named the wrong type must now name the right one.
 */

dataset('eav entity round trips', [
    'product decimal' => ['catalog/product', 'cost', '25.5000', 25.5],
    'product int' => ['catalog/product', 'visibility', '4', 4],
    'product flag' => ['catalog/product', 'has_options', '1', true],
    'product string' => ['catalog/product', 'sku', 'SKU-001', 'SKU-001'],
    'product tax class' => ['catalog/product', 'tax_class_id', '2', 2],
    'category flag' => ['catalog/category', 'is_active', '1', true],
    'category int' => ['catalog/category', 'children_count', '3', 3],
    'category string' => ['catalog/category', 'display_mode', 'PRODUCTS', 'PRODUCTS'],
    'customer attribute int' => ['customer/attribute', 'scope_multiline_count', '2', 2],
]);

it('returns typed values from database-shaped input', function (string $model, string $key, string $in, float|int|bool|string $out) {
    $object = Mage::getModel($model);
    $object->setData($key, $in);

    $getter = 'get' . str_replace('_', '', ucwords($key, '_'));

    expect($object->$getter())->toBe($out);
})->with('eav entity round trips');

it('returns null for a key the entity does not hold', function () {
    expect(Mage::getModel('catalog/product')->getCost())->toBeNull()
        ->and(Mage::getModel('catalog/product')->getVisibility())->toBeNull()
        ->and(Mage::getModel('catalog/category')->getIsActive())->toBeNull()
        ->and(Mage::getModel('catalog/category')->getUrlKey())->toBeNull()
        ->and(Mage::getModel('customer/attribute')->getScopeMultilineCount())->toBeNull();
});

it('leaves a merchant attribute on the magic accessor', function () {
    $product = Mage::getModel('catalog/product')->setData('my_custom_attribute', '7');

    expect($product->getMyCustomAttribute())->toBe('7');
});

it('types the keys whose annotation named the wrong type', function () {
    // MAP has three states (no, yes, use config), so it is not a flag
    $msrp = Mage::getModel('catalog/product')->setData('msrp_enabled', '2');
    // a bundle selection id is an integer column, not a string
    $selection = Mage::getModel('catalog/product')->setData('selection_id', '5');
    // a position is an integer column, not a string
    $position = Mage::getModel('catalog/product')->setData('position', '3');
    // a bundle shipment type is an integer attribute, not a string
    $shipment = Mage::getModel('catalog/product')->setData('shipment_type', '1');

    expect($msrp->getMsrpEnabled())->toBe(2)
        ->and($selection->getSelectionId())->toBe(5)
        ->and($position->getPosition())->toBe(3)
        ->and($shipment->getShipmentType())->toBe(1);
});

it('holds the quote item that a child product sticks to', function () {
    $item = Mage::getModel('sales/quote_item');
    $product = Mage::getModel('catalog/product')->setStickWithinParent($item);

    expect($product->getStickWithinParent())->toBe($item)
        ->and(Mage::getModel('catalog/product')->getStickWithinParent())->toBeNull();
});

it('keeps the product parent flag readable as a flag and as an id', function () {
    $flagged = Mage::getModel('catalog/product')->setParentId(true);
    $numbered = Mage::getModel('catalog/product')->setParentId(0);

    expect($flagged->getParentId())->toBeTrue()
        ->and($numbered->getParentId())->toBe(0);
});

it('reads the media gallery images without an index argument', function () {
    $product = Mage::getModel('catalog/product')->setMediaGallery([
        'images' => [
            ['value_id' => '1', 'file' => '/m/a/maho.jpg', 'disabled' => '0', 'label' => 'Maho'],
        ],
    ]);

    $images = $product->getMediaGalleryImages();

    expect($images->count())->toBe(1)
        ->and($images->getFirstItem()->getFile())->toBe('/m/a/maho.jpg');
});

it('accepts a store object and a store id on the product store key', function () {
    $store = Mage::app()->getStore();

    expect(Mage::getModel('catalog/product')->setStore($store)->getData('store'))->toBe($store)
        ->and(Mage::getModel('catalog/product')->setStore(0)->getData('store'))->toBe(0);
});

it('defaults a flag setter to true and still takes false', function () {
    expect(Mage::getModel('catalog/category')->setIsActive()->getIsActive())->toBeTrue()
        ->and(Mage::getModel('catalog/category')->setIsActive(false)->getIsActive())->toBeFalse()
        ->and(Mage::getModel('catalog/product')->setIsDuplicate()->getIsDuplicate())->toBeTrue()
        ->and(Mage::getModel('customer/attribute')->setScopeIsVisible()->getData('scope_is_visible'))->toBeTrue();
});
