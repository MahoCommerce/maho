<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class BestsellersReportProvider extends ReportProviderBase
{
    public const ROWS_PER_PERIOD = 5;

    /**
     * The aggregated bestseller table of each period type.
     */
    public const TABLES = [
        ReportQuery::PERIOD_DAY => 'sales/bestsellers_aggregated_daily',
        ReportQuery::PERIOD_MONTH => 'sales/bestsellers_aggregated_monthly',
        ReportQuery::PERIOD_YEAR => 'sales/bestsellers_aggregated_yearly',
    ];

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user);

        // The same sources as the admin report: the table of the period type for the periods that the
        // range covers completely, and the daily table for a month or a year that the range cuts.
        $full = [];
        $rowsByPeriod = [];
        foreach ($query->periodLabels() as $label) {
            [$periodStart, $periodEnd] = self::periodBounds($label, $query->periodType);
            $start = max($periodStart, $query->from);
            $end = min($periodEnd, $query->to);
            if ($start === $periodStart && $end === $periodEnd) {
                $full[] = [$periodStart, $periodEnd];
            } else {
                $rows = $this->partialPeriodRows($query, $start, $end);
                if ($rows !== []) {
                    $rowsByPeriod[$label] = $rows;
                }
            }
        }
        if ($full !== []) {
            foreach ($this->fullPeriodRows($query, $full[0][0], $full[count($full) - 1][1]) as $row) {
                $rowsByPeriod[$query->periodLabel($row['period'])][] = $row;
            }
        }

        $productIds = [];
        foreach ($rowsByPeriod as $rows) {
            foreach ($rows as $row) {
                $productIds[(int) $row['product_id']] = (int) $row['product_id'];
            }
        }
        $skus = self::skusOf(array_values($productIds));

        $byPeriod = [];
        $totalQty = 0.0;
        foreach ($rowsByPeriod as $period => $rows) {
            usort($rows, static fn(array $a, array $b): int => [(float) $b['qty_ordered'], (int) $a['product_id']] <=> [(float) $a['qty_ordered'], (int) $b['product_id']]);
            $ranked = [];
            foreach (array_slice($rows, 0, self::ROWS_PER_PERIOD) as $index => $row) {
                $productId = (int) $row['product_id'];
                $ranked[] = [
                    'rank' => $index + 1,
                    'productId' => $productId,
                    'sku' => $skus[$productId] ?? null,
                    'name' => (string) $row['product_name'],
                    'price' => self::amount($row['product_price']),
                    'qtyOrdered' => self::quantity($row['qty_ordered']),
                ];
                $totalQty += (float) $row['qty_ordered'];
            }
            $byPeriod[$period] = $ranked;
        }

        return $this->envelope(
            'products-bestsellers',
            $query,
            ['qtyOrdered' => round($totalQty, 4)],
            $this->periodList($query, $byPeriod, [], 'rows'),
            'bestsellers',
        );
    }

    /**
     * The first and the last day of the period with the label $label.
     *
     * @return array{string, string}
     */
    public static function periodBounds(string $label, string $periodType): array
    {
        return match ($periodType) {
            ReportQuery::PERIOD_YEAR => ["{$label}-01-01", "{$label}-12-31"],
            ReportQuery::PERIOD_MONTH => ["{$label}-01", (new \DateTimeImmutable("{$label}-01"))->format('Y-m-t')],
            default => [$label, $label],
        };
    }

    /**
     * The rows of the complete periods from $from to $to. Like the admin, a row counts when the product
     * is in the top 5 of its store, and the rows of the stores of the scope are added together. The period
     * of a row of the monthly and the yearly tables can be any day of its month or year.
     *
     * @return list<array<string, mixed>>
     */
    private function fullPeriodRows(ReportQuery $query, string $from, string $to): array
    {
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName(self::TABLES[$query->periodType]), [
                'period' => 'period',
                'product_id' => 'product_id',
                'qty_ordered' => new \Maho\Db\Expr('SUM(qty_ordered)'),
                'product_name' => new \Maho\Db\Expr('MAX(product_name)'),
                'product_price' => new \Maho\Db\Expr('MAX(product_price)'),
            ])
            ->where('period >= ?', $from)
            ->where('period <= ?', $to)
            ->where('rating_pos <= ?', self::ROWS_PER_PERIOD)
            ->where('store_id IN (?)', $query->storeIds)
            ->where('product_type_id NOT IN (?)', \Mage_Catalog_Model_Product_Type::getCompositeTypes())
            ->group(['period', 'product_id']);
        return $adapter->fetchAll($select);
    }

    /**
     * The 5 products with the largest quantity from $from to $to in the daily table.
     *
     * @return list<array<string, mixed>>
     */
    private function partialPeriodRows(ReportQuery $query, string $from, string $to): array
    {
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName(self::TABLES[ReportQuery::PERIOD_DAY]), [
                'product_id' => 'product_id',
                'qty_ordered' => new \Maho\Db\Expr('SUM(qty_ordered)'),
                'product_name' => new \Maho\Db\Expr('MAX(product_name)'),
                'product_price' => new \Maho\Db\Expr('MAX(product_price)'),
            ])
            ->where('period >= ?', $from)
            ->where('period <= ?', $to)
            ->where('store_id IN (?)', $query->storeIds)
            ->where('product_type_id NOT IN (?)', \Mage_Catalog_Model_Product_Type::getCompositeTypes())
            ->group('product_id')
            ->order(new \Maho\Db\Expr('SUM(qty_ordered) DESC'))
            ->order('product_id ASC')
            ->limit(self::ROWS_PER_PERIOD);
        return $adapter->fetchAll($select);
    }

    /**
     * @param list<int> $productIds
     * @return array<int, string>
     */
    public static function skusOf(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('catalog/product'), ['entity_id', 'sku'])
            ->where('entity_id IN (?)', $productIds);
        $skus = [];
        foreach ($adapter->fetchPairs($select) as $id => $sku) {
            $skus[(int) $id] = (string) $sku;
        }
        return $skus;
    }
}
