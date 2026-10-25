<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A special price without a start date starts on the store day of the save. The start date is a
 * day: a store time in the hour that a DST change repeats reads back one hour later, so the
 * special price did not apply in that hour.
 */
it('sets the store day without a time as the start date of a new special price', function (): void {
    $product = Mage::getModel('catalog/product')->setSpecialPrice(39.99);
    $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'special_from_date');

    $attribute->getBackend()->beforeSave($product);

    $storeDay = Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT);
    expect($product->getSpecialFromDate())->toBe($storeDay . ' 00:00:00')
        ->and(Mage::app()->getLocale()->isStoreDateInInterval(null, $product->getSpecialFromDate()))->toBeTrue();
});

it('keeps an empty start date when the product has no special price', function (): void {
    $product = Mage::getModel('catalog/product');
    $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'special_from_date');

    $attribute->getBackend()->beforeSave($product);

    expect($product->getSpecialFromDate())->toBeNull();
});
