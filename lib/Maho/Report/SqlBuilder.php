<?php

/**
 * Build the SQL of a report plan with Maho\\Db\\Select: the rows, the totals and the row count.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Db\Expr;
use Maho\Db\Select;
use Maho\Report\Definition\Aggregate;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Granularity;
use Maho\Report\Definition\Join;
use Maho\Report\Definition\JoinType;
use Maho\Report\Definition\Metric;

final class SqlBuilder
{
    /** The rows of the plan, grouped by its dimensions, sorted and paged. */
    public function rows(Plan $plan, Context $context, bool $compare = false, ?int $limit = null, int $offset = 0): Select
    {
        $context = $this->contextFor($plan, $context, $compare);
        $select = $this->base($plan, $context, $compare, true);

        $groups = [];
        foreach ($plan->dimensions as $dimension) {
            $sql = $this->dimensionSql($dimension, $context, $plan->firstDayOfWeek);
            $select->columns([$dimension->alias => new Expr($sql)]);
            // The alias, not the expression: MySQL refuses to group by an expression with a correlated subquery
            $groups[] = $dimension->alias;
        }
        $this->addMetrics($select, $plan, $context);
        $metricCodes = array_map(static fn(Metric $metric): string => $metric->code, $plan->metrics);
        foreach ($this->totalParts($plan) ?? [] as $code => $part) {
            if (!in_array($code, $metricCodes, true)) {
                $select->columns(['m_' . $code => new Expr($this->metricSql($plan->dataset, $part, $context))]);
            }
        }
        if ($groups !== []) {
            $select->group($groups);
        }

        $order = [];
        $sortKey = $plan->request->sortKey;
        if ($sortKey !== null) {
            $alias = $this->aliasOf($plan, $sortKey);
            if ($alias !== null) {
                $order[] = new Expr($alias . ($plan->request->sortDescending ? ' DESC' : ' ASC'));
            }
        } elseif (($period = $plan->periodDimension()) !== null) {
            $order[] = new Expr($period->alias . ' ASC');
        } elseif ($plan->metrics !== []) {
            $order[] = new Expr('m_' . $plan->metrics[0]->code . ' DESC');
        }
        foreach ($plan->dimensions as $dimension) {
            $order[] = new Expr($dimension->alias . ' ASC');
        }
        if ($order !== []) {
            $select->order($order);
        }
        if ($limit !== null) {
            $select->limit($limit, $offset);
        }
        return $select;
    }

    /**
     * One row with the metrics of the whole period. It joins no table that only a dimension reads, so a dimension that
     * repeats a record in several rows does not repeat it in the totals.
     */
    public function totals(Plan $plan, Context $context, bool $compare = false): Select
    {
        $context = $this->contextFor($plan, $context, $compare);
        $select = $this->base($plan, $context, $compare, false);
        $this->addMetrics($select, $plan, $context);
        return $select;
    }

    /**
     * The sums and counts whose columns in the rows give the totals of the plan, or null when the totals need their own
     * query: a metric is a distinct count or an average, or a grouping repeats a record or leaves one out.
     *
     * @return array<string, Metric>|null
     */
    public function totalParts(Plan $plan): ?array
    {
        foreach ($plan->dimensions as $dimension) {
            if ($dimension->dimension->nonAdditive) {
                return null;
            }
        }
        foreach (array_diff($this->joinOrder($plan, true), $this->joinOrder($plan, false)) as $alias) {
            $join = $plan->dataset->getJoin($alias);
            if ($join->type !== JoinType::Left || $join->manyRows) {
                return null;
            }
        }
        $parts = [];
        foreach ($plan->metrics as $metric) {
            $metricParts = $plan->dataset->additiveParts($metric->code);
            if ($metricParts === null) {
                return null;
            }
            $parts += $metricParts;
        }
        return $parts;
    }

    /** The number of grouped rows of the period. */
    public function count(Plan $plan, Context $context): Select
    {
        $inner = $this->rows($plan, $context);
        $inner->reset(Select::ORDER);
        return $context->adapter->select()->from(['grouped' => $inner], ['row_count' => new Expr('COUNT(*)')]);
    }

    /**
     * The rows behind the numbers of the plan: the record fields of the dataset, with the same conditions, scope,
     * period, stores and filters as a report. The metrics and the dimensions of the plan do not apply.
     *
     * @param string|null $sortField a record field, or null for the date field, newest first
     */
    public function records(Plan $plan, Context $context, ?string $sortField, bool $descending, int $limit, int $offset): Select
    {
        $records = $plan->dataset->getRecords() ?? throw new \LogicException("The dataset {$plan->dataset->code} has no records");
        $context = $this->contextFor($plan, $context, false);
        $linkFields = array_values($records->routeQuery);
        $select = $this->base($plan, $context, false, false, [...$records->fields, ...$linkFields, $records->idField]);
        $select->columns(['record_id' => new Expr($plan->dataset->getField($records->idField)->sql($context))]);
        foreach ($records->fields as $index => $code) {
            $select->columns(["r{$index}" => new Expr($plan->dataset->getField($code)->sql($context))]);
        }
        foreach ($linkFields as $index => $code) {
            $select->columns(["q{$index}" => new Expr($plan->dataset->getField($code)->sql($context))]);
        }
        $sortSql = $sortField !== null
            ? $plan->dataset->getField($sortField)->sql($context)
            : ($plan->dateField?->sql($context) ?? $plan->dataset->getField($records->idField)->sql($context));
        $direction = $sortField === null || $descending ? 'DESC' : 'ASC';
        $select->order(new Expr("{$sortSql} {$direction}"));
        $select->order(new Expr('record_id DESC'));
        return $select->limit($limit, $offset);
    }

    /** The number of rows behind the numbers of the plan. */
    public function recordCount(Plan $plan, Context $context): Select
    {
        $records = $plan->dataset->getRecords() ?? throw new \LogicException("The dataset {$plan->dataset->code} has no records");
        $context = $this->contextFor($plan, $context, false);
        $select = $this->base($plan, $context, false, false, [$records->idField]);
        return $select->columns(['row_count' => new Expr('COUNT(*)')]);
    }

    /** The SQL of a metric: an aggregate of a field, or a formula over other metrics. */
    public function metricSql(Dataset $dataset, Metric $metric, Context $context): string
    {
        if ($metric->formula !== null) {
            return Formula::parse($metric->formula)->toSql(
                fn(string $code): string => $this->metricSql($dataset, $dataset->getMetric($code), $context),
            );
        }

        $value = $metric->field === null ? null : $dataset->getField($metric->field)->sql($context);
        $condition = $metric->condition === null ? null : '(' . $dataset->getField($metric->condition)->sql($context) . ') = 1';

        return match ($metric->aggregate) {
            Aggregate::Sum => 'COALESCE(SUM(' . ($condition === null ? $value : "CASE WHEN {$condition} THEN {$value} ELSE 0 END") . '), 0)',
            Aggregate::Count => match (true) {
                $value === null && $condition === null => 'COUNT(*)',
                $value === null => "COALESCE(SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END), 0)",
                $condition === null => "COUNT({$value})",
                default => "COUNT(CASE WHEN {$condition} THEN {$value} END)",
            },
            Aggregate::CountDistinct => 'COUNT(DISTINCT ' . ($condition === null ? $value : "CASE WHEN {$condition} THEN {$value} END") . ')',
            Aggregate::Min, Aggregate::Max, Aggregate::Avg => strtoupper($metric->aggregate->value) . '('
                . ($condition === null ? $value : "CASE WHEN {$condition} THEN {$value} END") . ')',
            null => throw new \LogicException("The metric {$metric->code} has neither an aggregate nor a formula"),
        };
    }

    /** The SQL of a dimension. The period dimension turns the date into the key of its bucket. */
    public function dimensionSql(PlannedDimension $dimension, Context $context, int $firstDayOfWeek = 0): string
    {
        $sql = $dimension->field->sql($context);
        if ($dimension->granularity === null) {
            return $sql;
        }

        $adapter = $context->adapter;
        $local = $dimension->field->localDate ? $sql : $context->localTime($sql);
        $daysFrom = static fn(string $date): string => (string) $adapter->getDateDiffSql($local, $adapter->quote($date));

        return match ($dimension->granularity) {
            Granularity::Hour => (string) $adapter->getDateFormatSql($local, '%Y-%m-%d %H:00:00'),
            Granularity::Day => (string) $adapter->getDatePartSql($local),
            Granularity::Month => (string) $adapter->getDateFormatSql($local, '%Y-%m'),
            Granularity::Year => (string) $adapter->getDateFormatSql($local, '%Y'),
            Granularity::HourOfDay => (string) $adapter->getDateExtractSql($local, \Maho\Db\Adapter\AdapterInterface::INTERVAL_HOUR),
            Granularity::Week => (static function () use ($daysFrom, $firstDayOfWeek): string {
                $days = $daysFrom(Buckets::weekReference($firstDayOfWeek));
                return "(({$days}) - (({$days}) % 7))";
            })(),
            Granularity::DayOfWeek => '((' . $daysFrom(Buckets::SUNDAY) . ') % 7)',
        };
    }

    private function contextFor(Plan $plan, Context $context, bool $compare): Context
    {
        $bounds = $plan->utcBounds($compare);
        return $bounds === null ? $context : $context->withRange($bounds[0], $bounds[1]);
    }

    /** @param list<string> $extraFields more field codes whose joins the query needs */
    private function base(Plan $plan, Context $context, bool $compare, bool $withDimensions, array $extraFields = []): Select
    {
        $dataset = $plan->dataset;
        $adapter = $context->adapter;
        $select = $adapter->select()->from([$dataset->alias => $dataset->table], []);

        $joined = $this->joinOrder($plan, $withDimensions, $extraFields);
        foreach ($joined as $alias) {
            $join = $dataset->getJoin($alias);
            $name = [$alias => $join->table($context)];
            if ($join->type === JoinType::Inner) {
                $select->joinInner($name, $join->condition($context), []);
            } else {
                $select->joinLeft($name, $join->condition($context), []);
            }
        }

        foreach ($dataset->getConditions() as $condition) {
            $select->where(is_string($condition) ? $condition : $condition($context));
        }
        $scopeSql = $plan->scope?->sql($context);
        if ($scopeSql !== null && $scopeSql !== '') {
            $select->where($scopeSql);
        }

        if ($plan->dateField !== null) {
            $dateSql = $plan->dateField->sql($context);
            if ($plan->dateField->localDate) {
                [$from, $to] = $plan->localBounds($compare) ?? [null, null];
            } else {
                [$from, $to] = $plan->utcBounds($compare) ?? [null, null];
            }
            $select->where("{$dateSql} >= ?", $from);
            $select->where("{$dateSql} <= ?", $to);
        }

        if ($dataset->storeField !== null && $plan->request->storeIds !== null) {
            $storeSql = $dataset->getField($dataset->storeField)->sql($context);
            $select->where("{$storeSql} IN (?)", $plan->request->storeIds === [] ? [-1] : $plan->request->storeIds);
        }

        foreach ($plan->request->filters as $filter) {
            $this->addFilter($select, $plan, $context, $filter, $joined);
        }

        return $select;
    }

    private function addMetrics(Select $select, Plan $plan, Context $context): void
    {
        foreach ($plan->metrics as $metric) {
            $select->columns(['m_' . $metric->code => new Expr($this->metricSql($plan->dataset, $metric, $context))]);
        }
    }

    /** @param list<string> $joined the aliases that the query joins already, such as the join of a grouping */
    private function addFilter(Select $select, Plan $plan, Context $context, RequestFilter $filter, array $joined): void
    {
        $field = $plan->dataset->getField($filter->field);
        $join = $this->manyRowsJoin($plan, $field);
        $positive = match ($filter->operator) {
            Operator::Neq => Operator::Eq,
            Operator::Nin => Operator::In,
            Operator::Empty => Operator::NotEmpty,
            default => null,
        };
        // A positive filter keeps the joined row of a grouping; a negative one drops the line from every query
        if ($join === null || ($positive === null && in_array($join->alias, $joined, true))) {
            $this->addCondition($select, $field, $field->sql($context), $filter->operator, $filter->value);
            return;
        }
        $exists = $context->adapter->select()
            ->from([$join->alias => $join->table($context)], [new Expr('1')])
            ->where($join->condition($context));
        $this->addCondition($exists, $field, $field->sql($context), $positive ?? $filter->operator, $filter->value);
        $select->where(($positive === null ? '' : 'NOT ') . "EXISTS ({$exists})", null, Select::TYPE_CONDITION);
    }

    private function manyRowsJoin(Plan $plan, Field $field): ?Join
    {
        foreach ($field->joins as $alias) {
            $join = $plan->dataset->getJoin($alias);
            if ($join->manyRows) {
                return $join;
            }
        }
        return null;
    }

    private function addCondition(Select $select, Field $field, string $sql, Operator $operator, mixed $value): void
    {
        $cast = static fn(mixed $v): mixed => match ($field->type) {
            FieldType::Boolean => (int) (bool) $v,
            FieldType::Integer, FieldType::Id => is_numeric($v) ? (int) $v : $v,
            FieldType::Money, FieldType::Decimal => is_numeric($v) ? (float) $v : $v,
            default => is_bool($v) ? (int) $v : $v,
        };
        $isText = $field->type === FieldType::String;

        match ($operator) {
            Operator::Eq => $select->where("{$sql} = ?", $cast($value)),
            Operator::Neq => $select->where("({$sql} IS NULL OR {$sql} <> ?)", $cast($value)),
            Operator::In => $select->where("{$sql} IN (?)", array_map($cast, (array) $value)),
            Operator::Nin => $select->where("({$sql} IS NULL OR {$sql} NOT IN (?))", array_map($cast, (array) $value)),
            Operator::Gt => $select->where("{$sql} > ?", $cast($value)),
            Operator::Gte => $select->where("{$sql} >= ?", $cast($value)),
            Operator::Lt => $select->where("{$sql} < ?", $cast($value)),
            Operator::Lte => $select->where("{$sql} <= ?", $cast($value)),
            Operator::Contains => $select->where(
                "LOWER({$sql}) LIKE ? ESCAPE '!'",
                '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower((string) $value)) . '%',
            ),
            Operator::Empty => $select->where($isText ? "({$sql} IS NULL OR {$sql} = '')" : "{$sql} IS NULL"),
            Operator::NotEmpty => $select->where($isText ? "({$sql} IS NOT NULL AND {$sql} <> '')" : "{$sql} IS NOT NULL"),
        };
    }

    /**
     * The aliases of the joins that the plan reads, each after the joins it requires.
     *
     * @return list<string>
     */
    /** @param list<string> $extraFields */
    private function joinOrder(Plan $plan, bool $withDimensions, array $extraFields = []): array
    {
        $dataset = $plan->dataset;
        $fields = array_fill_keys($extraFields, true);
        $metricFields = function (Metric $metric) use (&$metricFields, &$fields, $dataset): void {
            if ($metric->formula !== null) {
                foreach (Formula::parse($metric->formula)->references() as $code) {
                    $metricFields($dataset->getMetric($code));
                }
                return;
            }
            foreach ([$metric->field, $metric->condition] as $code) {
                if ($code !== null) {
                    $fields[$code] = true;
                }
            }
        };
        foreach ($plan->metrics as $metric) {
            $metricFields($metric);
        }
        if ($withDimensions) {
            foreach ($plan->dimensions as $dimension) {
                $fields[$dimension->field->code] = true;
            }
        }
        foreach ($plan->request->filters as $filter) {
            if ($this->manyRowsJoin($plan, $dataset->getField($filter->field)) === null) {
                $fields[$filter->field] = true;
            }
        }
        if ($plan->dateField !== null) {
            $fields[$plan->dateField->code] = true;
        }
        if ($dataset->storeField !== null && $plan->request->storeIds !== null) {
            $fields[$dataset->storeField] = true;
        }

        $needed = $plan->scope->joins ?? [];
        foreach (array_keys($fields) as $code) {
            array_push($needed, ...$dataset->getField($code)->joins);
        }

        $ordered = [];
        $visit = function (string $alias) use (&$visit, &$ordered, $dataset): void {
            if (isset($ordered[$alias])) {
                return;
            }
            $ordered[$alias] = false;
            foreach ($dataset->getJoin($alias)->requires as $required) {
                $visit($required);
            }
            unset($ordered[$alias]);
            $ordered[$alias] = true;
        };
        foreach ($needed as $alias) {
            $visit($alias);
        }
        return array_keys($ordered);
    }

    private function aliasOf(Plan $plan, string $key): ?string
    {
        foreach ($plan->dimensions as $dimension) {
            if ($dimension->dimension->code === $key) {
                return $dimension->alias;
            }
        }
        foreach ($plan->metrics as $metric) {
            if ($metric->code === $key) {
                return 'm_' . $metric->code;
            }
        }
        return null;
    }
}
