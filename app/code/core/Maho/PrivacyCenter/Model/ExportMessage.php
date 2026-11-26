<?php

/**
 * Asks the queue to write the export of a confirmed privacy request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

final readonly class Maho_PrivacyCenter_Model_ExportMessage
{
    public const string QUEUE_NAME = 'privacycenter';

    public function __construct(
        public int $requestId,
    ) {}

    /**
     * While a message with this key waits or runs, the queue does not add a second one.
     */
    public function getDedupeKey(): string
    {
        return 'privacycenter_export_' . $this->requestId;
    }
}
