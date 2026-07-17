<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

interface Maho_Ai_Model_Platform_EmbedProviderInterface
{
    /**
     * Generate embeddings for one or more input strings.
     *
     * @param string|string[] $input  Single string or array of strings
     * @param array<string, mixed> $options  Supports: dimensions (int), model (string)
     * @return float[][]  One float array per input string
     */
    public function embed(string|array $input, array $options = []): array;

    /**
     * Get token usage from the last embed() call.
     *
     * @return array{input: int}
     */
    public function getLastEmbedTokenUsage(): array;

    /**
     * Get the platform code (e.g. 'openai', 'google')
     */
    public function getEmbedPlatformCode(): string;

    /**
     * Get the model used in the last embed() call
     */
    public function getLastEmbedModel(): string;
}
