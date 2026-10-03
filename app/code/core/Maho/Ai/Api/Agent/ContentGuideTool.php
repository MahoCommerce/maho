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
 * its own layout presets, so it never goes stale, and the panel sends it with each request.
 * The tool exists only when the request carried one.
 */
final class ContentGuideTool
{
    public const NAME = 'admin_content_guide';
    public const TITLE = 'Content editor guide';

    private string $guide = '';

    public function setGuide(string $guide): void
    {
        $this->guide = trim($guide);
    }

    public function hasGuide(): bool
    {
        return $this->guide !== '';
    }

    public function tool(): Tool
    {
        return new Tool(
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
            ? ['ok' => true, 'text' => $this->guide]
            : ['ok' => false, 'text' => 'No editor guide was sent with this request.'];
    }
}
