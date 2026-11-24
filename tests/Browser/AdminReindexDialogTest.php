<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const REINDEX_ADMIN_USER = 'reindex-dialog-admin';
const REINDEX_ADMIN_PASSWORD = 'Password123!';

beforeEach(function () {
    createBrowserAdmin(REINDEX_ADMIN_USER, REINDEX_ADMIN_PASSWORD);
});

// Per test rather than once at the end: afterAll runs after the base tearDown has called
// Mage::reset(), so there is no app left to clean up through
afterEach(function () {
    deleteBrowserAdmin(REINDEX_ADMIN_USER);
});

function loginToIndexManagement(): object
{
    return adminLoginAndVisit(
        REINDEX_ADMIN_USER,
        REINDEX_ADMIN_PASSWORD,
        '/admin/process/list',
        '#indexer_processes_grid_table:visible',
    );
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
