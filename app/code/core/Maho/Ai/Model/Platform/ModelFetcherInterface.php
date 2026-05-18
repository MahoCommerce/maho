<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
