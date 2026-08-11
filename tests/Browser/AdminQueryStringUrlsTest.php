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
 * Regression for admin screens that append route params to a url built with _current => true.
 *
 * _current copies the current query string into the next url. Code that then concatenates
 * "key/value/" onto that url writes the segment into the last query param's value instead of
 * the path, and the param never arrives. Same defect as the store switcher; these are the two
 * other places that had it.
 *
 * The screens are addressed directly with a minted secret key; the query string the screens
 * have to survive is supplied by the test itself.
 */

const QUERY_URLS_ADMIN_USER = 'query-urls-admin';
const QUERY_URLS_ADMIN_PASSWORD = 'Password123!';
const QUERY_URLS_QUERY = 'pest_query=PestQueryStringUrls';

beforeEach(function () {
    createQueryUrlsAdmin();
});

afterEach(function () {
    deleteQueryUrlsAdmin();
});

function deleteQueryUrlsAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(QUERY_URLS_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** A fresh admin with the full-access role, so the password is known and ACL never gets in the way. */
function createQueryUrlsAdmin(): void
{
    deleteQueryUrlsAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(QUERY_URLS_ADMIN_USER)
        ->setFirstname('Query')
        ->setLastname('Urls')
        ->setEmail('query-urls@example.test')
        ->setPassword(QUERY_URLS_ADMIN_PASSWORD)
        ->setIsActive(1)
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

/** Log in and open an admin page, waiting for one of its elements. */
function visitAdminPage(string $path, string $selector): object
{
    $page = visit(MahoServer::baseUrl() . '/admin')
        ->fill('#username', QUERY_URLS_ADMIN_USER)
        ->fill('#login', QUERY_URLS_ADMIN_PASSWORD)
        ->click('#step1 input[type="submit"]');

    waitForPageLoad($page, '.nav-bar:visible');
    $page->navigate(MahoServer::baseUrl() . adminPathWithSecretKey($page, $path));

    return waitForPageLoad($page, $selector);
}

/**
 * Run the report for a date range and land on the page it navigates to.
 *
 * The filter form is on the page being left too, so no element gate tells the two apart:
 * the sleep covers the navigation starting, which waitForPageLoad() can't.
 */
function submitReportFilter(object $page, string $from, string $to): string
{
    $page->fill('#sales_report_from', $from)
        ->fill('#sales_report_to', $to)
        ->click('Show Report')
        ->wait(2);

    return waitForPageLoad($page, '#filter_form')->url();
}

it('keeps the report filter in the path when the report is run twice', function () {
    // _current carries the query string into the url the first run navigates to, so the
    // second one starts from a url that ends in one - which is where the segment used to land.
    $page = visitAdminPage('/admin/report_product/viewed?' . QUERY_URLS_QUERY, '#filter_form');
    $first = submitReportFilter($page, '2026-07-01', '2026-07-31');
    $second = submitReportFilter($page, '2026-06-01', '2026-06-30');

    expect(parse_url($second, PHP_URL_QUERY))->not->toContain('filter')
        ->and(parse_url($second, PHP_URL_PATH))->toContain('/filter/')
        ->and(parse_url($second, PHP_URL_PATH))->not->toBe(parse_url($first, PHP_URL_PATH));
});

it('reloads the dashboard diagram when the period changes on a url with a query string', function () {
    $page = visitAdminPage('/admin/dashboard/?' . QUERY_URLS_QUERY, '#order_orders_period');

    // ajaxBlock answers with an empty body when it gets no block param, which pre-fix is
    // exactly what happened: "block/tab_orders/" went into the last query param's value.
    $page->select('#order_orders_period', '1y')->wait(2);

    expect(trim((string) $page->page()->locator('#diagram_tab_orders_content')->innerHTML()))->not->toBe('');
});
