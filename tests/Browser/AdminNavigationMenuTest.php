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
    deleteBrowserAdmin(NAV_MENU_ADMIN_USER);
}

function createNavMenuAdmin(): void
{
    deleteNavMenuAdmin();

    createBrowserAdmin(NAV_MENU_ADMIN_USER, NAV_MENU_ADMIN_PASSWORD);
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

    // Shift+Tab from the next entry gives Catalog keyboard focus, which a click does not give
    $footer->hover();
    $customer = $pw->locator('#nav-admin-customer');
    $customer->focus();
    $customer->press('Shift+Tab');
    expect($products->isVisible())->toBeTrue();
});
