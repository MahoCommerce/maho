<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage\Url;

use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;

/**
 * Prefixes a mount path with a store base URL (media or web), resolved on
 * every call so it follows the current store and the secure flag.
 */
final class StoreUrlGenerator implements PublicUrlGenerator
{
    public function __construct(private readonly string $urlType) {}

    #[\Override]
    public function publicUrl(string $path, Config $config): string
    {
        return rtrim(\Mage::getBaseUrl($this->urlType), '/') . '/' . ltrim($path, '/');
    }
}
