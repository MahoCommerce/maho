<?php

/**
 * Build the ranked product rows of each period from the daily, monthly and yearly tables of a report.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

trait RankedProductsTrait
{
    /**
     * Each period has at most this number of rows, the same as in the admin.
     */
    public const ROWS_PER_PERIOD = 5;

    /**
     * Return the totals and the periods of a ranked product report.
     *
     * The sources are the same as in the admin: the table of the period type for the periods that the
     * range covers completely, and the daily table for a month or a year that the range cuts. A row of
     * a complete period counts when the product is in the top 5 of its store, and the rows of the stores
     * of the scope are added together.
     *
     * @param array{day: string, month: string, year: string} $tables the table alias of each period type
     * @param string $column the column of the ranked number, for example qty_ordered
     * @param string $key the key of the ranked number in a row, for example qtyOrdered
     * @return array{totals: array<string, float>, periods: list<array<string, mixed>>}
     */
    protected function rankedProducts(ReportQuery $query, array $tables, string $column, string $key, bool $withoutComposite): array
    {
        $full = [];
        $rowsByPeriod = [];
        foreach ($query->periodLabels() as $label) {
            [$periodStart, $periodEnd] = self::periodBounds($label, $query->periodType);
            $start = max($periodStart, $query->from);
            $end = min($periodEnd, $query->to);
            if ($start === $periodStart && $end === $periodEnd) {
                $full[] = [$periodStart, $periodEnd];
                continue;
            }
            $select = $this->rankedSelect($query, $tables['day'], $column, $withoutComposite, $start, $end)
                ->group('product_id')
                ->order(new \Maho\Db\Expr("SUM({$column}) DESC"))
                ->order('product_id ASC')
                ->limit(self::ROWS_PER_PERIOD);
            $rows = \Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($select);
            if ($rows !== []) {
                $rowsByPeriod[$label] = $rows;
            }
        }
        if ($full !== []) {
            // The period of a row of the monthly and the yearly tables can be any day of its month or year
            $period = \Mage::getSingleton('core/resource')->getConnection('core_read')->getDateFormatSql('period', match ($query->periodType) {
                ReportQuery::PERIOD_YEAR => '%Y',
                ReportQuery::PERIOD_MONTH => '%Y-%m',
                default => '%Y-%m-%d',
            });
            $select = $this->rankedSelect($query, $tables[$query->periodType], $column, $withoutComposite, $full[0][0], $full[count($full) - 1][1])
                ->columns(['period' => $period])
                ->where('rating_pos <= ?', self::ROWS_PER_PERIOD)
                ->group([$period, 'product_id']);
            foreach (\Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($select) as $row) {
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
        $total = 0.0;
        foreach ($rowsByPeriod as $period => $rows) {
            usort($rows, static fn(array $a, array $b): int => [(float) $b['value'], (int) $a['product_id']] <=> [(float) $a['value'], (int) $b['product_id']]);
            $ranked = [];
            foreach (array_slice($rows, 0, self::ROWS_PER_PERIOD) as $index => $row) {
                $productId = (int) $row['product_id'];
                $ranked[] = [
                    'rank' => $index + 1,
                    'productId' => $productId,
                    'sku' => $skus[$productId] ?? null,
                    'name' => (string) $row['product_name'],
                    'price' => ReportProviderBase::amount($row['product_price']),
                    $key => ReportProviderBase::quantity($row['value']),
                ];
                $total += (float) $row['value'];
            }
            $byPeriod[$period] = $ranked;
        }

        return ['totals' => [$key => round($total, 4)], 'periods' => $this->periodList($query, $byPeriod, [], 'rows')];
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
            ReportQuery::PERIOD_MONTH => ["{$label}-01", new \DateTimeImmutable("{$label}-01")->format('Y-m-t')],
            default => [$label, $label],
        };
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

    private function rankedSelect(ReportQuery $query, string $table, string $column, bool $withoutComposite, string $from, string $to): \Maho\Db\Select
    {
        $resource = \Mage::getSingleton('core/resource');
        $select = $resource->getConnection('core_read')->select()
            ->from($resource->getTableName($table), [
                'product_id' => 'product_id',
                'value' => new \Maho\Db\Expr("SUM({$column})"),
                'product_name' => new \Maho\Db\Expr('MAX(product_name)'),
                'product_price' => new \Maho\Db\Expr('MAX(product_price)'),
            ])
            ->where('period >= ?', $from)
            ->where('period <= ?', $to)
            ->where('store_id IN (?)', $query->storeIds);
        if ($withoutComposite) {
            $select->where('product_type_id NOT IN (?)', \Mage_Catalog_Model_Product_Type::getCompositeTypes());
        }
        return $select;
    }
}
