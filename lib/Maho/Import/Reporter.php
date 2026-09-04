<?php

/**
 * Where an importer sends its progress: the CLI prints it, the web installer writes a progress file.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

interface Reporter
{
    public function info(string $message): void;

    public function warning(string $message): void;

    public function progress(int $done, int $total, string $label = ''): void;
}
