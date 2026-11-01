<?php

/**
 * Queue message that calculates the customers of one segment again. Handled by
 * Maho_CustomerSegmentation_Model_RefreshMessageHandler, which sends the result to the inbox of
 * $adminUserId.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

final readonly class Maho_CustomerSegmentation_Model_RefreshMessage
{
    public const QUEUE_NAME = 'customersegmentation';

    public function __construct(
        public int $segmentId,
        public ?int $adminUserId = null,
    ) {}

    /**
     * While a message with this key waits or runs, the queue does not add a second one.
     */
    public function getDedupeKey(): string
    {
        return 'customersegmentation_refresh_' . $this->segmentId;
    }
}
