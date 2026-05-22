<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface Maho_Ai_Model_Platform_ImageProviderInterface
{
    /**
     * Generate an image from a text prompt.
     *
     * @param array<string, mixed> $options  Supports: width (int), height (int), quality (string), style (string), model (string)
     * @return string  URL (https://...) for providers that return URLs, or data URI (data:image/png;base64,...) for providers that return binary
     */
    public function generateImage(string $prompt, array $options = []): string;

    /**
     * Get the platform code (e.g. 'openai', 'google')
     */
    public function getImagePlatformCode(): string;

    /**
     * Get the model used in the last generateImage() call
     */
    public function getLastImageModel(): string;
}
