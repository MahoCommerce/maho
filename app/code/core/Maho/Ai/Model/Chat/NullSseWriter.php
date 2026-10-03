<?php

/**
 * A stream writer for a turn with no browser: it drops every event.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Chat_NullSseWriter extends Maho_Ai_Model_Chat_SseWriter
{
    #[\Override]
    public function open(): void {}

    #[\Override]
    public function event(string $name, array $data = []): void {}

    #[\Override]
    public function isClientGone(): bool
    {
        return false;
    }
}
