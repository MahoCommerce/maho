<?php

/**
 * Gives the model the user guide of this store: the pages of the core and of the enabled modules.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Maho\Docs\Library;
use Maho\Docs\Page;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * The pages come from the files of this installation, so a store reads the guide of its own
 * release and of its own modules. The tool exists only when at least one page is installed.
 */
final class DocsTool
{
    public const NAME = 'docs_read';
    public const TITLE = 'Read the user guide';
    public const AUDIENCES = [Library::AUDIENCE_USER, Library::AUDIENCE_SYSADMIN];

    public function __construct(private ?Library $library = null) {}

    public function hasPages(): bool
    {
        return $this->pages() !== [];
    }

    public function tool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'read'),
            self::NAME,
            'The user guide of this store, for the people who work in the admin and run the server: how a feature works, the order in which the store picks a value, and the steps in the admin. Before you answer a question about how a feature works, call it without a page: it returns the pages of each module, with a description of each. Then read the page of the feature, and answer from it.',
            [
                'type' => 'object',
                'properties' => [
                    'page' => [
                        'type' => 'string',
                        'description' => 'The page to read, as the list gives it. Omit it for the list of pages.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            ['title' => self::TITLE, 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function read(array $arguments): array
    {
        $id = trim((string) ($arguments['page'] ?? ''));
        if ($id === '') {
            return ['ok' => true, 'text' => $this->index()];
        }
        $page = $this->library()->page($id, self::AUDIENCES);
        if ($page === null) {
            return ['ok' => false, 'text' => sprintf("No page \"%s\". The pages are:\n%s", $id, $this->index())];
        }

        return ['ok' => true, 'text' => sprintf("Page %s, from %s:\n\n%s", $page->id, $page->source->name, $page->body())];
    }

    /**
     * The pages grouped by module, in the order of the sources. "core" holds the pages that no
     * single module owns.
     */
    private function index(): string
    {
        $groups = [];
        foreach ($this->pages() as $page) {
            $groups["{$page->source->name} ({$page->source->audience})"][] = "  - {$page->id}: {$page->title}"
                . ($page->description !== '' ? ". {$page->description}" : '');
        }

        return implode("\n", array_map(
            static fn(string $group, array $lines): string => "- $group\n" . implode("\n", $lines),
            array_keys($groups),
            $groups,
        ));
    }

    /**
     * @return list<Page>
     */
    private function pages(): array
    {
        return $this->library()->pages(self::AUDIENCES);
    }

    private function library(): Library
    {
        return $this->library ??= new Library();
    }
}
