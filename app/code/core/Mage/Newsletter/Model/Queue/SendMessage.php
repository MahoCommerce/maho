<?php

/**
 * Queue message asking for the next batch of a newsletter campaign to go out.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

final class Mage_Newsletter_Model_Queue_SendMessage
{
    public function __construct(
        public readonly int $queueId,
    ) {}
}
