<?php

/**
 * Queue message that applies all the catalog price rules. Handled by
 * Mage_CatalogRule_Model_ApplyAllMessageHandler, which sends the result to the inbox of $adminUserId.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

final readonly class Mage_CatalogRule_Model_ApplyAllMessage
{
    public const QUEUE_NAME = 'catalogrule';

    public const DEDUPE_KEY = 'catalogrule_apply_all';

    public function __construct(
        public ?int $adminUserId = null,
    ) {}
}
