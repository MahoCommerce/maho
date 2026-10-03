<?php

/**
 * Queue message for one background run of the assistant.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

final readonly class Maho_Ai_Model_Chat_BackgroundTurn
{
    public function __construct(
        public int $conversationId,
        public int $adminUserId,
        public string $instruction,
    ) {}
}
