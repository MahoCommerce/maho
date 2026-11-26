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
 * Prefixes a mount path with a store base URL (media or web).
 *
 * The URL is resolved on every call. Without options it follows the current store and the
 * secure flag of the request. A caller that writes for another store, such as a cron job,
 * names it: `$mount->publicUrl($path, [StoreBaseUrlGenerator::OPTION_STORE => $storeId])`.
 */
final class StoreBaseUrlGenerator implements PublicUrlGenerator
{
    /** The store whose base URL to use: an ID, a code or a store model. */
    public const string OPTION_STORE = 'store';

    /** True for the secure base URL, false for the unsecure one. */
    public const string OPTION_SECURE = 'secure';

    public function __construct(private readonly string $urlType) {}

    #[\Override]
    public function publicUrl(string $path, Config $config): string
    {
        $store = \Mage::app()->getStore($config->get(self::OPTION_STORE));

        return rtrim($store->getBaseUrl($this->urlType, $config->get(self::OPTION_SECURE)), '/') . '/' . ltrim($path, '/');
    }
}
