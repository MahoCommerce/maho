<?php

/**
 * Queue message that applies the active rules of one link type, or of every link type when
 * $linkTypeId is null. Handled by Maho_CatalogLinkRule_Model_Processor::handleApplyRules(), which
 * sends the result to the inbox of $adminUserId.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

final readonly class Maho_CatalogLinkRule_Model_ApplyRulesMessage
{
    public function __construct(
        public ?int $linkTypeId = null,
        public ?int $adminUserId = null,
    ) {}
}
