<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

final class NullReporter implements Reporter
{
    #[\Override]
    public function info(string $message): void {}

    #[\Override]
    public function warning(string $message): void {}

    #[\Override]
    public function progress(int $done, int $total, string $label = ''): void {}
}
