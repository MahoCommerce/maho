<?php

/**
 * Resolve a request against its dataset: the scope, the date field, the dimensions and the local periods.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Granularity;

final class Planner
{
    public const DateRange DEFAULT_RANGE = DateRange::Last30Days;

    /**
     * The codes in the request must exist in the dataset: the validator checks them before.
     *
     * @param \DateTimeImmutable $now the current time
     * @param string $timezone the time zone of the local days
     * @param int $firstDayOfWeek 0 for Sunday to 6 for Saturday
     */
    public function plan(Dataset $dataset, Request $request, \DateTimeImmutable $now, string $timezone, int $firstDayOfWeek = 0): Plan
    {
        $scopeCode = $request->scope ?? $dataset->getDefaultScope();
        $scope = $scopeCode === null ? null : $dataset->getScope($scopeCode);

        $dateField = null;
        if ($dataset->isDated()) {
            $dateField = $dataset->getField($request->dateField ?? $dataset->getDateFields()[0]);
        }

        $metrics = array_map($dataset->getMetric(...), $request->metrics);

        $dimensions = [];
        foreach ($request->dimensions as $index => $requested) {
            $dimension = $dataset->getDimension($requested->code);
            if ($dimension->isPeriod()) {
                if ($dateField === null) {
                    throw new \LogicException("The dataset {$dataset->code} has no date field for the period dimension");
                }
                $dimensions[] = new PlannedDimension($dimension, $dateField, $requested->granularity ?? Granularity::Day, "d{$index}");
            } else {
                $dimensions[] = new PlannedDimension($dimension, $dataset->getField($dimension->field), null, "d{$index}");
            }
        }

        $localFrom = $localTo = $compareFrom = $compareTo = null;
        if ($dateField !== null) {
            [$localFrom, $localTo] = self::localRange($request, $now, $timezone);
            [$compareFrom, $compareTo] = self::compareRange($request->compare, $localFrom, $localTo);
        }

        return new Plan(
            $dataset,
            $request,
            $scope,
            $dateField,
            $metrics,
            $dimensions,
            $localFrom,
            $localTo,
            $compareFrom,
            $compareTo,
            $timezone,
            $firstDayOfWeek,
        );
    }

    /**
     * The first and the last local day of the request, at midnight in $timezone.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function localRange(Request $request, \DateTimeImmutable $now, string $timezone): array
    {
        $zone = new \DateTimeZone($timezone);
        if ($request->range === null && $request->from !== null && $request->to !== null) {
            $from = new \DateTimeImmutable($request->from, $zone);
            $to = new \DateTimeImmutable($request->to, $zone);
            return $from <= $to ? [$from, $to] : [$to, $from];
        }
        return ($request->range ?? self::DEFAULT_RANGE)->resolve($now->setTimezone($zone));
    }

    /**
     * The period that a comparison reads. The previous period has the same number of days and ends the day before.
     * The previous year moves both days back one year, and February 29 becomes February 28.
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    public static function compareRange(Comparison $compare, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return match ($compare) {
            Comparison::None => [null, null],
            Comparison::PreviousPeriod => (static function () use ($from, $to): array {
                $days = (int) $from->diff($to)->days;
                $end = $from->modify('-1 day');
                return [$end->modify("-{$days} days"), $end];
            })(),
            Comparison::PreviousYear => [self::yearBefore($from), self::yearBefore($to)],
        };
    }

    private static function yearBefore(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $year = (int) $date->format('Y') - 1;
        $month = (int) $date->format('n');
        $day = min((int) $date->format('j'), (int) new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month))->format('t'));
        return $date->setDate($year, $month, $day);
    }
}
