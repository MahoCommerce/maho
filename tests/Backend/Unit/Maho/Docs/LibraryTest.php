<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Docs\Library;
use Maho\Docs\Source;

uses(Tests\MahoBackendTestCase::class);

/**
 * A docs folder in the temp directory with $files, as paths from the folder.
 *
 * @param array<string, string> $files
 */
function docsLibraryFolder(array $files): string
{
    $dir = sys_get_temp_dir() . '/maho_docs_' . uniqid();
    foreach ($files as $path => $content) {
        @mkdir(dirname("$dir/$path"), 0777, true);
        file_put_contents("$dir/$path", $content);
    }

    return $dir;
}

it('lists the pages of a folder with their title and description, in path order', function (): void {
    $dir = docsLibraryFolder([
        'swatches.md' => "---\ndescription: Show colors instead of dropdowns.\n---\n\n# Swatches <span class=\"version-badge\">v26.5+</span>\n\nText.",
        'advanced/labels.md' => "# Image labels\n\nText.",
        'notes.md' => 'No title here.',
        'image.png' => 'binary',
        '.hidden.md' => '# Hidden',
    ]);
    $pages = new Library([new Source('Acme_Swatches', Library::AUDIENCE_USER, $dir)])->pages([Library::AUDIENCE_USER]);

    expect(array_map(fn($page) => $page->id, $pages))->toBe(['Acme_Swatches/advanced/labels.md', 'Acme_Swatches/notes.md', 'Acme_Swatches/swatches.md']);
    expect($pages[2]->title)->toBe('Swatches');
    expect($pages[2]->description)->toBe('Show colors instead of dropdowns.');
    expect($pages[1]->title)->toBe('notes');
    expect($pages[0]->description)->toBe('');
});

it('returns only the pages of the audiences asked for', function (): void {
    $library = new Library([
        new Source('core', Library::AUDIENCE_USER, docsLibraryFolder(['a.md' => '# User page'])),
        new Source('core', Library::AUDIENCE_DEVELOPER, docsLibraryFolder(['b.md' => '# Developer page'])),
    ]);

    expect(array_map(fn($page) => $page->title, $library->pages([Library::AUDIENCE_USER])))->toBe(['User page']);
    expect($library->page('core/b.md', [Library::AUDIENCE_USER]))->toBeNull();
    expect($library->page('core/b.md', [Library::AUDIENCE_DEVELOPER])?->title)->toBe('Developer page');
});

it('gives the body of a page without its front matter, cut to the limit', function (): void {
    $dir = docsLibraryFolder(['a.md' => "---\ndescription: Short.\n---\n# Title\n\n" . str_repeat('x', 100)]);
    $page = new Library([new Source('core', Library::AUDIENCE_USER, $dir)])->page('core/a.md', [Library::AUDIENCE_USER]);

    expect($page?->body())->toStartWith('# Title')->not->toContain('description:');
    expect($page?->body(20))->toEndWith('[The page is cut here.]');
});

it('finds no page outside the index', function (): void {
    $library = new Library([new Source('core', Library::AUDIENCE_USER, docsLibraryFolder(['a.md' => '# A']))]);

    expect($library->page('core/../a.md', [Library::AUDIENCE_USER]))->toBeNull();
    expect($library->page('core/b.md', [Library::AUDIENCE_USER]))->toBeNull();
});

it('reads the folders that the enabled modules declare in config.xml', function (): void {
    $page = new Library()->page('Mage_ConfigurableSwatches/configurable-swatches.md', [Library::AUDIENCE_USER]);

    expect($page?->title)->toBe('Configurable Swatches');
    expect($page?->body())->toContain('-swatch');
});
