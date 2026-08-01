<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Regression for the admin store switcher corrupting a url that ends in a query string.
 *
 * getSwitchUrl() carries the current query string over, so on a grid reached with one
 * (?form_key=... in the report) the switcher's "store/<id>/" used to be concatenated onto
 * the last query param's *value*: the store view never arrived, the form key came back
 * corrupted, and repeated switches accumulated. Only a browser exercises that js.
 *
 * Secret keys are turned off for the run so the grid can be addressed directly; the query
 * string the switcher has to preserve is therefore supplied by the test itself.
 */

const STORE_SWITCHER_ADMIN_USER = 'store-switcher-admin';
const STORE_SWITCHER_ADMIN_PASSWORD = 'Password123!';
const STORE_SWITCHER_QUERY = 'form_key=PestStoreSwitcherFormKey';

beforeEach(function () {
    Mage::getModel('core/config')->saveConfig(Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_SECURITY_USE_FORM_KEY, 0);
    Mage::app()->cleanCache();

    createStoreSwitcherAdmin();
});

afterEach(function () {
    deleteStoreSwitcherAdmin();

    Mage::getModel('core/config')->deleteConfig(Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_SECURITY_USE_FORM_KEY);
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
});

function deleteStoreSwitcherAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(STORE_SWITCHER_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** A fresh admin with the full-access role, so the password is known and ACL never gets in the way. */
function createStoreSwitcherAdmin(): void
{
    deleteStoreSwitcherAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(STORE_SWITCHER_ADMIN_USER)
        ->setFirstname('Store')
        ->setLastname('Switcher')
        ->setEmail('store-switcher@example.test')
        ->setPassword(STORE_SWITCHER_ADMIN_PASSWORD)
        ->setIsActive(1)
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

/** The first switchable store view. */
function storeSwitcherStoreId(): int
{
    return (int) array_key_first(Mage::app()->getStores());
}

/** Log in and open the product grid with a query string on it. */
function visitProductGridWithQuery(): object
{
    $page = visit(MahoServer::baseUrl() . '/admin')
        ->fill('#username', STORE_SWITCHER_ADMIN_USER)
        ->fill('#login', STORE_SWITCHER_ADMIN_PASSWORD)
        ->click('#step1 input[type="submit"]');

    waitForPageLoad($page, '.nav-bar:visible');
    $page->navigate(MahoServer::baseUrl() . '/admin/catalog_product/index/?' . STORE_SWITCHER_QUERY);

    return waitForPageLoad($page, '#store_switcher');
}

/**
 * Pick a store view and land on the page it navigates to.
 *
 * The switcher is on the page being left too, so no element gate tells the two apart:
 * the sleep covers the navigation starting, which waitForPageLoad() can't.
 */
function switchStoreView(object $page, string $value): object
{
    $page->select('#store_switcher', $value)->wait(2);

    return waitForPageLoad($page, '#store_switcher');
}

it('puts the store view in the path and leaves the query string alone', function () {
    $storeId = storeSwitcherStoreId();

    $url = switchStoreView(visitProductGridWithQuery(), (string) $storeId)->url();

    // Pre-fix both fail: the segment landed in the query as "?form_key=<key>store/<id>/".
    expect(parse_url($url, PHP_URL_PATH))->toContain('/store/' . $storeId . '/')
        ->and(parse_url($url, PHP_URL_QUERY))->toBe(STORE_SWITCHER_QUERY);
});

it('drops the store view again when switching back to all store views', function () {
    $page = switchStoreView(visitProductGridWithQuery(), (string) storeSwitcherStoreId());

    // Pre-fix the segments piled up, one per switch, inside the query param's value.
    $url = switchStoreView($page, '')->url();

    expect(parse_url($url, PHP_URL_PATH))->not->toContain('/store/')
        ->and(parse_url($url, PHP_URL_QUERY))->toBe(STORE_SWITCHER_QUERY);
});
