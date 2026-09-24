<?php

/**
 * Base provider of the reports. Its name does not end with Provider, so the API kernel loads it before the providers. It runs the report in the admin store and returns a raw JSON document.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class ReportProviderBase extends \Maho\ApiPlatform\Provider
{
    /**
     * A report with rows per period has at most this number of rows in total.
     */
    public const MAX_ROWS = 5000;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->requireUser();
        $filters = self::requestFilters($context);

        /** @var \Mage_Reports_Model_Statistics $statistics */
        $statistics = \Mage::getModel('reports/statistics');
        $document = $statistics->withAdminStore(fn(): array => $this->buildReport($filters, $user));

        return $this->respondRaw($document);
    }

    /**
     * Build the document of the report.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    abstract protected function buildReport(array $filters, ApiUser $user): array;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function requestFilters(array $context): array
    {
        return ($context['filters'] ?? []) + ($context['request']?->query->all() ?? []);
    }

    /**
     * The global base currency. The aggregation and the live queries convert every amount to it with base_to_global_rate.
     */
    public static function currency(): string
    {
        return (string) \Mage::app()->getBaseCurrencyCode();
    }

    /**
     * The time zone of the default scope. The periods of the report tables use it.
     */
    public static function timezone(): string
    {
        return (string) (\Mage::getStoreConfig(\Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, \Mage_Core_Model_App::ADMIN_STORE_ID)
            ?: \Mage_Core_Model_Locale::DEFAULT_TIMEZONE);
    }

    /**
     * Convert a UTC date of the database to ISO 8601, or return null.
     */
    public static function isoDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || str_starts_with($value, '0000')) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Join the parts of a name with spaces, or return null when all parts are empty.
     */
    public static function personName(mixed ...$parts): ?string
    {
        $parts = array_filter(array_map(static fn(mixed $part): string => trim((string) $part), $parts), static fn(string $part): bool => $part !== '');
        return $parts === [] ? null : implode(' ', $parts);
    }

    public static function amount(mixed $value): float
    {
        return round((float) $value, 4);
    }

    public static function quantity(mixed $value): float
    {
        return round((float) $value, 4);
    }

    /**
     * Return the statistics part of an aggregated report: the time of the last refresh of its tables.
     *
     * @return array{code: string, updatedAt: ?string}
     */
    protected function statisticsOf(string $code): array
    {
        /** @var \Mage_Reports_Model_Statistics $statistics */
        $statistics = \Mage::getModel('reports/statistics');
        return ['code' => $code, 'updatedAt' => self::isoDate($statistics->getUpdatedAt($code))];
    }

    /**
     * Build the document of an aggregated report.
     *
     * @param array<string, mixed> $totals
     * @param list<array<string, mixed>> $periods
     * @param array<string, mixed> $extra keys that come after dateBasis
     * @return array<string, mixed>
     */
    protected function envelope(string $report, ReportQuery $query, array $totals, array $periods, ?string $statisticsCode, array $extra = []): array
    {
        $document = [
            'report' => $report,
            'currency' => self::currency(),
            'timezone' => self::timezone(),
            'periodType' => $query->periodType,
            'from' => $query->from,
            'to' => $query->to,
        ];
        if ($query->dateBasis !== null) {
            $document['dateBasis'] = $query->dateBasis;
        }
        if ($query->orderStatuses !== null) {
            $document['orderStatuses'] = $query->orderStatuses;
        }
        $document += $extra;
        $document['scope'] = $query->scopeArray();
        if ($statisticsCode !== null) {
            $document['statistics'] = $this->statisticsOf($statisticsCode);
        }
        $document['totals'] = $totals;
        $document['periods'] = $periods;
        return $document;
    }

    /**
     * Build the document of a live report with a ranked list.
     *
     * @param array<string, mixed> $extra keys that come after to
     * @param list<array<string, mixed>> $member
     * @return array<string, mixed>
     */
    protected function listEnvelope(string $report, ReportQuery $query, array $extra, array $member): array
    {
        return [
            'report' => $report,
            'currency' => self::currency(),
            'timezone' => self::timezone(),
            'from' => $query->from,
            'to' => $query->to,
        ] + $extra + [
            'scope' => $query->scopeArray(),
            'totalItems' => count($member),
            'member' => $member,
        ];
    }

    /**
     * Put the rows in period order. With emptyPeriods, add each missing period with $empty.
     *
     * @param array<string, mixed> $byPeriod period label => the part of the period
     * @param array<string, mixed> $empty the part of a period without data
     * @return list<array<string, mixed>>
     */
    protected function periodList(ReportQuery $query, array $byPeriod, array $empty, string $key = 'values'): array
    {
        if ($query->emptyPeriods) {
            $labels = $query->periodLabels();
        } else {
            $labels = array_map(strval(...), array_keys($byPeriod));
            sort($labels);
        }

        $periods = [];
        foreach ($labels as $label) {
            $periods[] = ['period' => $label, $key => $byPeriod[$label] ?? $empty];
        }
        return $periods;
    }

    /**
     * Build a report with one set of values per period from an admin report collection.
     *
     * @param array<string, string> $columns value key => column of the collection
     * @param list<string> $countKeys the value keys that are counts (integers)
     * @return array<string, mixed>
     */
    protected function valueReport(
        string $report,
        string $statisticsCode,
        ReportQuery $query,
        \Mage_Sales_Model_Resource_Report_Collection_Abstract $collection,
        array $columns,
        array $countKeys = ['ordersCount'],
    ): array {
        $this->prepareCollection($collection, $query);
        $toValues = static function (array $row) use ($columns, $countKeys): array {
            $values = [];
            foreach ($columns as $key => $column) {
                $values[$key] = in_array($key, $countKeys, true) ? (int) ($row[$column] ?? 0) : self::amount($row[$column] ?? 0);
            }
            return $values;
        };

        $empty = $toValues([]);
        $byPeriod = [];
        $totals = $empty;
        foreach ($collection as $item) {
            $values = $toValues($item->getData());
            $label = $query->periodLabel($item->getData('period'));
            $byPeriod[$label] = isset($byPeriod[$label]) ? self::addValues($byPeriod[$label], $values) : $values;
            $totals = self::addValues($totals, $values);
        }

        return $this->envelope($report, $query, $totals, $this->periodList($query, $byPeriod, $empty), $statisticsCode);
    }

    /**
     * Build a report with a list of rows per period from an admin report collection.
     *
     * @param \Closure(array<string, mixed>): array<string, mixed> $toRow maps a collection row to a report row
     * @param list<string> $totalKeys the row keys that the totals add up
     * @param \Closure(array<string, mixed>, array<string, mixed>): int $compare sorts the rows of a period
     * @param array<string, mixed> $extra keys of the document that come before scope
     * @return array<string, mixed>
     */
    protected function rowReport(
        string $report,
        string $statisticsCode,
        ReportQuery $query,
        \Mage_Sales_Model_Resource_Report_Collection_Abstract $collection,
        \Closure $toRow,
        array $totalKeys,
        \Closure $compare,
        array $extra = [],
    ): array {
        $this->prepareCollection($collection, $query);
        // One row more than the maximum shows that the report is too long, without loading all rows
        $collection->setPageSize(self::MAX_ROWS + 1)->setCurPage(1);

        $byPeriod = [];
        $totals = array_fill_keys($totalKeys, 0);
        $count = 0;
        foreach ($collection as $item) {
            if (++$count > self::MAX_ROWS) {
                throw new BadRequestHttpException(sprintf('The report has more than %d rows. Use a shorter range.', self::MAX_ROWS));
            }
            $row = $toRow($item->getData());
            $byPeriod[$query->periodLabel($item->getData('period'))][] = $row;
            $totals = self::addValues($totals, array_intersect_key($row, $totals));
        }
        foreach ($byPeriod as $label => $rows) {
            usort($rows, $compare);
            $byPeriod[$label] = $rows;
        }

        return $this->envelope($report, $query, $totals, $this->periodList($query, $byPeriod, [], 'rows'), $statisticsCode, $extra);
    }

    /**
     * Apply the period type, the range, the stores and the order statuses of $query to an admin report collection.
     */
    protected function prepareCollection(\Mage_Sales_Model_Resource_Report_Collection_Abstract $collection, ReportQuery $query): void
    {
        $collection->setPeriod($query->periodType)
            ->setDateRange($query->from, $query->to)
            ->addStoreFilter($query->storeIds)
            ->addOrderStatusFilter($query->orderStatuses);
    }

    /**
     * Add the values of $values to $totals, key by key.
     *
     * @param array<string, int|float> $totals
     * @param array<string, int|float> $values
     * @return array<string, int|float>
     */
    protected static function addValues(array $totals, array $values): array
    {
        foreach ($values as $key => $value) {
            $totals[$key] = is_int($value) && is_int($totals[$key] ?? 0)
                ? ($totals[$key] ?? 0) + $value
                : round((float) ($totals[$key] ?? 0) + $value, 4);
        }
        return $totals;
    }
}
