<?php

/**
 * Gives the model the HTML the content editor writes, as the panel generated it from the editor.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * The guide is not a file in the repository: the editor script builds it in the browser from
 * its own layout presets, so it never goes stale. The panel sends it once per version of the
 * script, the helper keeps it in the cache, and the tool exists only while a guide is known.
 */
final class ContentGuideTool
{
    public const NAME = 'admin_content_guide';
    public const TITLE = 'Content editor guide';

    private ?string $guide = null;

    /** Keep the guide that a request carried, only when no guide is known for this script version. */
    public function store(string $guide): void
    {
        $guide = trim($guide);
        if ($guide !== '' && $this->guide() === '') {
            \Mage::helper('ai')->saveEditorGuide($guide);
            $this->guide = $guide;
        }
    }

    public function guide(): string
    {
        return $this->guide ??= \Mage::helper('ai')->editorGuide();
    }

    public function hasGuide(): bool
    {
        return $this->guide() !== '';
    }

    public function tool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'read'),
            self::NAME,
            'The HTML the content editor of this admin writes for its layouts: columns, bento grids, accordion, tabs, slideshow, table, images, widgets and variables. Read it before you write page, block or template content that has a layout or an image, and use the same HTML so the administrator can keep editing the layout in the editor.',
            ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false],
            ['title' => self::TITLE, 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @return array{ok: bool, text: string}
     */
    public function read(): array
    {
        return $this->hasGuide()
            ? ['ok' => true, 'text' => $this->guide()]
            : ['ok' => false, 'text' => 'No editor guide is available yet.'];
    }
}
