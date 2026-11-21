<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('returns a chmod command only when other users can access local.xml', function (): void {
    $file = Mage::getBaseDir('etc') . DS . 'local.xml';
    $mode = fileperms($file) & 0777;
    $helper = Mage::helper('install');

    try {
        chmod($file, 0640);
        expect($helper->getLocalConfigChmodCommand())->toBeNull();

        chmod($file, 0644);
        expect($helper->getLocalConfigChmodCommand())->toBe('chmod o-rwx ' . escapeshellarg($file));
    } finally {
        chmod($file, $mode);
    }
});
