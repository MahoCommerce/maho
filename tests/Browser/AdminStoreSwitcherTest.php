<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

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
 * The grid is addressed directly with a minted secret key; the query string the switcher
 * has to preserve is supplied by the test itself.
 */

const STORE_SWITCHER_ADMIN_USER = 'store-switcher-admin';
const STORE_SWITCHER_ADMIN_PASSWORD = 'Password123!';
const STORE_SWITCHER_QUERY = 'form_key=PestStoreSwitcherFormKey';

beforeEach(function () {
    createBrowserAdmin(STORE_SWITCHER_ADMIN_USER, STORE_SWITCHER_ADMIN_PASSWORD);
});

afterEach(function () {
    deleteBrowserAdmin(STORE_SWITCHER_ADMIN_USER);
});

/** The first switchable store view. */
function storeSwitcherStoreId(): int
{
    return (int) array_key_first(Mage::app()->getStores());
}

/** Log in and open the product grid with a query string on it. */
function visitProductGridWithQuery(): object
{
    return adminLoginAndVisit(
        STORE_SWITCHER_ADMIN_USER,
        STORE_SWITCHER_ADMIN_PASSWORD,
        '/admin/catalog_product/index/?' . STORE_SWITCHER_QUERY,
        '#store_switcher',
    );
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
