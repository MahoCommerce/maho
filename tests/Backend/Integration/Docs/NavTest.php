<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The .md files that mahocommerce.com puts in $folder: the files of docs/$folder, and the files of
 * each module folder whose audience goes to $folder.
 *
 * @return list<string>
 */
function docsNavPages(string $folder): array
{
    $siteFolders = ['user' => 'user-guide', 'developer' => 'developer', 'sysadmin' => 'hosting'];
    $dirs = [dirname(__DIR__, 4) . "/docs/$folder"];
    foreach (Mage::getConfig()->getNode('global/docs')->children() as $module => $audiences) {
        foreach ($audiences->children() as $audience => $path) {
            if (($siteFolders[$audience] ?? null) === $folder) {
                $dirs[] = Maho::findFile(Mage::getConfig()->getModuleDir('', $module)) . "/$path";
            }
        }
    }
    $pages = [];
    foreach ($dirs as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'md') {
                $pages[] = substr($file->getPathname(), strlen($dir) + 1);
            }
        }
    }
    sort($pages);

    return $pages;
}

it('lists each page of a docs folder once in its .nav.yml', function (string $folder): void {
    preg_match_all('/^\s*-\s+(?:.*:\s+)?(\S+\.md)\s*$/m', (string) file_get_contents(dirname(__DIR__, 4) . "/docs/$folder/.nav.yml"), $matches);
    $listed = $matches[1];
    sort($listed);

    expect($listed)->toBe(docsNavPages($folder));
})->with(['user-guide', 'developer', 'api', 'hosting']);
