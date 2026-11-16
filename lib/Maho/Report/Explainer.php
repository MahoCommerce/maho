<?php

/**
 * Rank the values of a dimension by how much of the change of a metric between two periods they caused.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

final class Explainer
{
    /**
     * The values of one dimension with the biggest change first.
     *
     * The share is the part of the total change that a value caused, as a percent. It is null when the values do not
     * add up to the total: a ratio or a distinct count, or a dimension where a record has several values.
     *
     * @param array<string, array{label: string, current: ?float, earlier: ?float}> $values the values of the dimension, by key
     * @param float|null $totalChange the change of the metric over the whole report
     * @param bool $additive whether the values of the dimension add up to the total of the metric
     * @return list<array{value: string, label: string, current: ?float, earlier: ?float, change: float, percent: ?float, share: ?float}>
     */
    public static function drivers(array $values, ?float $totalChange, bool $additive, int $top = 5): array
    {
        $drivers = [];
        foreach ($values as $key => $value) {
            $current = $value['current'];
            $earlier = $value['earlier'];
            $change = ($current ?? 0.0) - ($earlier ?? 0.0);
            if (abs($change) < 0.00005) {
                continue;
            }
            $drivers[] = [
                'value' => (string) $key,
                'label' => $value['label'],
                'current' => $current,
                'earlier' => $earlier,
                'change' => round($change, 4),
                'percent' => $earlier === null || abs($earlier) < 0.00005 ? null : round($change / abs($earlier) * 100, 2),
                'share' => $additive && $totalChange !== null && abs($totalChange) >= 0.00005 ? round($change / $totalChange * 100, 2) : null,
            ];
        }
        usort($drivers, static fn(array $a, array $b): int => abs($b['change']) <=> abs($a['change']) ?: strcmp($a['value'], $b['value']));
        return array_slice($drivers, 0, max(1, $top));
    }
}
