<?php

/**
 * Queue message that runs one AI task.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

final readonly class Maho_Ai_Model_Task_QueueMessage
{
    public function __construct(
        public int $taskId,
    ) {}
}
