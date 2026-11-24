<?php

/**
 * A request resolved against its dataset: the metrics, the dimensions and the UTC bounds of each period.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\Metric;
use Maho\Report\Definition\Scope;

final class Plan
{
    /**
     * @param list<Metric> $metrics
     * @param list<PlannedDimension> $dimensions
     */
    public function __construct(
        public readonly Dataset $dataset,
        public readonly Request $request,
        public readonly ?Scope $scope,
        public readonly ?Field $dateField,
        public readonly array $metrics,
        public readonly array $dimensions,
        public readonly ?\DateTimeImmutable $localFrom,
        public readonly ?\DateTimeImmutable $localTo,
        public readonly ?\DateTimeImmutable $compareFrom,
        public readonly ?\DateTimeImmutable $compareTo,
        public readonly string $timezone,
        public readonly int $firstDayOfWeek,
    ) {}

    public function periodDimension(): ?PlannedDimension
    {
        foreach ($this->dimensions as $dimension) {
            if ($dimension->dimension->isPeriod()) {
                return $dimension;
            }
        }
        return null;
    }

    public function hasComparison(): bool
    {
        return $this->compareFrom !== null && $this->compareTo !== null;
    }

    /** @return array{0: string, 1: string}|null the UTC "Y-m-d H:i:s" bounds of the period or of the comparison */
    public function utcBounds(bool $compare = false): ?array
    {
        $from = $compare ? $this->compareFrom : $this->localFrom;
        $to = $compare ? $this->compareTo : $this->localTo;
        if ($from === null || $to === null) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        return [
            $from->setTime(0, 0)->setTimezone($utc)->format('Y-m-d H:i:s'),
            $to->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array{0: string, 1: string}|null the local "Y-m-d" bounds of the period or of the comparison */
    public function localBounds(bool $compare = false): ?array
    {
        $from = $compare ? $this->compareFrom : $this->localFrom;
        $to = $compare ? $this->compareTo : $this->localTo;
        return $from === null || $to === null ? null : [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }
}
