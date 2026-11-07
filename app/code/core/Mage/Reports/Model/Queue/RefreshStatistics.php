<?php

/**
 * Queue message that asks for a lifetime refresh of the statistics of one report.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

final class Mage_Reports_Model_Queue_RefreshStatistics
{
    public const QUEUE_NAME = 'reports';

    /**
     * @param ?int $adminUserId the administrator who gets the result in the inbox, or null for no message
     */
    public function __construct(
        public readonly string $code,
        public readonly ?int $adminUserId = null,
    ) {}

    /**
     * While a message with this key is pending or in work, the queue does not add a second one.
     */
    public function getDedupeKey(): string
    {
        return 'reports_lifetime_' . $this->code;
    }
}
