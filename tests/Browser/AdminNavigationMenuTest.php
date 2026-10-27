<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const NAV_MENU_ADMIN_USER = 'nav-menu-admin';
const NAV_MENU_ADMIN_PASSWORD = 'Password123!';

beforeEach(function () {
    createNavMenuAdmin();
});

afterEach(function () {
    deleteNavMenuAdmin();
});

function deleteNavMenuAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(NAV_MENU_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

function createNavMenuAdmin(): void
{
    deleteNavMenuAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(NAV_MENU_ADMIN_USER)
        ->setFirstname('Nav')
        ->setLastname('Menu')
        ->setEmail('nav-menu@example.test')
        ->setPassword(NAV_MENU_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

it('hides a closed submenu, and opens it on hover and on keyboard focus but not after a click', function () {
    $pw = adminLoginAndVisit(NAV_MENU_ADMIN_USER, NAV_MENU_ADMIN_PASSWORD, '/admin/dashboard', '.nav-bar:visible')->page();
    $catalog = $pw->locator('#nav-admin-catalog');
    $products = $pw->locator('#nav-admin-catalog + ul.d-menu > li > a')->first();
    $attributes = $pw->locator('#nav-admin-catalog-attributes');
    $manageAttributes = $pw->locator('#nav-admin-catalog-attributes-attributes');
    $sales = $pw->locator('#nav-admin-sales');
    $footer = $pw->locator('.footer');

    $footer->hover();
    expect($products->isVisible())->toBeFalse();

    $catalog->hover();
    expect($products->isVisible())->toBeTrue();

    $catalog->click();
    $sales->hover();
    expect($products->isVisible())->toBeFalse();

    $catalog->hover();
    $attributes->hover();
    expect($manageAttributes->isVisible())->toBeTrue();

    $attributes->click();
    $sales->hover();
    expect($products->isVisible())->toBeFalse()
        ->and($manageAttributes->isVisible())->toBeFalse();

    // Focus from the keyboard, so that the browser treats it as keyboard focus and not as a click
    $footer->hover();
    $catalog->focus();
    $catalog->press('Shift+Tab');
    $pw->locator(':focus')->press('Tab');
    expect($products->isVisible())->toBeTrue();
});
