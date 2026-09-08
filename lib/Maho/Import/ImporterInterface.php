<?php

/**
 * A CSV importer: validate() reads and checks without writing, import() validates then writes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

interface ImporterInterface
{
    /**
     * @param array<string, mixed> $options
     * @throws RowException
     */
    public function validate(string $csvPath, array $options = []): void;

    /**
     * Idempotent by natural key: a rerun updates, it never duplicates.
     *
     * @param array<string, mixed> $options
     * @throws RowException
     */
    public function import(string $csvPath, array $options = [], ?Reporter $reporter = null): Result;
}
