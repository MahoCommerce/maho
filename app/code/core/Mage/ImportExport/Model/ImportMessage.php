<?php

/**
 * Queue message that imports the rows that the last validation stored. Handled by
 * Mage_ImportExport_Model_MessageHandler, which sends the result to the inbox of $adminUserId.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

final readonly class Mage_ImportExport_Model_ImportMessage
{
    public const QUEUE_NAME = 'importexport';

    public const DEDUPE_KEY = 'importexport_import';

    public function __construct(
        public ?int $adminUserId = null,
    ) {}
}
