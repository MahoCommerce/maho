<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface Maho_Ai_Model_Platform_ProviderInterface
{
    /**
     * Send a completion request
     *
     * @param array<array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function complete(array $messages, array $options = []): string;

    /**
     * Get token usage from last complete() call
     *
     * @return array{input: int, output: int}
     */
    public function getLastTokenUsage(): array;

    /**
     * Get the platform code (e.g. 'openai', 'anthropic')
     */
    public function getPlatformCode(): string;

    /**
     * Get the model that was used in last complete() call
     */
    public function getLastModel(): string;
}
