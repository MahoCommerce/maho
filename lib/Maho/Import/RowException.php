<?php

/**
 * An import failure pinned to a file and a line, so the author can open the CSV at the right row.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

class RowException extends \Maho\Exception
{
    public function __construct(
        public readonly string $csvPath,
        public readonly int $csvLine,
        string $message,
        ?\Throwable $previous = null,
    ) {
        $where = basename($csvPath) . ($csvLine > 0 ? " line $csvLine" : '');
        parent::__construct("$where: $message", 0, $previous);
    }
}
