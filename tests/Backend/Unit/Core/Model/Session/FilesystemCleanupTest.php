<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function cleanupSavePath(): string
{
    return Mage::getBaseDir('var') . DS . 'session-cleanup-test';
}

function seedSessionFile(string $name, int $ageSeconds, string $subDir = ''): string
{
    $dir = cleanupSavePath() . ($subDir === '' ? '' : DS . $subDir);
    @mkdir($dir, 0777, true);
    $path = $dir . DS . $name;
    file_put_contents($path, 'x');
    touch($path, time() - $ageSeconds);

    return $path;
}

function runSessionCleanup(): void
{
    (new ReflectionMethod(Mage_Core_Model_Session::class, '_cleanFileSystemSessions'))
        ->invoke(Mage::getSingleton('core/session'));
}

beforeEach(function () {
    // Never point this at the real var/session: the method unlinks every sess_* file it finds
    @mkdir(cleanupSavePath(), 0777, true);

    Mage::getConfig()->setNode('global/session_save', 'files');
    Mage::getConfig()->setNode('global/session_save_path', cleanupSavePath());

    $store = Mage::app()->getStore();
    $store->setConfig('admin/security/session_cookie_lifetime', '10800');
    $store->setConfig('web/cookie/cookie_lifetime', '604800');
    $store->setConfig('web/cookie/remember_enabled', '1');
    $store->setConfig('web/cookie/remember_cookie_lifetime', '2592000');
});

afterEach(function () {
    // Not glob(): it skips the dotfile one of the cases seeds, leaving the directory undeletable
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(cleanupSavePath(), FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir(cleanupSavePath());
});

it('keeps a session file that was touched recently', function () {
    $path = seedSessionFile('sess_recent', 60);

    runSessionCleanup();

    expect(file_exists($path))->toBeTrue();
});

it('deletes a session file older than every configured lifetime', function () {
    $path = seedSessionFile('sess_ancient', 2592000 + 86400);

    runSessionCleanup();

    expect(file_exists($path))->toBeFalse();
});

it('keeps a Remember Me session past the plain cookie lifetime', function () {
    // Regression: the threshold omitted remember_cookie_lifetime, so the file went at day 7
    $path = seedSessionFile('sess_remembered', 14 * 86400);

    runSessionCleanup();

    expect(file_exists($path))->toBeTrue();
});

it('does not retain for Remember Me when the feature is disabled', function () {
    Mage::app()->getStore()->setConfig('web/cookie/remember_enabled', '0');
    foreach (Mage::app()->getStores() as $store) {
        $store->setConfig('web/cookie/remember_enabled', '0');
    }

    $path = seedSessionFile('sess_not_remembered', 14 * 86400);

    runSessionCleanup();

    expect(file_exists($path))->toBeFalse();
});

it('respects a store view that configures a longer lifetime than the current scope', function () {
    // The reaper runs in one scope, but the lifetimes are per store view. getStores() hands back
    // the very object getStore() returns, so the current store has to be skipped or the short
    // value below is overwritten and the case proves nothing.
    $current = Mage::app()->getStore();
    $others = array_filter(
        Mage::app()->getStores(),
        fn(Mage_Core_Model_Store $store): bool => $store->getId() !== $current->getId(),
    );

    if ($others === []) {
        $this->markTestSkipped('Needs a second store view');
    }

    $current->setConfig('web/cookie/cookie_lifetime', '3600');
    $current->setConfig('web/cookie/remember_cookie_lifetime', '86400');
    foreach ($others as $store) {
        $store->setConfig('web/cookie/remember_enabled', '1');
        $store->setConfig('web/cookie/remember_cookie_lifetime', '2592000');
    }

    $path = seedSessionFile('sess_other_store', 14 * 86400);

    runSessionCleanup();

    expect(file_exists($path))->toBeTrue();
});

it('leaves files that are not sessions alone', function () {
    $path = seedSessionFile('.gitignore', 2592000 + 86400);

    runSessionCleanup();

    expect(file_exists($path))->toBeTrue();
});

it('reaps a session name that keeps its records in a subdirectory', function () {
    $path = seedSessionFile('sess_admin', 2592000 + 86400, Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE);

    runSessionCleanup();

    expect(file_exists($path))->toBeFalse();
});

it('keeps a recent session inside a subdirectory', function () {
    $path = seedSessionFile('sess_admin_recent', 60, Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE);

    runSessionCleanup();

    expect(file_exists($path))->toBeTrue();
});
