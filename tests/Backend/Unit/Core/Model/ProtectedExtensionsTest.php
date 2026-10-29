<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
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

    it('keeps the minimum list when a saved value leaves extensions out', function (string $saved, string $extra) {
        Mage::app()->getStore()->setConfig(Mage_Core_Helper_Data::XML_PATH_PROTECTED_FILE_EXTENSIONS, $saved);

        $extensions = Mage::helper('core')->getProtectedFileExtensions();

        expect($extensions)->toBe(array_values(array_unique($extensions)))
            ->and($extensions)->toContain($extra)
            ->and($extensions)->not->toContain('');
        foreach (Mage_Core_Helper_Data::PROTECTED_FILE_EXTENSIONS_MINIMUM as $ext) {
            expect($extensions)->toContain($ext);
        }
        foreach ($extensions as $ext) {
            expect($ext)->toBe(strtolower(trim($ext)));
        }
    })->with([
        'saved list from an older release' => ['php,php3,php4,php5,htaccess,jsp,pl,py,asp,sh,cgi,htm,html,phtml,shtml', 'html'],
        'spaces and upper case' => [' PHP , Phtml ,EXE', 'exe'],
        'empty value' => ['', 'php'],
    ]);
});

describe('available path validator', function () {
    it('refuses a protected inner extension in any letter case', function (string $path) {
        $validator = Mage::getModel('core/file_validator_availablePath');
        $validator->setPaths(['available' => ['xml' => '/sitemap/*.xml']]);

        expect($validator->isValid($path))->toBeFalse();
    })->with([
        'lower case' => ['/sitemap/sitemap.php.xml'],
        'upper case' => ['/sitemap/sitemap.PHP.xml'],
        'mixed case phar' => ['/sitemap/sitemap.Phar.xml'],
    ]);

    it('accepts an available path', function () {
        $validator = Mage::getModel('core/file_validator_availablePath');
        $validator->setPaths(['available' => ['xml' => '/sitemap/*.xml']]);

        expect($validator->isValid('/sitemap/sitemap.xml'))->toBeTrue();
    });

    it('works when the protected extensions are saved as a string', function () {
        Mage::app()->getStore()->setConfig(Mage_Core_Helper_Data::XML_PATH_PROTECTED_FILE_EXTENSIONS, 'php,html');
        $validator = Mage::getModel('core/file_validator_availablePath');
        $validator->setPaths(['available' => ['xml' => '/sitemap/*.xml']]);

        expect($validator->isValid('/sitemap/sitemap.HTML.xml'))->toBeFalse()
            ->and($validator->isValid('/sitemap/sitemap.xml'))->toBeTrue();
    });
});
