<?php

/**
 * A folder of Markdown pages for one audience.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Docs;

final readonly class Source
{
    /**
     * @param string $name "core/" and the folder for a folder at the root of the Maho package, such as "core/user-guide", or the module that declares the folder, such as "Acme_GiftWrap"
     * @param string $audience one of Library::AUDIENCES
     */
    public function __construct(
        public string $name,
        public string $audience,
        public string $dir,
    ) {}
}
