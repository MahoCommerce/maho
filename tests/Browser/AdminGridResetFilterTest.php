<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Regression for a grid that reloads the whole page after "Reset Filter".
 *
 * The admin url path holds key/value pairs. The grid wrote an empty value as an empty segment
 * ("filter//"), and a web server that merges slashes removes that segment, so each later key
 * takes the value of the next one. form_key then got the value "form_key", _current copied it
 * into the mass action url, and the mass action failed with "Invalid Form Key".
 *
 * The Notifications grid reloads the whole page, keeps its filter in the session and has a
 * mass action url with _current. The filter row of this grid is hidden, so the test calls
 * the grid script directly.
 */

const GRID_RESET_ADMIN_USERNAME = 'pest_grid_reset';
const GRID_RESET_ADMIN_PASSWORD = 'PestGridReset2026!';
const GRID_RESET_ALPHA = 'Pest Grid Reset Alpha';
const GRID_RESET_BETA = 'Pest Grid Reset Beta';

beforeEach(function () {
    deleteGridResetFixtures();
    createGridResetFixtures();
});

afterEach(function () {
    deleteGridResetFixtures();
});

function deleteGridResetFixtures(): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('adminnotification/inbox'),
        ['title IN (?)' => [GRID_RESET_ALPHA, GRID_RESET_BETA]],
    );

    $user = Mage::getModel('admin/user')->loadByUsername(GRID_RESET_ADMIN_USERNAME);
    if ($user->getId()) {
        $user->delete();
    }
}

function createGridResetFixtures(): void
{
    $resource = Mage::getSingleton('core/resource');
    foreach ([GRID_RESET_ALPHA, GRID_RESET_BETA] as $title) {
        $resource->getConnection('core_write')->insert($resource->getTableName('adminnotification/inbox'), [
            'severity' => Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE,
            'date_added' => Mage::app()->getLocale()->formatDateForDb('now'),
            'title' => $title,
            'description' => $title,
        ]);
    }

    $user = Mage::getModel('admin/user')
        ->setUsername(GRID_RESET_ADMIN_USERNAME)
        ->setFirstname('Grid')
        ->setLastname('Reset')
        ->setEmail('grid-reset@example.test')
        ->setPassword(GRID_RESET_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

/** Run $script on the grid object, then wait for the page that it opens. */
function runOnNotificationGrid(object $page, string $script): object
{
    $page->script("(() => { const grid = window.notificationGridJsObject; {$script} })()");
    $page->wait(2);

    return waitForPageLoad($page, '#notificationGrid');
}

it('writes no empty path segment and no form key when the filter is reset', function () {
    $page = adminLoginAndVisit(GRID_RESET_ADMIN_USERNAME, GRID_RESET_ADMIN_PASSWORD, '/admin/notification/index', '#notificationGrid');

    runOnNotificationGrid($page, sprintf(
        "grid.addVarToUrl(grid.pageVar, 1); grid.reload(grid.addVarToUrl(grid.filterVar, btoa('title=%s')));",
        rawurlencode(GRID_RESET_ALPHA),
    ))->assertSee(GRID_RESET_ALPHA)
        ->assertDontSee(GRID_RESET_BETA);

    $url = runOnNotificationGrid($page, 'grid.resetFilter();')->url();

    // The grid keeps its filter in the session, so only a filter param that is present and empty clears it
    expect(parse_url($url, PHP_URL_PATH))->not->toContain('//')
        ->and($url)->not->toContain('form_key');
    $page->assertSee(GRID_RESET_ALPHA)
        ->assertSee(GRID_RESET_BETA);
});
