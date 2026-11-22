<?php

/**
 * Check a subscription to a saved report, and email the report when it is due.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

final readonly class Mage_Reports_Model_Queue_Subscription
{
    public const string QUEUE_NAME = 'reports';

    public function __construct(
        public int $subscriptionId,
    ) {}
}
