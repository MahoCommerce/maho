<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

/**
 * Guard for block types whose class file name differs only in case.
 *
 * uc_words() changes only the first letter of each part of the type, so
 * log/dashboard_toppages becomes Mage_Log_Block_Dashboard_Toppages. The PSR-0
 * autoloader then looks for Toppages.php, which a case-sensitive filesystem does
 * not find next to TopPages.php. The test compares directory entries instead of
 * calling file_exists(), so it also fails on a case-insensitive filesystem.
 */

uses(Tests\MahoBackendTestCase::class);

it('finds the class file of every layout block type with the same case', function () {
    $types = [];

    $designFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(Mage::getBaseDir('design'), FilesystemIterator::SKIP_DOTS),
    );
    foreach ($designFiles as $file) {
        if ($file->getExtension() !== 'xml' || !str_contains($file->getPathname(), '/layout/')) {
            continue;
        }
        foreach (simplexml_load_file($file->getPathname())->xpath('//block[@type]') as $block) {
            $types[(string) $block['type']] = $file->getPathname();
        }
    }

    $codePools = [
        Mage::getBaseDir('code') . '/local',
        Mage::getBaseDir('code') . '/community',
        Mage::getBaseDir('code') . '/core',
        Mage::getBaseDir('lib'),
    ];
    $entries = [];
    $findIgnoringCase = function (string $dir, string $relativePath) use (&$entries): ?string {
        foreach (explode('/', $relativePath) as $part) {
            $entries[$dir] ??= is_dir($dir) ? scandir($dir) : [];
            $match = in_array($part, $entries[$dir], true) ? $part : null;
            foreach ($match === null ? $entries[$dir] : [] as $entry) {
                if (strcasecmp($entry, $part) === 0) {
                    $match = $entry;
                    break;
                }
            }
            if ($match === null) {
                return null;
            }
            $dir .= '/' . $match;
        }
        return $dir;
    };

    $mismatches = [];
    foreach ($types as $type => $source) {
        $class = Mage::getConfig()->getBlockClassName($type);
        $relativePath = strtr($class, ['_' => '/', '\\' => '/']) . '.php';
        // A type with no class file in any case is a different bug, so it is not checked here.
        foreach ($codePools as $codePool) {
            $file = $findIgnoringCase($codePool, $relativePath);
            if ($file === null) {
                continue;
            }
            if ($file !== "$codePool/$relativePath") {
                $mismatches[] = sprintf(
                    '%s in %s resolves to %s, but the file is %s',
                    $type,
                    Maho::toRelativePath($source),
                    $class,
                    Maho::toRelativePath($file),
                );
            }
            break;
        }
    }

    expect($mismatches)->toBe([]);
});
