<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\FrontendThemeCreate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the dev:frontend:theme:create scaffolder. The last test compiles
 * the generated entry, whose relative import fails silently when it is wrong.
 */
const THEME_TEST_PACKAGE = 'testscaffold';

function themeCreateTester(): CommandTester
{
    $command = new FrontendThemeCreate();
    $application = new Application();
    $application->addCommand($command);
    return new CommandTester($command);
}

function themeCreateCleanup(): void
{
    foreach (['app/design/frontend/' . THEME_TEST_PACKAGE, 'public/skin/frontend/' . THEME_TEST_PACKAGE] as $dir) {
        $path = MAHO_ROOT_DIR . '/' . $dir;
        if (!is_dir($path)) {
            continue;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}

function themeCreateSkin(string $file): string
{
    return MAHO_ROOT_DIR . '/public/skin/frontend/' . THEME_TEST_PACKAGE . "/default/$file";
}

beforeEach(fn() => themeCreateCleanup());
afterEach(fn() => themeCreateCleanup());

it('scaffolds a plain css/theme.css when the theme takes no build step', function () {
    $tester = themeCreateTester();
    $tester->execute([
        '--package' => THEME_TEST_PACKAGE,
        '--theme' => 'default',
        '--parent' => 'base/default',
        '--no-tailwind' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and(is_file(themeCreateSkin('css/theme.css')))->toBeTrue()
        ->and(is_dir(themeCreateSkin('src')))->toBeFalse()
        ->and(is_file(MAHO_ROOT_DIR . '/app/design/frontend/' . THEME_TEST_PACKAGE . '/default/etc/theme.xml'))->toBeTrue()
        ->and(file_get_contents(themeCreateSkin('css/theme.css')))->not->toContain('generated');
});

it('scaffolds a single src/tailwind.css that imports the whole engine', function () {
    $tester = themeCreateTester();
    $tester->execute([
        '--package' => THEME_TEST_PACKAGE,
        '--theme' => 'default',
        '--parent' => 'base/default',
        '--tailwind' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);

    $source = (string) file_get_contents(themeCreateSkin('src/tailwind.css'));
    expect($source)
        ->toContain('@import "../../../base/default/src/tailwind.css";')
        ->toContain('/*! css/styles.css is generated')
        // the parent entry carries all of it, so the child declares none of it
        ->not->toContain('@source')
        ->not->toContain('@reference')
        ->not->toContain('@plugin');

    // The output slot is styles.css, which leaves css/theme.css free for the
    // parent identity: the fallback serves only the first file it finds.
    expect(is_file(themeCreateSkin('css/theme.css')))->toBeFalse();
});

it('imports the identity of a parent theme that carries one, since a plain theme.css shadows it', function () {
    themeCreateTester()->execute([
        '--package' => THEME_TEST_PACKAGE,
        '--theme' => 'default',
        '--parent' => 'base/fashion',
        '--no-tailwind' => true,
    ]);

    expect(file_get_contents(themeCreateSkin('css/theme.css')))
        ->toContain("@import url('../../../base/fashion/css/theme.css');");
});

it('imports nothing for a base/default parent, whose theme.css declares nothing', function () {
    themeCreateTester()->execute([
        '--package' => THEME_TEST_PACKAGE,
        '--theme' => 'default',
        '--parent' => 'base/default',
        '--no-tailwind' => true,
    ]);

    expect(file_get_contents(themeCreateSkin('css/theme.css')))->not->toContain('@import');
});

it('compiles the scaffolded entry into a bundle that replaces the shipped one', function () {
    $binary = MAHO_ROOT_DIR . '/node_modules/.bin/tailwindcss';
    if (!is_file($binary)) {
        test()->markTestSkipped('the Tailwind toolchain is not installed');
    }

    themeCreateTester()->execute([
        '--package' => THEME_TEST_PACKAGE,
        '--theme' => 'default',
        '--parent' => 'base/default',
        '--tailwind' => true,
    ]);

    $design = MAHO_ROOT_DIR . '/app/design/frontend/' . THEME_TEST_PACKAGE . '/default/template';
    mkdir($design, 0755, true);
    file_put_contents("$design/probe.phtml", '<div class="rotate-47">x</div>');

    $entry = themeCreateSkin('src/tailwind.css');
    file_put_contents($entry, ":root { --probe-token: 3px; }\n.probe-promo { @apply rounded-box; }\n", FILE_APPEND);

    $process = new Process([$binary, '-i', $entry, '-o', themeCreateSkin('css/styles.css'), '--minify'], MAHO_ROOT_DIR);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    $css = (string) file_get_contents(themeCreateSkin('css/styles.css'));
    expect($css)
        // the inherited @source reached this theme's own template, with no @source here
        ->toContain('.rotate-47')
        // the whole engine came with the import
        ->toContain('--color-primary')
        ->toContain('.btn')
        ->toContain('--probe-token')
        ->toContain('.probe-promo')
        ->toContain('css/styles.css is generated');

    // It replaces the shipped bundle, so it must be of the same order of size
    expect(strlen($css))->toBeGreaterThan(300000);
});
