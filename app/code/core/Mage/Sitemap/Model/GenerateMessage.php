<?php

/**
 * Queue message that generates one sitemap. Handled by Mage_Sitemap_Model_GenerateMessageHandler,
 * which sends the result to the inbox of $adminUserId.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

final readonly class Mage_Sitemap_Model_GenerateMessage
{
    public const QUEUE_NAME = 'sitemap';

    public function __construct(
        public int $sitemapId,
        public ?int $adminUserId = null,
    ) {}

    /**
     * While a message with this key waits or runs, the queue does not add a second one.
     */
    public function getDedupeKey(): string
    {
        return 'sitemap_generate_' . $this->sitemapId;
    }
}
