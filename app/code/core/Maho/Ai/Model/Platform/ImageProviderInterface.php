<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

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
