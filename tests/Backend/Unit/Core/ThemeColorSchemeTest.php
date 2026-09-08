<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const MAHO_SKIN_DIR = __DIR__ . '/../../../../public/skin/frontend/base';

function industryThemeFiles(): array
{
    $files = [];
    foreach (glob(MAHO_SKIN_DIR . '/*/css/theme.css') ?: [] as $file) {
        $theme = basename(dirname($file, 2));
        if ($theme !== 'default') {
            $files[$theme] = $file;
        }
    }
    return $files;
}

/** The minifier drops the quotes around the attribute value. */
function darkRootSelectorPattern(): string
{
    return str_replace('"', '"?', preg_quote(Mage_Core_Model_Design_Tokens::DARK_ROOT_SELECTOR, '/'));
}

function compiledDarkDeclarations(): array
{
    $css = file_get_contents(MAHO_SKIN_DIR . '/default/css/styles.css');
    preg_match('/@media \(prefers-color-scheme:\s*dark\)\s*\{\s*' . darkRootSelectorPattern() . '\s*\{([^}]*)\}/', $css, $m);
    $names = [];
    foreach (explode(';', $m[1] ?? '') as $declaration) {
        $name = trim(explode(':', $declaration, 2)[0]);
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return $names;
}

function darkBlock(string $css): ?string
{
    if (!preg_match('/@media \(prefers-color-scheme:\s*dark\)\s*\{\s*(:root|' . darkRootSelectorPattern() . ')\s*\{([^}]*)\}/', $css, $m)) {
        return null;
    }
    return $m[2];
}

it('finds the industry themes and the compiled dark block', function () {
    expect(industryThemeFiles())->toHaveCount(10)
        ->and(compiledDarkDeclarations())->toContain('color-scheme', '--color-base-100', '--color-info');
});

it('pins the color scheme of every industry theme in dark mode', function () {
    foreach (industryThemeFiles() as $theme => $file) {
        $block = darkBlock(file_get_contents($file));
        expect($block)->not->toBeNull("$theme has no prefers-color-scheme: dark block")
            ->and((bool) preg_match('/color-scheme:\s*(dark|light)\s*;/', (string) $block))->toBeTrue("$theme does not pin color-scheme in dark mode");
    }
});

it('hangs every dark rule of a dark theme on the color scheme attribute', function () {
    $gate = 'data-color-scheme="light"';
    foreach (industryThemeFiles() as $theme => $file) {
        $css = file_get_contents($file);
        $block = darkBlock($css);
        if ($block === null || preg_match('/color-scheme:\s*light\s*;/', $block)) {
            continue;
        }
        $start = strpos($css, '@media (prefers-color-scheme: dark)');
        $media = substr($css, $start, strpos($css, "\n}\n", $start) - $start);
        preg_match_all('/^    ([^ }\/*][^\n]*)[{,]$/m', $media, $selectors);
        expect($selectors[1])->not->toBeEmpty();
        foreach ($selectors[1] as $selector) {
            expect(str_contains($selector, $gate))->toBeTrue("$theme dark rule '$selector' ignores the Dark Mode setting");
        }
    }
});

it('overrides every compiled dark variable in a light-only theme', function () {
    foreach (industryThemeFiles() as $theme => $file) {
        $css = file_get_contents($file);
        $block = darkBlock($css);
        if ($block === null || !preg_match('/color-scheme:\s*light\s*;/', $block)) {
            continue;
        }
        preg_match('/^:root\s*\{(.*?)^\}/ms', $css, $m);
        foreach (compiledDarkDeclarations() as $name) {
            if ($name === 'color-scheme') {
                continue;
            }
            expect(str_contains($m[1] ?? '', "$name:"))->toBeTrue("$theme leaves $name to the compiled dark palette");
        }
    }
});
