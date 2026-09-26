<?php

/**
 * The counts of one run of Migrator.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

final class MigrationResult
{
    public int $copied = 0;

    public int $skipped = 0;

    public int $bytes = 0;

    /** @var array<string, string> path => error message */
    public array $failed = [];
}
