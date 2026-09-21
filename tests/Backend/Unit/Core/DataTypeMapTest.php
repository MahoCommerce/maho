<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A value takes the PHP type of its typed setter when it enters setData(). The type is then
 * the same on every database backend.
 */

it('types a loaded row by the typed setters of the model', function () {
    $role = Mage::getModel('admin/role')->getCollection()->setPageSize(1)->getFirstItem();
    expect($role->getData('role_id'))->toBeInt()
        ->and($role->getData('tree_level'))->toBeInt()
        ->and($role->getData('role_name'))->toBeString();

    $loaded = Mage::getModel('admin/role')->load($role->getId());
    expect($loaded->getData('parent_id'))->toBeInt()
        ->and($loaded->getData('sort_order'))->toBeInt();
});

it('casts scalars on setData by setter type and passes the rest through', function () {
    $role = Mage::getModel('admin/role');
    $role->setData('tree_level', '2');
    $role->setData('sort_order', '');
    $role->setData('role_name', 7);
    $role->setData('parent_id', null);
    $role->setData('not_a_setter', '5');
    $role->setData('sort_order', new Maho\Db\Expr('sort_order + 1'));

    expect($role->getData('tree_level'))->toBe(2)
        ->and($role->getData('role_name'))->toBe('7')
        ->and($role->getData('parent_id'))->toBeNull()
        ->and($role->getData('not_a_setter'))->toBe('5')
        ->and($role->getData('sort_order'))->toBeInstanceOf(Maho\Db\Expr::class);
});

it('casts the array form of setData and addData', function () {
    $role = Mage::getModel('admin/role');
    $role->setData(['tree_level' => '1', 'role_name' => 'x']);
    $role->addData(['sort_order' => '3']);

    expect($role->getData('tree_level'))->toBe(1)
        ->and($role->getData('sort_order'))->toBe(3);
});

it('routes array access writes through setData', function () {
    $role = Mage::getModel('admin/role');
    $role['tree_level'] = '9';

    expect($role->getData('tree_level'))->toBe(9)
        ->and($role->hasDataChanges())->toBeTrue();
});

it('types a class without a table by its typed setters', function () {
    $request = new Mage_Shipping_Model_Rate_Request();
    $request->setData('dest_region_id', '5');
    $request->setData('package_weight', '2.5');
    $request->setData('free_shipping', '1');
    $request->setData('dest_postcode', '0123');

    expect($request->getData('dest_region_id'))->toBe(5)
        ->and($request->getData('package_weight'))->toBe(2.5)
        ->and($request->getData('free_shipping'))->toBeTrue()
        ->and($request->getData('dest_postcode'))->toBe('0123');
});

it('types EAV attribute values by their backend type', function () {
    $product = Mage::getModel('catalog/product')->getCollection()
        ->addAttributeToSelect(['price', 'status'])
        ->addAttributeToFilter('price', ['notnull' => true])
        ->setPageSize(1)
        ->getFirstItem();
    expect($product->getData('price'))->toBeFloat()
        ->and($product->getData('status'))->toBeInt();

    $loaded = Mage::getModel('catalog/product')->load($product->getId());
    expect($loaded->getData('price'))->toBeFloat()
        ->and($loaded->getData('status'))->toBeInt()
        ->and($loaded->getData('sku'))->toBeString();
});
