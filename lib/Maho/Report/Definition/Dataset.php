<?php

/**
 * A reportable entity with one row per record, and the fields, joins, metrics and dimensions on it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

final class Dataset
{
    /** @var array<string, Field> */
    private array $fields = [];

    /** @var array<string, Join> */
    private array $joins = [];

    /** @var array<string, Metric> */
    private array $metrics = [];

    /** @var array<string, Dimension> */
    private array $dimensions = [];

    /** @var array<string, Scope> */
    private array $scopes = [];

    private ?string $defaultScope = null;

    /** @var list<string> */
    private array $dateFields = [];

    /** @var list<string|\Closure(\Maho\Report\Context): string> */
    private array $conditions = [];

    /** @var array<string, Preset> */
    private array $presets = [];

    private ?Records $records = null;

    /**
     * @param string $group the area of the dataset, such as "sales" or "carts"
     * @param string $aclResource the admin ACL resource that guards the dataset
     * @param string $table the base table name
     * @param string $alias the alias of the base table in every field expression
     * @param string|null $storeField the code of the field that holds the store ID; null for a dataset without a store
     * @param bool $volatile the data changes as time passes, so a result stays in the cache for a short time only
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $group,
        public readonly string $aclResource,
        public readonly string $table,
        public readonly string $alias = 'main',
        public readonly ?string $storeField = null,
        public readonly bool $volatile = false,
    ) {}

    public function addField(Field $field): static
    {
        $this->fields[$field->code] = $field;
        return $this;
    }

    public function hasField(string $code): bool
    {
        return isset($this->fields[$code]);
    }

    public function getField(string $code): Field
    {
        return $this->fields[$code] ?? throw new \OutOfBoundsException("Unknown field {$code} in dataset {$this->code}");
    }

    /** @return array<string, Field> */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function removeField(string $code): static
    {
        unset($this->fields[$code]);
        return $this;
    }

    public function addJoin(Join $join): static
    {
        $this->joins[$join->alias] = $join;
        return $this;
    }

    public function getJoin(string $alias): Join
    {
        return $this->joins[$alias] ?? throw new \OutOfBoundsException("Unknown join {$alias} in dataset {$this->code}");
    }

    public function addMetric(Metric $metric): static
    {
        $this->metrics[$metric->code] = $metric;
        return $this;
    }

    public function hasMetric(string $code): bool
    {
        return isset($this->metrics[$code]);
    }

    public function getMetric(string $code): Metric
    {
        return $this->metrics[$code] ?? throw new \OutOfBoundsException("Unknown metric {$code} in dataset {$this->code}");
    }

    /**
     * Whether the value of a metric over several groups is the sum of its values in each group: a sum or a count, or
     * a formula that only adds and subtracts such metrics. A ratio, an average or a distinct count is not additive.
     */
    public function isAdditive(string $code): bool
    {
        $metric = $this->getMetric($code);
        if ($metric->formula !== null) {
            $formula = \Maho\Report\Formula::parse($metric->formula);
            return $formula->isLinear() && array_all($formula->references(), $this->isAdditive(...));
        }
        return in_array($metric->aggregate, [Aggregate::Sum, Aggregate::Count], true);
    }

    public function setRecords(?Records $records): static
    {
        $this->records = $records;
        return $this;
    }

    public function getRecords(): ?Records
    {
        return $this->records;
    }

    /** @return array<string, Metric> */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function removeMetric(string $code): static
    {
        unset($this->metrics[$code]);
        return $this;
    }

    public function addDimension(Dimension $dimension): static
    {
        $this->dimensions[$dimension->code] = $dimension;
        return $this;
    }

    public function hasDimension(string $code): bool
    {
        return isset($this->dimensions[$code]);
    }

    public function getDimension(string $code): Dimension
    {
        return $this->dimensions[$code] ?? throw new \OutOfBoundsException("Unknown dimension {$code} in dataset {$this->code}");
    }

    /** @return array<string, Dimension> */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    public function removeDimension(string $code): static
    {
        unset($this->dimensions[$code]);
        return $this;
    }

    /** The first scope that a dataset adds is its default scope. */
    public function addScope(Scope $scope): static
    {
        $this->scopes[$scope->code] = $scope;
        $this->defaultScope ??= $scope->code;
        return $this;
    }

    public function setDefaultScope(string $code): static
    {
        $this->defaultScope = $code;
        return $this;
    }

    public function getDefaultScope(): ?string
    {
        return $this->defaultScope;
    }

    public function hasScope(string $code): bool
    {
        return isset($this->scopes[$code]);
    }

    public function getScope(string $code): Scope
    {
        return $this->scopes[$code] ?? throw new \OutOfBoundsException("Unknown scope {$code} in dataset {$this->code}");
    }

    /** @return array<string, Scope> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Make a date field selectable as the date of the period. The first one is the default. A dated dataset gets the
     * period dimension.
     */
    public function addDateField(string $fieldCode, string $periodLabel = 'Period'): static
    {
        if (!in_array($fieldCode, $this->dateFields, true)) {
            $this->dateFields[] = $fieldCode;
        }
        if (!isset($this->dimensions[Dimension::PERIOD])) {
            $this->addDimension(new Dimension(Dimension::PERIOD, $periodLabel, ''));
        }
        return $this;
    }

    /** @return list<string> */
    public function getDateFields(): array
    {
        return $this->dateFields;
    }

    public function isDated(): bool
    {
        return $this->dateFields !== [];
    }

    /** @param string|\Closure(\Maho\Report\Context): string $condition SQL that every query of the dataset applies */
    public function addCondition(string|\Closure $condition): static
    {
        $this->conditions[] = $condition;
        return $this;
    }

    /** @return list<string|\Closure(\Maho\Report\Context): string> */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function addPreset(Preset $preset): static
    {
        $this->presets[$preset->code] = $preset;
        return $this;
    }

    public function removePreset(string $code): static
    {
        unset($this->presets[$code]);
        return $this;
    }

    /** @return array<string, Preset> */
    public function getPresets(): array
    {
        return $this->presets;
    }
}
