<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function minifyFixtureDir(string $name): string
{
    $dir = Mage::getBaseDir() . '/public/skin/frontend/base/default/css/minify-test-' . $name;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function minifyCleanup(): void
{
    foreach (['a', 'b'] as $name) {
        $dir = minifyFixtureDir($name);
        array_map(unlink(...), glob($dir . '/*') ?: []);
        rmdir($dir);
    }
    array_map(unlink(...), glob(Mage::getBaseDir() . '/public/media/mahominify/theme-*-*.css') ?: []);
}

afterEach(fn() => minifyCleanup());

it('caches two stylesheets with the same name and modification time under different names', function (): void {
    $mtime = time() - 100;
    foreach (['a' => 'body{color:red}', 'b' => 'body{color:blue}'] as $name => $css) {
        $file = minifyFixtureDir($name) . '/theme.css';
        file_put_contents($file, $css);
        touch($file, $mtime);
    }
    Mage::app()->getStore()->setConfig('dev/css/minify_enabled', '1');

    $helper = Mage::helper('core/minify');
    $first = $helper->minifyCss('skin/frontend/base/default/css/minify-test-a/theme.css');
    $second = $helper->minifyCss('skin/frontend/base/default/css/minify-test-b/theme.css');

    expect($first)->toContain('/media/mahominify/theme-');
    expect($first)->not->toBe($second);
    $path = fn(string $url): string => Mage::getBaseDir() . '/public/media/mahominify/' . basename($url);
    expect(file_get_contents($path($first)))->toContain('red');
    expect(file_get_contents($path($second)))->toContain('blue');
});
