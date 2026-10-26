<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Maho\Ai\Api\Agent\DocsTool;
use Maho\Docs\Library;
use Maho\Docs\Source;

uses(Tests\MahoBackendTestCase::class);

function docsToolLibrary(): Library
{
    $user = sys_get_temp_dir() . '/maho_docs_tool_' . uniqid();
    $developer = $user . '_dev';
    @mkdir($user, 0777, true);
    @mkdir($developer, 0777, true);
    file_put_contents("$user/swatches.md", "---\ndescription: Show colors instead of dropdowns.\n---\n# Swatches\n\nA gallery image labeled Red-swatch wins.");
    file_put_contents("$developer/observers.md", '# Observers');

    return new Library([
        new Source('Acme_Swatches', Library::AUDIENCE_USER, $user),
        new Source('core', Library::AUDIENCE_DEVELOPER, $developer),
    ]);
}

it('lists the pages for the admin in its description and in its index, without the developer pages', function (): void {
    $tool = new DocsTool(docsToolLibrary());

    expect($tool->hasPages())->toBeTrue();
    expect($tool->tool()->getDescription())->toContain('Acme_Swatches/swatches.md (Swatches)')->not->toContain('Observers');
    expect($tool->read([])['text'])->toBe('- Acme_Swatches/swatches.md: Swatches. Show colors instead of dropdowns.');
});

it('reads a page by the id that the index gives', function (): void {
    $outcome = new DocsTool(docsToolLibrary())->read(['page' => 'Acme_Swatches/swatches.md']);

    expect($outcome['ok'])->toBeTrue();
    expect($outcome['text'])->toContain('from Acme_Swatches', 'Red-swatch wins')->not->toContain('description:');
});

it('answers an unknown page with the list of pages', function (): void {
    $outcome = new DocsTool(docsToolLibrary())->read(['page' => 'core/observers.md']);

    expect($outcome['ok'])->toBeFalse();
    expect($outcome['text'])->toContain('No page "core/observers.md"', 'Acme_Swatches/swatches.md');
});

it('is a local tool with a label', function (): void {
    expect(Maho\Ai\Api\Agent\McpToolbox::isLocal(DocsTool::NAME))->toBeTrue();
    expect(Mage::helper('ai')->toolLabel(DocsTool::NAME))->toBe('Read the user guide');
});
