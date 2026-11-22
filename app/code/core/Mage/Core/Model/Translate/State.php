<?php

/**
 * The loaded translations of the translator and their configuration at one moment.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final readonly class Mage_Core_Model_Translate_State
{
    /**
     * @param array<string, mixed>|null $config the area, the locale, the store, the package and the theme
     * @param array<string, string>|null $data the translations, by source text
     * @param array<string, string>|null $dataScope the module of each source text that a module translates
     */
    public function __construct(
        public ?string $locale,
        public ?array $config,
        public ?string $cacheId,
        public ?array $data,
        public ?array $dataScope,
    ) {}
}
