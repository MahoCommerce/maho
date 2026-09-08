<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

interface Maho_ContentNegotiation_Model_Renderer_RendererInterface
{
    /**
     * Null when the page has no entity to render, so the HTML response stays as it is.
     */
    public function render(): ?string;

    /**
     * @return string[]
     */
    public function getCacheTags(): array;
}
