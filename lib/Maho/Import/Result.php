<?php

/**
 * Counters and notices of one import run.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

final class Result
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var list<string> */
    public array $notices = [];

    public function merge(self $other): self
    {
        $this->created += $other->created;
        $this->updated += $other->updated;
        $this->skipped += $other->skipped;
        $this->notices = array_merge($this->notices, $other->notices);
        return $this;
    }

    public function summary(): string
    {
        return sprintf('%d created, %d updated, %d skipped', $this->created, $this->updated, $this->skipped);
    }
}
