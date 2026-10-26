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

    /** Above this count, the tool description stops listing the pages, and the model asks for the list. */
    private const MAX_LISTED = 80;

    public function __construct(private ?Library $library = null) {}

    public function hasPages(): bool
    {
        return $this->pages() !== [];
    }

    public function tool(): Tool
    {
        $pages = $this->pages();
        $list = count($pages) <= self::MAX_LISTED
            ? ' Pages: ' . implode('; ', array_map(static fn(Page $page): string => "{$page->id} ({$page->title})", $pages)) . '.'
            : '';

        return ToolDefinition::create(
            new ExecutionReference(self::class, 'read'),
            self::NAME,
            'The user guide of this store, for the people who work in the admin and run the server: how a feature works, the order in which the store picks a value, and the steps in the admin. Before you answer a question about how a feature works, read its page, and answer from it. Without a page, it returns the list of pages with a description of each.' . $list,
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

    private function index(): string
    {
        return implode("\n", array_map(
            static fn(Page $page): string => "- {$page->id}: {$page->title}" . ($page->description !== '' ? ". {$page->description}" : ''),
            $this->pages(),
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
