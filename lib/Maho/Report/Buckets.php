<?php

/**
 * The buckets of a period dimension: the keys that the SQL returns, every key of a range, and the labels.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Granularity;

final class Buckets
{
    /** 1970-01-04 was a Sunday. A week key counts the days from this date plus the first day of the week. */
    public const SUNDAY = '1970-01-04';

    /** The reference date of the week keys, a day that falls on $firstDayOfWeek. */
    public static function weekReference(int $firstDayOfWeek): string
    {
        return new \DateTimeImmutable(self::SUNDAY)->modify("+{$firstDayOfWeek} days")->format('Y-m-d');
    }

    /** Turn a raw SQL value into the key of its bucket. */
    public static function normalize(Granularity $granularity, mixed $raw): string|int|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        return match ($granularity) {
            Granularity::Hour => substr((string) $raw, 0, 13) . ':00:00',
            Granularity::Day => substr((string) $raw, 0, 10),
            Granularity::Month => substr((string) $raw, 0, 7),
            Granularity::Year => substr((string) $raw, 0, 4),
            Granularity::Week, Granularity::HourOfDay, Granularity::DayOfWeek => (int) round((float) $raw),
        };
    }

    /**
     * Every key between two local days, in order.
     *
     * @return list<string|int>
     */
    public static function keys(Granularity $granularity, \DateTimeImmutable $from, \DateTimeImmutable $to, int $firstDayOfWeek = 0): array
    {
        if ($granularity === Granularity::HourOfDay) {
            return range(0, 23);
        }
        if ($granularity === Granularity::DayOfWeek) {
            return array_map(static fn(int $i): int => ($firstDayOfWeek + $i) % 7, range(0, 6));
        }

        $keys = [];
        $day = $from->setTime(0, 0);
        $last = $to->setTime(0, 0);
        $reference = new \DateTimeImmutable(self::weekReference($firstDayOfWeek), $from->getTimezone());
        while ($day <= $last) {
            if ($granularity === Granularity::Hour) {
                for ($hour = 0; $hour < 24; $hour++) {
                    $keys[] = $day->format('Y-m-d') . sprintf(' %02d:00:00', $hour);
                }
            } else {
                $keys[] = self::keyOf($granularity, $day, $firstDayOfWeek, $reference);
            }
            $day = $day->modify('+1 day');
        }
        return array_values(array_unique($keys));
    }

    /**
     * The key of the bucket of a local day: the day, the month or the year as text, or the week as the number of days
     * from the reference date to the first day of the week. A step shorter than a day, or a cycle, gives null.
     */
    public static function keyOf(Granularity $granularity, \DateTimeImmutable $day, int $firstDayOfWeek = 0, ?\DateTimeImmutable $reference = null): string|int|null
    {
        return match ($granularity) {
            Granularity::Day => $day->format('Y-m-d'),
            Granularity::Month => $day->format('Y-m'),
            Granularity::Year => $day->format('Y'),
            Granularity::Week => (static function () use ($day, $reference, $firstDayOfWeek): int {
                $reference ??= new \DateTimeImmutable(self::weekReference($firstDayOfWeek), $day->getTimezone());
                $days = (int) $reference->diff($day->setTime(0, 0))->format('%r%a');
                return $days - (($days % 7) + 7) % 7;
            })(),
            default => null,
        };
    }

    /** A label for a bucket key. The weekday and the hour of day get a number here: the caller can translate them. */
    public static function label(Granularity $granularity, string|int $key, int $firstDayOfWeek = 0): string
    {
        return match ($granularity) {
            Granularity::Hour => substr((string) $key, 0, 16),
            Granularity::Week => new \DateTimeImmutable(self::weekReference($firstDayOfWeek))->modify('+' . (int) $key . ' days')->format('Y-m-d'),
            Granularity::HourOfDay => sprintf('%02d:00', (int) $key),
            default => (string) $key,
        };
    }
}
