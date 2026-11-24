<?php

/**
 * The values that a report SQL expression can read: the adapter, the clock, the time zone and the range.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Db\Adapter\AdapterInterface;

final class Context
{
    /**
     * @param \Closure(string): string $localTime turns a UTC datetime column into the same moment in local time
     * @param \DateTimeImmutable $now the current time in UTC
     * @param int $firstDayOfWeek 0 for Sunday to 6 for Saturday
     * @param string|null $utcFrom the start of the queried range, as a UTC "Y-m-d H:i:s"
     * @param string|null $utcTo the end of the queried range, as a UTC "Y-m-d H:i:s"
     * @param array<string, mixed> $settings values from the configuration, such as the days of dead stock
     * @param string|null $dateField the code of the date field of the request, for a derived join that filters on it
     * @param list<int>|null $storeIds the store views of the request, for a derived join that reads data of a store
     * @param (\Closure(string): string)|null $localTimeOfAnyDate like $localTime, for a date that can be outside the
     *                                                           queried range, such as the first order of a customer
     */
    public function __construct(
        public readonly AdapterInterface $adapter,
        private readonly \Closure $localTime,
        public readonly \DateTimeImmutable $now,
        public readonly string $timezone,
        public readonly int $firstDayOfWeek = 0,
        public readonly ?string $utcFrom = null,
        public readonly ?string $utcTo = null,
        public readonly array $settings = [],
        public readonly ?string $dateField = null,
        public readonly ?array $storeIds = null,
        private readonly ?\Closure $localTimeOfAnyDate = null,
    ) {}

    /** The local time of a column whose dates are in the queried range. It knows the clock changes of that range only. */
    public function localTime(string $column): string
    {
        return ($this->localTime)($column);
    }

    /** The local time of a column whose dates can be in any year, with the clock changes of every year. */
    public function localTimeOfAnyDate(string $column): string
    {
        return ($this->localTimeOfAnyDate ?? $this->localTime)($column);
    }

    public function withRange(?string $utcFrom, ?string $utcTo): self
    {
        return new self($this->adapter, $this->localTime, $this->now, $this->timezone, $this->firstDayOfWeek, $utcFrom, $utcTo, $this->settings, $this->dateField, $this->storeIds, $this->localTimeOfAnyDate);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /** The current time in UTC, moved by a number of hours or days, as "Y-m-d H:i:s". */
    public function utcAgo(int $amount, string $unit = 'hours'): string
    {
        return $this->now->modify("-{$amount} {$unit}")->format('Y-m-d H:i:s');
    }

    public function quote(mixed $value): string
    {
        return $this->adapter->quote($value);
    }
}
