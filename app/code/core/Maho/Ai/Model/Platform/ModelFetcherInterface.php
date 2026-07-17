<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

/**
 * Contract for community provider model fetchers.
 *
 * Implementations are declared via <model_fetcher_class> in provider config
 * and are called by the core ModelFetcher when a community provider is selected.
 */
interface Maho_Ai_Model_Platform_ModelFetcherInterface
{
    /**
     * Fetch available models for a given capability.
     *
     * @param string $capability  One of: chat, embed, image, video
     * @return list<array{value: string, label: string}>
     */
    public function fetchModels(string $capability = 'chat'): array;
}
