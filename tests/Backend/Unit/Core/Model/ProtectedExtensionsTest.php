<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('protected upload extensions', function () {
    it('lists every PHP executable extension in the default configuration', function () {
        $extensions = Mage::helper('core')->getProtectedFileExtensions();
        $extensions = is_array($extensions) ? $extensions : explode(',', (string) $extensions);
        $extensions = array_map(fn($ext) => strtolower(trim((string) $ext)), $extensions);

        foreach (['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'phps', 'phtml', 'pht', 'htaccess'] as $ext) {
            expect($extensions)->toContain($ext);
        }
    });

    it('rejects the executable extensions in any letter case', function () {
        $validator = new Mage_Core_Model_File_Validator_NotProtectedExtension();
        foreach (['php8', 'PHP8', 'phar', 'Phar', 'phps', ' phps '] as $ext) {
            expect($validator->isValid($ext))->toBeFalse();
        }
        expect($validator->isValid('png'))->toBeTrue();
    });
});
