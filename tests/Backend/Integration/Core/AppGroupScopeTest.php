<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function storeOfGroupScope(string $scopeCode): ?string
{
    $app = new Mage_Core_Model_App();
    (new ReflectionMethod($app, '_initCurrentStore'))->invoke($app, $scopeCode, 'group');

    return (new ReflectionProperty($app, '_currentStore'))->getValue($app);
}

it('runs the default store of the store group that the group scope code names', function () {
    $group = Mage::app()->getGroup(1);

    expect(storeOfGroupScope('1'))->toBe(Mage::app()->getStore($group->getDefaultStoreId())->getCode());
});

it('runs no store for a group scope code that is not a group ID', function (string $scopeCode) {
    expect(storeOfGroupScope($scopeCode))->toBeNull();
})->with(['1-us', '01']);
