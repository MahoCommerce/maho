<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

/**
 * In-memory store of open documents, synced via LSP textDocument notifications.
 */
class Maho_Intelligence_Model_Lsp_DocumentStore
{
    /** @var array<string, string> URI → document text */
    private array $documents = [];

    public function open(string $uri, string $text): void
    {
        $this->documents[$uri] = $text;
    }

    public function change(string $uri, string $text): void
    {
        $this->documents[$uri] = $text;
    }

    public function close(string $uri): void
    {
        unset($this->documents[$uri]);
    }

    public function get(string $uri): ?string
    {
        return $this->documents[$uri] ?? null;
    }

    public function has(string $uri): bool
    {
        return isset($this->documents[$uri]);
    }
}
