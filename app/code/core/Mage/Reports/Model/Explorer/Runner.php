<?php

/**
 * Run a report request: check it, build its SQL, read the rows, add the labels and keep the result in the cache.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Context;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Granularity;
use Maho\Report\Plan;
use Maho\Report\Planner;
use Maho\Report\PostProcessor;
use Maho\Report\Request;
use Maho\Report\SqlBuilder;

class Mage_Reports_Model_Explorer_Runner
{
    public const CACHE_TYPE = 'reports_explorer';
    public const CACHE_TAG = 'REPORTS_EXPLORER';
    public const XML_PATH = 'reports/explorer/';
    public const EXPORT_PAGE_SIZE = 5000;

    /**
     * Run a request and return the result envelope.
     *
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input
     */
    public function run(Request $request, Mage_Reports_Model_Explorer_Access $access): array
    {
        return Mage::app()->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, function () use ($request, $access): array {
            [$plan, $context] = $this->prepare($request, $access, self::maxRows());

            $cacheKey = $this->cacheKey($plan, $access);
            $useCache = Mage::app()->useCache(self::CACHE_TYPE);
            if ($useCache && ($cached = Mage::app()->loadCache($cacheKey)) !== false) {
                return ['cached' => true] + Mage::helper('core')->jsonDecode($cached);
            }

            $result = $this->execute($plan, $context);
            if ($useCache) {
                Mage::app()->saveCache(
                    Mage::helper('core')->jsonEncode($result),
                    $cacheKey,
                    [self::CACHE_TAG],
                    $this->cacheLifetime($plan),
                );
            }
            return ['cached' => false] + $result;
        });
    }

    public const RECORDS_LIMIT = 50;

    /**
     * List the rows behind the numbers of a request: the orders, items, customers or carts that the report counts.
     * The metrics, the dimensions and the sort of the request do not apply. Each row has the ID of its record, so the
     * transport can link to the record.
     *
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input
     */
    public function records(Request $request, Mage_Reports_Model_Explorer_Access $access, ?string $sort = null, int $limit = self::RECORDS_LIMIT, int $offset = 0): array
    {
        return Mage::app()->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, function () use ($request, $access, $sort, $limit, $offset): array {
            $helper = Mage::helper('reports');
            $dataset = Mage::getSingleton('reports/explorer_registry')->getDataset($request->dataset);
            $records = $dataset?->getRecords();
            $errors = new Mage_Core_Exception_Input($helper->__('The rows of this report cannot be listed.'));
            if ($dataset !== null && $access->allowsDataset($dataset) && !$access->allowsRecords($dataset)) {
                $errors->addError('dataset', $records === null
                    ? $helper->__('This dataset has no rows to list.')
                    : $helper->__('You may not read the rows of this dataset.'));
            }
            $fields = [];
            foreach ($records->fields ?? [] as $code) {
                if ($access->personalData || !$dataset?->getField($code)->personal) {
                    $fields[] = $code;
                }
            }
            $descending = $sort === null || str_starts_with($sort, '-');
            $sortField = $sort === null ? null : ltrim($sort, '-');
            if ($sortField !== null && !in_array($sortField, $fields, true)) {
                $errors->addError('sort', $helper->__('The rows cannot be sorted by "%s".', $sortField));
            }
            if ($limit < 1 || $limit > self::maxRows()) {
                $errors->addError('limit', $helper->__('The limit must be between 1 and %d.', self::maxRows()));
            }
            $errors->throwIfErrors();

            // The validator checks the period, the scope, the stores and the filters as for a report of one metric
            $metric = $dataset === null ? [] : [array_key_first(array_filter($dataset->getMetrics(), static fn($m): bool => !$m->hidden))];
            [$plan, $context] = $this->prepare($request->with(['metrics' => $metric, 'dimensions' => [], 'sort' => null, 'limit' => 1, 'offset' => 0]), $access, self::maxRows());
            if ($records === null || $dataset === null) {
                throw new LogicException('The validator accepted a dataset without records.');
            }

            $builder = new SqlBuilder();
            $adapter = $context->adapter;
            $raw = $adapter->fetchAll($builder->records($plan, $context, $sortField, $descending, $limit, $offset));
            $total = (int) $adapter->fetchOne($builder->recordCount($plan, $context));

            $columns = $labels = [];
            $resolver = Mage::getSingleton('reports/explorer_labels');
            foreach ($fields as $code) {
                $field = $dataset->getField($code);
                $columns[] = ['key' => $code, 'label' => $field->label, 'format' => match ($field->type) {
                    FieldType::Money => 'money',
                    FieldType::Integer, FieldType::Decimal => 'number',
                    FieldType::Date => 'date',
                    FieldType::Boolean => 'boolean',
                    default => 'text',
                }];
                $index = array_search($code, $records->fields, true);
                $values = array_column($raw, "r{$index}");
                $labels[$code] = $field->options + ($field->labels === null ? [] : $resolver->resolve($field->labels, $values));
            }
            $rows = [];
            foreach ($raw as $row) {
                $values = $rowLabels = [];
                foreach ($fields as $code) {
                    $value = $row['r' . array_search($code, $records->fields, true)] ?? null;
                    $values[$code] = $value;
                    if ((is_int($value) || is_string($value)) && isset($labels[$code][$value])) {
                        $rowLabels[$code] = $labels[$code][$value];
                    }
                }
                $rows[] = ['id' => $row['record_id'], 'values' => $values, 'labels' => $rowLabels];
            }

            $local = $plan->localBounds();
            return [
                'dataset' => $dataset->code,
                'request' => $request->toArray(),
                'columns' => $columns,
                'rows' => $rows,
                'totalRows' => $total,
                'route' => $records->route,
                'routeParam' => $records->routeParam,
                'period' => $local === null ? null : ['from' => $local[0], 'to' => $local[1], 'timezone' => $plan->timezone],
                'currency' => (string) Mage::app()->getBaseCurrencyCode(),
            ];
        });
    }

    /**
     * Read every row of a request, page by page, for an export. The rows have their labels.
     *
     * @param \Closure(array<string, mixed>, array<string, mixed>): void $onRow receives each row and the envelope
     * @return int the number of rows
     */
    public function each(Request $request, Mage_Reports_Model_Explorer_Access $access, int $maxRows, \Closure $onRow): int
    {
        return Mage::app()->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, function () use ($request, $access, $maxRows, $onRow): int {
            $count = 0;
            $offset = 0;
            do {
                $page = $request->with(['limit' => min(self::EXPORT_PAGE_SIZE, $maxRows - $count), 'offset' => $offset]);
                [$plan, $context] = $this->prepare($page, $access, max($maxRows, self::EXPORT_PAGE_SIZE));
                $result = $this->execute($plan, $context);
                foreach ($result['rows'] as $row) {
                    $onRow($row, $result);
                    $count++;
                }
                $offset += count($result['rows']);
                $more = $result['totalRows'] > $offset && $count < $maxRows && $result['rows'] !== [];
            } while ($more);
            return $count;
        });
    }

    /**
     * The SQL of each statement of a request, with its time and its number of rows.
     *
     * @return list<array{label: string, sql: string, milliseconds: float, rows: int}>
     */
    public function explain(Request $request, Mage_Reports_Model_Explorer_Access $access): array
    {
        return Mage::app()->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, function () use ($request, $access): array {
            [$plan, $context] = $this->prepare($request, $access, self::maxRows());
            $builder = new SqlBuilder();
            $statements = [
                'rows' => $builder->rows($plan, $context, false, $plan->request->limit, $plan->request->offset),
                'totals' => $builder->totals($plan, $context),
            ];
            if ($plan->hasComparison()) {
                $statements['compare rows'] = $builder->rows($plan, $context, true);
                $statements['compare totals'] = $builder->totals($plan, $context, true);
            }
            $explained = [];
            foreach ($statements as $label => $select) {
                $start = hrtime(true);
                $rows = $context->adapter->fetchAll($select);
                $explained[] = [
                    'label' => $label,
                    'sql' => $select->assemble(),
                    'milliseconds' => round((hrtime(true) - $start) / 1e6, 1),
                    'rows' => count($rows),
                ];
            }
            return $explained;
        });
    }

    /**
     * @return array{0: Plan, 1: Context}
     * @throws Mage_Core_Exception_Input
     */
    private function prepare(Request $request, Mage_Reports_Model_Explorer_Access $access, int $maxRows): array
    {
        $registry = Mage::getSingleton('reports/explorer_registry');
        $dataset = $registry->getDataset($request->dataset);
        $request = Mage::getSingleton('reports/explorer_validator')->validate($request, $dataset, $access, $maxRows);
        if (!$dataset instanceof Dataset) {
            throw new LogicException('The validator accepted an unknown dataset.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $plan = new Planner()->plan($dataset, $request, $now, self::timezone(), self::firstDayOfWeek());

        $period = $plan->periodDimension();
        if ($period !== null && count($plan->dimensions) === 1 && $plan->localFrom !== null && $plan->localTo !== null) {
            $buckets = count(\Maho\Report\Buckets::keys($period->granularity ?? Granularity::Day, $plan->localFrom, $plan->localTo, $plan->firstDayOfWeek));
            if ($request->limit < $buckets || $request->offset !== 0) {
                $plan = new Planner()->plan($dataset, $request->with(['limit' => $buckets, 'offset' => 0]), $now, self::timezone(), self::firstDayOfWeek());
            }
        }

        return [$plan, $this->context($plan, $registry->getAdapter(), $now)];
    }

    private function context(Plan $plan, \Maho\Db\Adapter\AdapterInterface $adapter, DateTimeImmutable $now): Context
    {
        $bounds = array_filter([$plan->utcBounds(), $plan->utcBounds(true)]);
        $from = $bounds === [] ? null : min(array_column($bounds, 0));
        $to = $bounds === [] ? null : max(array_column($bounds, 1));
        $resource = Mage::getResourceSingleton('sales/report_order');
        $localTime = static fn(string $column): string => (string) $resource->getStoreTZOffsetQuery(
            $plan->dataset->table,
            $column,
            $from ?? '2000-01-01 00:00:00',
            $to ?? $now->format('Y-m-d H:i:s'),
            Mage_Core_Model_App::ADMIN_STORE_ID,
            true,
        );

        $settings = [];
        foreach (['dead_stock_days', 'abandoned_after_hours', 'recent_cart_hours'] as $key) {
            $settings[$key] = max(1, (int) Mage::getStoreConfig(self::XML_PATH . $key));
        }
        $settings['report_attributes'] = array_values(array_filter(explode(',', (string) Mage::getStoreConfig(self::XML_PATH . 'report_attributes'))));

        return new Context($adapter, $localTime, $now, $plan->timezone, $plan->firstDayOfWeek, null, null, $settings, $plan->dateField?->code);
    }

    /** @return array<string, mixed> */
    private function execute(Plan $plan, Context $context): array
    {
        $adapter = $context->adapter;
        $builder = new SqlBuilder();
        $limit = $plan->request->limit;
        $offset = $plan->request->offset;

        $rows = $adapter->fetchAll($builder->rows($plan, $context, false, $limit + 1, $offset));
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $totalRows = $hasMore || $offset > 0
            ? (int) $adapter->fetchOne($builder->count($plan, $context))
            : count($rows);
        $totals = $adapter->fetchRow($builder->totals($plan, $context)) ?: [];

        $compareRows = $compareTotals = null;
        if ($plan->hasComparison()) {
            $compareRows = $adapter->fetchAll($builder->rows($plan, $context, true));
            $compareTotals = $adapter->fetchRow($builder->totals($plan, $context, true)) ?: [];
        }

        $result = new PostProcessor()->process($plan, $rows, $totals, $totalRows, $compareRows, $compareTotals);
        $result['rows'] = $this->addLabels($plan, $result['rows']);

        $helper = Mage::helper('reports');
        $warnings = [];
        foreach ($result['warnings'] as $warning) {
            $warnings[] = match ($warning) {
                PostProcessor::WARNING_NON_ADDITIVE => $helper->__('A record can appear in several rows of this dimension, so the rows do not add up to the total.'),
                default => $warning,
            };
        }
        if ($totalRows > self::maxRows()) {
            $warnings[] = $helper->__('The report has %d rows. The page shows at most %d, so export the rows to read all of them.', $totalRows, self::maxRows());
        }

        $local = $plan->localBounds();
        $compare = $plan->localBounds(true);
        return [
            'dataset' => $plan->dataset->code,
            'request' => $plan->request->toArray(),
            'columns' => $this->columns($plan),
            'rows' => $result['rows'],
            'totals' => $result['totals'],
            'totalRows' => $result['totalRows'],
            'warnings' => $warnings,
            'period' => $local === null ? null : ['from' => $local[0], 'to' => $local[1], 'timezone' => $plan->timezone],
            'comparePeriod' => $compare === null ? null : ['from' => $compare[0], 'to' => $compare[1]],
            'currency' => (string) Mage::app()->getBaseCurrencyCode(),
            'generatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function columns(Plan $plan): array
    {
        $columns = [];
        foreach ($plan->dimensions as $dimension) {
            $columns[] = [
                'key' => $dimension->dimension->code,
                'label' => $dimension->dimension->isPeriod() ? $dimension->field->label : $dimension->dimension->label,
                'kind' => 'dimension',
                'granularity' => $dimension->granularity?->value,
            ];
        }
        foreach ($plan->metrics as $metric) {
            $columns[] = [
                'key' => $metric->code,
                'label' => $metric->label,
                'kind' => 'metric',
                'format' => $metric->format->value,
                'higherIsBetter' => $metric->higherIsBetter,
            ];
        }
        return $columns;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function addLabels(Plan $plan, array $rows): array
    {
        $labels = Mage::getSingleton('reports/explorer_labels');
        $none = Mage::helper('reports')->__('(none)');
        foreach ($plan->dimensions as $dimension) {
            $code = $dimension->dimension->code;
            $map = [];
            if ($dimension->granularity === Granularity::DayOfWeek) {
                $map = $labels->resolve('weekday', range(0, 6));
            } elseif ($dimension->granularity === null) {
                $map = $dimension->field->options;
                $resolver = $dimension->dimension->labels ?? $dimension->field->labels;
                if ($resolver !== null) {
                    $map += $labels->resolve($resolver, array_column(array_column($rows, 'dimensions'), $code));
                }
            }
            foreach ($rows as $index => $row) {
                $value = $row['dimensions'][$code] ?? null;
                if ($value === null || $value === '') {
                    $rows[$index]['labels'][$code] = $none;
                } elseif ((is_int($value) || is_string($value)) && isset($map[$value])) {
                    $rows[$index]['labels'][$code] = $map[$value];
                } elseif (!isset($row['labels'][$code])) {
                    $rows[$index]['labels'][$code] = (string) $value;
                }
            }
        }
        return $rows;
    }

    private function cacheKey(Plan $plan, Mage_Reports_Model_Explorer_Access $access): string
    {
        return 'REPORT_EXPLORER_' . hash('sha256', Mage::helper('core')->jsonEncode([
            $plan->request->toArray(),
            $plan->localBounds(),
            $plan->localBounds(true),
            $plan->timezone,
            $plan->firstDayOfWeek,
            $access->toArray(),
            Mage::app()->getLocale()->getLocaleCode(),
        ]));
    }

    private function cacheLifetime(Plan $plan): int
    {
        $open = max(60, (int) Mage::getStoreConfig(self::XML_PATH . 'cache_lifetime_open'));
        $closed = max($open, (int) Mage::getStoreConfig(self::XML_PATH . 'cache_lifetime_closed'));
        if ($plan->dataset->volatile || $plan->localTo === null) {
            return $open;
        }
        $today = new DateTimeImmutable('now', new DateTimeZone($plan->timezone))->format('Y-m-d');
        return $plan->localTo->format('Y-m-d') < $today ? $closed : $open;
    }

    /** The time zone of the default scope. Every classic report uses it for its periods. */
    public static function timezone(): string
    {
        return (string) (Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, Mage_Core_Model_App::ADMIN_STORE_ID)
            ?: Mage_Core_Model_Locale::DEFAULT_TIMEZONE);
    }

    /** The first day of the week of the default scope: 0 for Sunday to 6 for Saturday. */
    public static function firstDayOfWeek(): int
    {
        return ((int) Mage::getStoreConfig('general/locale/firstday', Mage_Core_Model_App::ADMIN_STORE_ID)) % 7;
    }

    public static function maxRows(): int
    {
        return max(1, (int) Mage::getStoreConfig(self::XML_PATH . 'max_rows'));
    }
}
