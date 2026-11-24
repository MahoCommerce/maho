<?php

/**
 * Queue message that writes a large report export. The handler sends a link to the file to the inbox of $adminUserId.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

final readonly class Mage_Reports_Model_Queue_ExplorerExport
{
    public const string QUEUE_NAME = 'reports';

    /**
     * @param array<string, mixed> $request the shape of {@see \Maho\Report\Request::toArray()}
     * @param array<string, mixed> $access the shape of {@see Mage_Reports_Model_Explorer_Access::toArray()}
     */
    public function __construct(
        public array $request,
        public array $access,
        public int $adminUserId,
    ) {}
}
