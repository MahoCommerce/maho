<?php

/**
 * Queue message that writes an export file. $parameters holds the entity, the file format and
 * the filters of the export form. Handled by Mage_ImportExport_Model_MessageHandler, which sends
 * a link to the file to the inbox of $adminUserId.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

final readonly class Mage_ImportExport_Model_ExportMessage
{
    public const QUEUE_NAME = 'importexport';

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public array $parameters,
        public ?int $adminUserId = null,
    ) {}
}
