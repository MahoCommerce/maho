<?php

/**
 * Turn the SQL rows of a plan into a result: typed values, empty buckets, the comparison and the changes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Aggregate;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Metric;
use Maho\Report\Definition\MetricFormat;

final class PostProcessor
{
    public const WARNING_NON_ADDITIVE = 'non_additive';

    /**
     * @param list<array<string, mixed>> $rows the rows of {@see SqlBuilder::rows()}
     * @param array<string, mixed> $totals the row of {@see SqlBuilder::totals()}
     * @param list<array<string, mixed>>|null $compareRows
     * @param array<string, mixed>|null $compareTotals
     * @return array{
     *     rows: list<array{dimensions: array<string, string|int|null>, labels: array<string, string>, values: array<string, float|string|null>, compare: array<string, float|string|null>|null, change: array<string, array{absolute: ?float, percent: ?float}>|null}>,
     *     totals: array{values: array<string, float|string|null>, compare: array<string, float|string|null>|null, change: array<string, array{absolute: ?float, percent: ?float}>|null},
     *     totalRows: int,
     *     warnings: list<string>
     * }
     */
    public function process(Plan $plan, array $rows, array $totals, int $totalRows, ?array $compareRows = null, ?array $compareTotals = null): array
    {
        $current = $this->keyed($plan, $rows);
        $period = $plan->periodDimension();
        $fill = $period !== null && count($plan->dimensions) === 1 && $plan->localFrom !== null && $plan->localTo !== null;

        if ($fill) {
            $filled = [];
            foreach (Buckets::keys($period->granularity, $plan->localFrom, $plan->localTo, $plan->firstDayOfWeek) as $key) {
                $rowKey = $this->rowKey([$key]);
                $filled[$rowKey] = $current[$rowKey] ?? [
                    'dimensions' => [$period->dimension->code => $key],
                    'values' => $this->emptyValues($plan),
                ];
            }
            $current = $filled;
            $totalRows = count($current);
        }

        $compare = null;
        if ($compareRows !== null) {
            $compare = $this->keyed($plan, $compareRows, $this->periodMap($plan));
        }

        $result = [];
        foreach ($current as $rowKey => $row) {
            $compareValues = $compare === null ? null : ($compare[$rowKey]['values'] ?? $this->emptyValues($plan));
            $result[] = [
                'dimensions' => $row['dimensions'],
                'labels' => $this->periodLabels($plan, $row['dimensions']),
                'values' => $row['values'],
                'compare' => $compareValues,
                'change' => $compareValues === null ? null : $this->change($plan, $row['values'], $compareValues),
            ];
        }

        if ($fill && $plan->request->sortKey !== null && $plan->request->sortKey !== $period->dimension->code) {
            $key = $plan->request->sortKey;
            usort($result, static function (array $a, array $b) use ($key, $plan): int {
                $order = ($a['values'][$key] ?? null) <=> ($b['values'][$key] ?? null);
                return $plan->request->sortDescending ? -$order : $order;
            });
        } elseif ($fill && ($plan->request->sortKey === null ? $period->granularity?->isCyclic() !== true : $plan->request->sortDescending)) {
            $result = array_reverse($result);
        }

        $totalValues = $this->values($plan, $totals);
        $totalCompare = $compareTotals === null ? null : $this->values($plan, $compareTotals);

        $warnings = [];
        foreach ($plan->dimensions as $dimension) {
            if ($dimension->dimension->nonAdditive) {
                $warnings[] = self::WARNING_NON_ADDITIVE;
            }
        }

        return [
            'rows' => $result,
            'totals' => [
                'values' => $totalValues,
                'compare' => $totalCompare,
                'change' => $totalCompare === null ? null : $this->change($plan, $totalValues, $totalCompare),
            ],
            'totalRows' => $totalRows,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * The totals of the plan from all its rows, in the shape of {@see SqlBuilder::totals()}: the sum of each part, and
     * each formula calculated from those sums.
     *
     * @param array<string, Metric> $parts the result of {@see SqlBuilder::totalParts()}
     * @param list<array<string, mixed>> $rows every row of {@see SqlBuilder::rows()}
     * @return array<string, float|null>
     */
    public function totalsFromRows(Plan $plan, array $parts, array $rows): array
    {
        $sums = [];
        foreach (array_keys($parts) as $code) {
            $sums[$code] = array_sum(array_map(static fn(array $row): float => (float) ($row['m_' . $code] ?? 0), $rows));
        }
        $totals = [];
        foreach ($plan->metrics as $metric) {
            $totals['m_' . $metric->code] = $this->totalFromSums($plan->dataset, $metric, $sums);
        }
        return $totals;
    }

    /** @param array<string, float> $sums the sum of each part over the rows */
    private function totalFromSums(Dataset $dataset, Metric $metric, array $sums): ?float
    {
        return $metric->formula === null
            ? $sums[$metric->code]
            : Formula::parse($metric->formula)->evaluate(fn(string $code): ?float => $this->totalFromSums($dataset, $dataset->getMetric($code), $sums));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string|int, string|int>|null $periodMap a compare bucket key => the current bucket key at the same position
     * @return array<string, array{dimensions: array<string, string|int|null>, values: array<string, float|string|null>}>
     */
    private function keyed(Plan $plan, array $rows, ?array $periodMap = null): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            $dimensions = [];
            foreach ($plan->dimensions as $dimension) {
                $raw = $row[$dimension->alias] ?? null;
                if ($dimension->granularity !== null) {
                    $raw = Buckets::normalize($dimension->granularity, $raw);
                    if ($periodMap !== null && $raw !== null) {
                        $raw = $periodMap[$raw] ?? null;
                        if ($raw === null) {
                            continue 2;
                        }
                    }
                } elseif ($raw !== null && !is_int($raw)) {
                    $raw = (string) $raw;
                }
                $dimensions[$dimension->dimension->code] = $raw;
            }
            $keyed[$this->rowKey(array_values($dimensions))] = [
                'dimensions' => $dimensions,
                'values' => $this->values($plan, $row),
            ];
        }
        return $keyed;
    }

    /**
     * The bucket keys of the comparison, each mapped to the bucket key at the same position in the period.
     *
     * @return array<string|int, string|int>|null
     */
    private function periodMap(Plan $plan): ?array
    {
        $period = $plan->periodDimension();
        if ($period === null || $plan->compareFrom === null || $plan->compareTo === null
            || $plan->localFrom === null || $plan->localTo === null) {
            return null;
        }
        $current = Buckets::keys($period->granularity, $plan->localFrom, $plan->localTo, $plan->firstDayOfWeek);
        $previous = Buckets::keys($period->granularity, $plan->compareFrom, $plan->compareTo, $plan->firstDayOfWeek);
        $map = [];
        foreach ($previous as $index => $key) {
            if (isset($current[$index])) {
                $map[$key] = $current[$index];
            }
        }
        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, float|string|null>
     */
    private function values(Plan $plan, array $row): array
    {
        $values = [];
        foreach ($plan->metrics as $metric) {
            $raw = $row['m_' . $metric->code] ?? null;
            $values[$metric->code] = match (true) {
                $raw === null => $metric->aggregate === Aggregate::Sum || $metric->aggregate === Aggregate::Count ? 0.0 : null,
                $metric->format === MetricFormat::Date => (string) $raw,
                default => round((float) $raw, 4),
            };
        }
        return $values;
    }

    /** @return array<string, float|string|null> the values of a bucket with no row */
    private function emptyValues(Plan $plan): array
    {
        $values = [];
        foreach ($plan->metrics as $metric) {
            $values[$metric->code] = $this->emptyValue($plan->dataset, $metric);
        }
        return $values;
    }

    private function emptyValue(Dataset $dataset, Metric $metric): ?float
    {
        if ($metric->formula !== null) {
            return Formula::parse($metric->formula)->evaluate(
                fn(string $code): ?float => $this->emptyValue($dataset, $dataset->getMetric($code)),
            );
        }
        return match ($metric->aggregate) {
            Aggregate::Sum, Aggregate::Count, Aggregate::CountDistinct => 0.0,
            default => null,
        };
    }

    /**
     * @param array<string, float|string|null> $values
     * @param array<string, float|string|null> $previous
     * @return array<string, array{absolute: ?float, percent: ?float}>
     */
    private function change(Plan $plan, array $values, array $previous): array
    {
        $change = [];
        foreach ($plan->metrics as $metric) {
            $now = $values[$metric->code] ?? null;
            $before = $previous[$metric->code] ?? null;
            if (!is_float($now) || !is_float($before)) {
                $change[$metric->code] = ['absolute' => null, 'percent' => null];
                continue;
            }
            $change[$metric->code] = [
                'absolute' => round($now - $before, 4),
                'percent' => $before == 0.0 ? null : round(($now - $before) / abs($before) * 100, 2),
            ];
        }
        return $change;
    }

    /**
     * @param array<string, string|int|null> $dimensions
     * @return array<string, string>
     */
    private function periodLabels(Plan $plan, array $dimensions): array
    {
        $labels = [];
        foreach ($plan->dimensions as $dimension) {
            $value = $dimensions[$dimension->dimension->code] ?? null;
            if ($dimension->granularity !== null && $value !== null) {
                $labels[$dimension->dimension->code] = Buckets::label($dimension->granularity, $value, $plan->firstDayOfWeek);
            }
        }
        return $labels;
    }

    /** @param list<string|int|null> $values */
    private function rowKey(array $values): string
    {
        return json_encode($values, JSON_THROW_ON_ERROR);
    }
}
