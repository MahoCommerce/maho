<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Maho\Ai\Api\Agent\AdminPageTool;
use Maho\Ai\Api\Agent\AttachmentTool;
use Maho\Ai\Api\Agent\BackgroundTaskTool;
use Maho\Ai\Api\Agent\ContentGuideTool;
use Maho\Ai\Api\Agent\DocsTool;
use Maho\Ai\Api\Agent\FormTool;
use Maho\Ai\Api\Agent\ImageTool;
use Maho\Ai\Api\Agent\McpToolbox;
use Maho\Ai\Api\Agent\MemoryTool;
use Maho\Ai\Api\Agent\NotifyTool;
use Maho\Docs\Library;
use Symfony\AI\Platform\Result\ToolCall;

uses(Tests\MahoBackendTestCase::class);

/** A toolbox for local tool names only: those never reach the MCP catalog. */
function localToolbox(): McpToolbox
{
    return new ReflectionClass(McpToolbox::class)->newInstanceWithoutConstructor();
}

it('asks the administrator before a local tool that costs money or runs the store tools', function (string $name): void {
    $toolbox = localToolbox();

    expect(McpToolbox::isLocal($name))->toBeTrue();
    expect($toolbox->isReadOnly($name))->toBeFalse();
    expect($toolbox->isDestructive($name))->toBeFalse();
    expect($toolbox->needsConfirmation(new ToolCall('call_1', $name, [])))->toBeTrue();
})->with([ImageTool::NAME, BackgroundTaskTool::NAME]);

it('runs every other local tool without asking, since it changes no store data', function (string $name): void {
    $toolbox = localToolbox();

    expect(McpToolbox::isLocal($name))->toBeTrue();
    expect($toolbox->isReadOnly($name))->toBeTrue();
    expect($toolbox->isDestructive($name))->toBeFalse();
    expect($toolbox->needsConfirmation(new ToolCall('call_1', $name, [])))->toBeFalse();
})->with([
    McpToolbox::ENABLE_NAME,
    ContentGuideTool::NAME,
    DocsTool::NAME,
    AttachmentTool::NAME,
    NotifyTool::NAME,
    AdminPageTool::NAME,
    AdminPageTool::FILL_NAME,
    ...MemoryTool::NAMES,
    ...FormTool::NAMES,
]);

it('gives a local tool the effect hints that the toolbox reads', function (): void {
    expect(new DocsTool(new Library([]))->tool()->getEffectHints())->toEqual(DocsTool::effectHints());
});

it('leaves a tool of the MCP catalog to the catalog', function (): void {
    expect(McpToolbox::isLocal('catalog_products_list'))->toBeFalse();
});
