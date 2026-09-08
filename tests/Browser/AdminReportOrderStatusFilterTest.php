<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Regression for a sales report that keeps only the first of the selected order statuses.
 *
 * The filter form encodes itself into the "filter" segment of the url. A multiselect holds
 * more than one selected option, so the encoder must read all of them, and the form must show
 * all of them again on the page the report comes back on.
 */

const REPORT_STATUS_ADMIN_USER = 'report-status-admin';
const REPORT_STATUS_ADMIN_PASSWORD = 'Password123!';

beforeEach(function () {
    createReportStatusAdmin();
});

afterEach(function () {
    deleteReportStatusAdmin();
});

function deleteReportStatusAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(REPORT_STATUS_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** A fresh admin with the full-access role, so the password is known and ACL never gets in the way. */
function createReportStatusAdmin(): void
{
    deleteReportStatusAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(REPORT_STATUS_ADMIN_USER)
        ->setFirstname('Report')
        ->setLastname('Status')
        ->setEmail('report-status@example.test')
        ->setPassword(REPORT_STATUS_ADMIN_PASSWORD)
        ->setIsActive(1)
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

/** Decode the filter the report was run with out of the url the report landed on. */
function decodeReportFilter(string $url): array
{
    preg_match('#/filter/([^/]+)#', (string) parse_url($url, PHP_URL_PATH), $matches);
    parse_str((string) base64_decode(rawurldecode($matches[1] ?? '')), $filter);

    return $filter;
}

/** The values of the options that are selected in a select element, in document order. */
function selectedOptionValues(object $page, string $selector): array
{
    $values = $page->script(
        "Array.from(document.querySelectorAll('{$selector} option:checked')).map(option => option.value).join(',')",
    );

    return $values === '' ? [] : explode(',', (string) $values);
}

it('runs a sales report with every selected order status', function () {
    $page = adminLoginAndVisit(
        REPORT_STATUS_ADMIN_USER,
        REPORT_STATUS_ADMIN_PASSWORD,
        '/admin/report_sales/sales',
        '#filter_form',
    );

    $page->fill('#sales_report_from', '2026-07-01')
        ->fill('#sales_report_to', '2026-07-31')
        ->select('#sales_report_show_order_statuses', '1')
        ->select('#sales_report_order_statuses', ['processing', 'complete'])
        ->click('Show Report')
        ->wait(2);

    waitForPageLoad($page, '#filter_form');

    $ranWith = decodeReportFilter($page->url())['order_statuses'] ?? [];
    $stillSelected = selectedOptionValues($page, '#sales_report_order_statuses');
    sort($ranWith);
    sort($stillSelected);

    expect($ranWith)->toBe(['complete', 'processing'])
        ->and($stillSelected)->toBe(['complete', 'processing']);
});
