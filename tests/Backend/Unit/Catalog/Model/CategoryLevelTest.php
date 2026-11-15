<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('reads the level of a category as an int', function () {
    $category = Mage::getModel('catalog/category')->setData('level', '2');

    expect($category->getLevel())->toBe(2);
});

it('takes the level from the path when the category has no level', function () {
    $category = Mage::getModel('catalog/category')->setPath('1/2/7');

    expect($category->getLevel())->toBe(2);
});
