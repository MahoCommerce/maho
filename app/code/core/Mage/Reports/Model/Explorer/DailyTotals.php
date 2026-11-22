<?php

/**
 * The daily totals of the orders: one row for each local day, store view, state, status, customer group, customer
 * type and new or returning customer, with the sum of each additive metric. A report on a long period reads them
 * instead of every order.
 *
 * The explorer engine fills the table from the Orders dataset, so a total has the same rules as the live report. An
 * order save marks its day as changed. Before a report reads the table, it marks the days of the orders that a script
 * changed without a save, and calculates the changed days again. A cron job builds the history after the totals are
 * turned on, and deletes them when they are turned off.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Definition\Aggregate;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Granularity;
use Maho\Report\Definition\Metric;
use Maho\Report\Formula;
use Maho\Report\Plan;
use Maho\Report\Planner;
use Maho\Report\Request;

class Mage_Reports_Model_Explorer_DailyTotals
{
    public const string DATASET = 'sales_orders';
    public const string DATE_FIELD = 'created_at';
    public const string XML_PATH_MIN_DAYS = 'reports/explorer/daily_totals_min_days';
    public const string FLAG_CODE = 'report_order_daily';
    /** The UTC time of the last check of the orders that changed, apart from the build state that only cron writes. */
    public const string CHECK_FLAG_CODE = 'report_order_daily_checked';
    public const string LOCK = 'report_order_daily';

    /** The fields that a row of the table keeps. A report can group and filter by these only. */
    public const array KEYS = ['store_id', 'state', 'status', 'customer_group_id', 'customer_type', 'customer_kind'];

    /** The days that one query of the build reads. */
    public const int CHUNK_DAYS = 31;

    /** The values of one insert: SQLite takes at most 32,766 parameters in a statement, MySQL and PostgreSQL 65,535. */
    public const int MAX_INSERT_VALUES = 30000;

    /** The days of history that one cron run builds. */
    public const int HISTORY_DAYS = 92;

    private ?Dataset $dataset = null;

    /** The shortest period, in days, that reads the daily totals. 0 turns them off. */
    public static function minDays(): int
    {
        return max(0, Mage::getStoreConfigAsInt(self::XML_PATH_MIN_DAYS, Mage_Core_Model_App::ADMIN_STORE_ID));
    }

    /**
     * The plan of a request on the daily totals, after the changed days of its periods are calculated again. Null
     * when the request needs the orders: a short period, a field or a metric that the table does not keep, or a
     * history that the cron job did not build yet.
     */
    public function planFor(Plan $plan, DateTimeImmutable $now): ?Plan
    {
        $minDays = self::minDays();
        $request = $plan->request;
        if ($minDays === 0 || $plan->dataset->code !== self::DATASET || $plan->dateField?->code !== self::DATE_FIELD
            || $plan->localFrom === null || $plan->localTo === null
            || $plan->localFrom->diff($plan->localTo)->days + 1 < $minDays
        ) {
            return null;
        }
        $granularity = $plan->periodDimension()?->granularity;
        if ($granularity === Granularity::Hour || $granularity === Granularity::HourOfDay) {
            return null;
        }
        $state = $this->state();
        if ($state['timezone'] !== $plan->timezone || !$this->covers($state, $plan)) {
            return null;
        }
        $daily = $this->dataset();
        if ($daily === null
            || ($plan->scope !== null && !$daily->hasScope($plan->scope->code))
            || array_any($plan->metrics, static fn(Metric $metric): bool => !$daily->hasMetric($metric->code))
            || array_any($request->dimensions, static fn($dimension): bool => !$daily->hasDimension($dimension->code))
            || array_any($request->filters, static fn($filter): bool => !$daily->hasField($filter->field))
        ) {
            return null;
        }

        $this->markUpdatedOrders($now);
        $bounds = array_filter([$plan->localBounds(), $plan->localBounds(true)]);
        $this->refresh(min(array_column($bounds, 0)), max(array_column($bounds, 1)));
        return new Planner()->plan($daily, $request, $now, $plan->timezone, $plan->firstDayOfWeek);
    }

    /**
     * The Orders dataset on the table of the daily totals: the same codes for the metrics, the groupings, the filters
     * and the order scopes, so that one request runs on both. Null without the Orders dataset.
     */
    public function dataset(): ?Dataset
    {
        if ($this->dataset !== null) {
            return $this->dataset;
        }
        $orders = Mage::getSingleton('reports/explorer_registry')->getDataset(self::DATASET);
        if ($orders === null) {
            return null;
        }
        $table = $this->table();
        $columns = array_keys(Mage::getSingleton('reports/explorer_registry')->getAdapter()->describeTable($table));

        $daily = new Dataset($orders->code, $orders->label, $orders->group, $orders->aclResource, $table, 'main', 'store_id');
        foreach (self::KEYS as $code) {
            if ($orders->hasField($code)) {
                $field = $orders->getField($code);
                $daily->addField(new Field($code, $field->label, $field->type, "main.{$code}", [], $field->personal, $field->labels, false, $field->options, $field->filterable));
            }
        }
        foreach (['is_new_customer' => 'new', 'is_returning' => 'returning'] as $code => $kind) {
            if ($orders->hasField($code)) {
                $daily->addField(new Field($code, $orders->getField($code)->label, FieldType::Boolean, "CASE WHEN main.customer_kind = '{$kind}' THEN 1 ELSE 0 END"));
            }
        }
        $date = $orders->getField(self::DATE_FIELD);
        $daily->addField(new Field(self::DATE_FIELD, $date->label, FieldType::Date, 'main.day', localDate: true));
        $daily->addDateField(self::DATE_FIELD, $orders->getDimension(\Maho\Report\Definition\Dimension::PERIOD)->label);

        foreach ($this->metricCodes($orders, $columns) as $code) {
            $metric = $orders->getMetric($code);
            $daily->addField(new Field("m_{$code}", $metric->label, FieldType::Decimal, "main.m_{$code}", filterable: false));
            $daily->addMetric(Metric::aggregate($code, $metric->label, Aggregate::Sum, "m_{$code}", $metric->format, null, $metric->hidden, $metric->higherIsBetter, $metric->description));
        }
        // A formula over the sums gives the same value from the daily rows, such as the average order value
        do {
            $added = false;
            foreach ($orders->getMetrics() as $metric) {
                if ($metric->formula !== null && !$daily->hasMetric($metric->code)
                    && array_all(Formula::parse($metric->formula)->references(), $daily->hasMetric(...))
                ) {
                    $daily->addMetric(Metric::formula($metric->code, $metric->label, $metric->formula, $metric->format, $metric->hidden, $metric->higherIsBetter, $metric->description));
                    $added = true;
                }
            }
        } while ($added);

        foreach ($orders->getDimensions() as $dimension) {
            if (!$dimension->isPeriod() && $daily->hasField($dimension->field)) {
                $daily->addDimension($dimension);
            }
        }
        foreach ($orders->getScopes() as $scope) {
            $daily->addScope($scope);
        }
        if ($orders->getDefaultScope() !== null) {
            $daily->setDefaultScope($orders->getDefaultScope());
        }
        return $this->dataset = $daily;
    }

    /** Mark the local day of an order, created at the UTC time $createdAt, as changed. */
    public function markChanged(?string $createdAt): void
    {
        $day = new DateTimeImmutable($createdAt ?? 'now', new DateTimeZone('UTC'))
            ->setTimezone(new DateTimeZone(Mage_Reports_Model_Explorer_Runner::timezone()))
            ->format('Y-m-d');
        $this->adapter()->insertOnDuplicate($this->changeTable(), ['day' => $day, 'changed_at' => Mage_Core_Model_Locale::nowUtc()], ['changed_at']);
    }

    /**
     * Calculate again the changed days from $from to $to, or every changed day. A day that changes again during the
     * calculation stays marked.
     */
    public function refresh(?string $from = null, ?string $to = null): void
    {
        $adapter = $this->adapter();
        $select = $adapter->select()->from($this->changeTable(), ['day', 'changed_at'])->order('day ASC');
        if ($from !== null && $to !== null) {
            $select->where('day >= ?', $from)->where('day <= ?', $to);
        }
        $changes = $adapter->fetchPairs($select);
        if ($changes === []) {
            return;
        }
        $lock = Mage::getSingleton('core/lock');
        $lock->acquire(self::LOCK, true);
        try {
            foreach ($this->ranges(array_map(static fn($day): string => substr((string) $day, 0, 10), array_keys($changes))) as [$start, $end]) {
                $this->build($start, $end);
            }
            foreach ($changes as $day => $changedAt) {
                $adapter->delete($this->changeTable(), ['day = ?' => $day, 'changed_at <= ?' => $changedAt]);
            }
        } finally {
            $lock->release(self::LOCK);
        }
    }

    /**
     * Calculate the totals of the local days from $from to $to again from the orders.
     */
    public function rebuild(string $from, string $to): void
    {
        $lock = Mage::getSingleton('core/lock');
        $lock->acquire(self::LOCK, true);
        try {
            $this->build($from, $to);
        } finally {
            $lock->release(self::LOCK);
        }
    }

    /**
     * The cron job: start again after a change of the time zone, mark the days of the orders that changed since the
     * last run, build the next part of the history, and calculate the changed days again.
     */
    public function update(DateTimeImmutable $now): void
    {
        $state = $this->state();
        if (self::minDays() === 0) {
            // Order saves do not mark their days while the totals are off, so turning them on again builds them anew
            if ($state['timezone'] !== null) {
                $this->reset();
            }
            return;
        }
        $timezone = Mage_Reports_Model_Explorer_Runner::timezone();
        if ($state['timezone'] !== $timezone) {
            $this->reset();
            $state = ['timezone' => $timezone, 'from' => null, 'complete' => false];
        }
        $this->markUpdatedOrders($now);

        if (!$state['complete']) {
            $today = $now->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
            $oldest = $this->oldestOrderDay($timezone) ?? $today;
            $end = $state['from'] === null ? $today : $this->shift($state['from'], -1);
            $start = max($oldest, $this->shift($end, -(self::HISTORY_DAYS - 1)));
            if ($start <= $end) {
                $this->rebuild($start, $end);
            }
            $state['from'] = min($start, $state['from'] ?? $start);
            $state['complete'] = $state['from'] <= $oldest;
        }
        $this->saveState($state);
        $this->refresh();
    }

    /**
     * Mark the days of the orders whose last change is after the previous check, also when a script changed them
     * without a save, as long as it set updated_at. The first check only records its time: the build reads the orders
     * after it.
     */
    public function markUpdatedOrders(DateTimeImmutable $now): void
    {
        $flag = Mage::getModel('core/flag', ['flag_code' => self::CHECK_FLAG_CODE])->loadSelf();
        $checkedAt = $flag->getFlagData();
        $scanStart = $now->setTimezone(new DateTimeZone('UTC'))->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
        if (is_string($checkedAt)) {
            $adapter = $this->adapter();
            $timezone = Mage_Reports_Model_Explorer_Runner::timezone();
            $orderTable = Mage::getSingleton('core/resource')->getTableName('sales/order');
            $created = $adapter->fetchCol($adapter->select()->distinct()->from($orderTable, ['created_at'])->where('updated_at >= ?', $checkedAt));
            foreach (array_unique(array_map(fn($date): string => $this->localDay((string) $date, $timezone), $created)) as $day) {
                $adapter->insertOnDuplicate($this->changeTable(), ['day' => $day, 'changed_at' => $scanStart], ['changed_at']);
            }
        }
        $flag->setFlagData($scanStart)->save();
    }

    /** Delete the totals, the changed days and the state, so that the next cron run builds them anew. */
    public function reset(): void
    {
        $adapter = $this->adapter();
        $adapter->delete($this->table());
        $adapter->delete($this->changeTable());
        foreach ([self::FLAG_CODE, self::CHECK_FLAG_CODE] as $code) {
            $flag = Mage::getModel('core/flag', ['flag_code' => $code])->loadSelf();
            if ($flag->getId()) {
                $flag->delete();
            }
        }
    }

    /**
     * The state of the build: the time zone of its days, the first day that it holds, and whether it holds every day
     * of the orders.
     *
     * @return array{timezone: ?string, from: ?string, complete: bool}
     */
    public function state(): array
    {
        $data = Mage::getModel('core/flag', ['flag_code' => self::FLAG_CODE])->loadSelf()->getFlagData();
        $data = is_array($data) ? $data : [];
        return [
            'timezone' => isset($data['timezone']) ? (string) $data['timezone'] : null,
            'from' => isset($data['from']) ? (string) $data['from'] : null,
            'complete' => (bool) ($data['complete'] ?? false),
        ];
    }

    /** @param array{timezone: ?string, from: ?string, complete: bool} $state */
    public function saveState(array $state): void
    {
        Mage::getModel('core/flag', ['flag_code' => self::FLAG_CODE])->loadSelf()->setFlagData($state)->save();
    }

    /** @param array{timezone: ?string, from: ?string, complete: bool} $state */
    private function covers(array $state, Plan $plan): bool
    {
        if ($state['complete']) {
            return true;
        }
        if ($state['from'] === null) {
            return false;
        }
        $bounds = array_filter([$plan->localBounds(), $plan->localBounds(true)]);
        return min(array_column($bounds, 0)) >= $state['from'];
    }

    /** Write the totals of the local days from $from to $to. The caller holds the lock. */
    private function build(string $from, string $to): void
    {
        $orders = Mage::getSingleton('reports/explorer_registry')->getDataset(self::DATASET);
        $daily = $this->dataset();
        if ($orders === null || $daily === null) {
            return;
        }
        $metrics = array_values(array_filter(
            array_keys($daily->getMetrics()),
            static fn(string $code): bool => $daily->getMetric($code)->formula === null,
        ));
        $dimensions = array_values(array_filter(self::KEYS, $orders->hasDimension(...)));
        $runner = Mage::getSingleton('reports/explorer_runner');
        $adapter = $this->adapter();

        for ($start = $from; $start <= $to; $start = $this->shift($end, 1)) {
            $end = min($to, $this->shift($start, self::CHUNK_DAYS - 1));
            $rows = $runner->groupedRows(Request::fromArray([
                'dataset' => self::DATASET,
                'metrics' => $metrics,
                'dimensions' => ['period:day', ...$dimensions],
                'scope' => 'all',
                'dateField' => self::DATE_FIELD,
                'from' => $start,
                'to' => $end,
            ]));
            $insert = [];
            foreach ($rows as $row) {
                $record = ['day' => (string) $row['period']];
                foreach ($dimensions as $code) {
                    $record[$code] = $row[$code];
                }
                foreach ($metrics as $code) {
                    $record["m_{$code}"] = $row[$code] ?? 0;
                }
                $insert[] = $record;
            }
            $adapter->beginTransaction();
            try {
                $adapter->delete($this->table(), ['day >= ?' => $start, 'day <= ?' => $end]);
                // A large store has more values in one range than a statement of the database takes
                $rowsPerInsert = $insert === [] ? 1 : max(1, intdiv(static::MAX_INSERT_VALUES, count($insert[0])));
                foreach (array_chunk($insert, $rowsPerInsert) as $rows) {
                    $adapter->insertMultiple($this->table(), $rows);
                }
                $adapter->commit();
            } catch (Throwable $e) {
                $adapter->rollBack();
                throw $e;
            }
        }
    }

    /**
     * The sums and counts of the Orders dataset that the table has a column for.
     *
     * @param list<string> $columns
     * @return list<string>
     */
    private function metricCodes(Dataset $orders, array $columns): array
    {
        $codes = [];
        foreach ($orders->getMetrics() as $metric) {
            if ($metric->formula === null && in_array($metric->aggregate, [Aggregate::Sum, Aggregate::Count], true)
                && in_array("m_{$metric->code}", $columns, true)
            ) {
                $codes[] = $metric->code;
            }
        }
        return $codes;
    }

    /**
     * Group the days into ranges of days that follow each other.
     *
     * @param list<string> $days "Y-m-d", in order
     * @return list<array{0: string, 1: string}>
     */
    private function ranges(array $days): array
    {
        $ranges = [];
        foreach ($days as $day) {
            $last = array_key_last($ranges);
            if ($last !== null && $this->shift($ranges[$last][1], 1) === $day) {
                $ranges[$last][1] = $day;
            } else {
                $ranges[] = [$day, $day];
            }
        }
        return $ranges;
    }

    private function oldestOrderDay(string $timezone): ?string
    {
        $adapter = $this->adapter();
        $oldest = $adapter->fetchOne($adapter->select()->from(Mage::getSingleton('core/resource')->getTableName('sales/order'), [new \Maho\Db\Expr('MIN(created_at)')]));
        return $oldest ? $this->localDay((string) $oldest, $timezone) : null;
    }

    private function localDay(string $utc, string $timezone): string
    {
        return new DateTimeImmutable($utc, new DateTimeZone('UTC'))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
    }

    private function shift(string $day, int $days): string
    {
        return new DateTimeImmutable($day, new DateTimeZone('UTC'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }

    private function table(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('reports/order_daily');
    }

    private function changeTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('reports/order_daily_change');
    }

    private function adapter(): \Maho\Db\Adapter\AdapterInterface
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }
}
