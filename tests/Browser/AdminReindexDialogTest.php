<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoFrontendTestCase;

uses(MahoFrontendTestCase::class)->group('browser');

/**
 * Secret keys in admin urls are turned off for the run so the test can address
 * adminhtml/process/list directly; that also puts the reindex endpoints on the forced form key
 * path, which is the protection this screen relies on in that configuration.
 */

const REINDEX_ADMIN_USER = 'reindex-dialog-admin';
const REINDEX_ADMIN_PASSWORD = 'Password123!';
const REINDEX_SECRET_KEY_PATH = 'admin/security/use_form_key';

afterAll(function () {
    // Drop the override rather than pin a value; the test never ran if Playwright is missing
    Mage::getModel('core/config')->deleteConfig(REINDEX_SECRET_KEY_PATH);
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
    MahoServer::stop();
});

beforeEach(function () {
    if (!browserTestsReady()) {
        test()->markTestSkipped('Playwright is not installed');
    }

    Mage::getModel('core/config')->saveConfig(REINDEX_SECRET_KEY_PATH, 0);
    Mage::app()->cleanCache();

    createReindexAdmin();
    MahoServer::start();
});

afterEach(fn() => deleteReindexAdmin());

function deleteReindexAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(REINDEX_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** A fresh admin with the full-access role, so the password is known and ACL never gets in the way. */
function createReindexAdmin(): void
{
    deleteReindexAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(REINDEX_ADMIN_USER)
        ->setFirstname('Reindex')
        ->setLastname('Dialog')
        ->setEmail('reindex-dialog@example.test')
        ->setPassword(REINDEX_ADMIN_PASSWORD)
        ->setIsActive(1)
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

function loginToIndexManagement(): object
{
    return visit(MahoServer::baseUrl() . '/admin')
        ->fill('#username', REINDEX_ADMIN_USER)
        ->fill('#login', REINDEX_ADMIN_PASSWORD)
        ->press('Login')
        ->navigate(MahoServer::baseUrl() . '/admin/process/list')
        ->waitForText('Index Management');
}

it('reports a single index through the progress dialog', function () {
    // By selector, not by text: the mass-action select carries an option with the same caption,
    // and every grid row carries the same link, so the first one has to be named explicitly
    $page = loginToIndexManagement()->click('a[onclick^="indexReindexProcess"] >> nth=0');

    // waitForText() is a one-shot read, and the dialog only reports a result after the first poll
    $result = $page->page()->locator('.maho-job-result');
    $result->waitFor(['state' => 'visible', 'timeout' => 300_000]);

    expect(trim((string) $result->textContent()))->toBe('Reindex complete')
        ->and($page->url())->toContain('process/list');
})->skip(
    fn() => count(Mage::getModel('index/runner')->buildQueue()) === 0,
    'no visible indexes to reindex',
);

it('offers a reindex all button', function () {
    loginToIndexManagement()->assertSee('Reindex All');
});
