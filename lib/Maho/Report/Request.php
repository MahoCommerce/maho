<?php

/**
 * What a report asks for: the dataset, the metrics, the dimensions, the filters, the period and the page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Granularity;

final class Request
{
    public const int DEFAULT_LIMIT = 100;

    /**
     * @param list<string> $metrics
     * @param list<RequestDimension> $dimensions
     * @param list<RequestFilter> $filters
     * @param string|null $from the first local day, "Y-m-d", when $range is null
     * @param string|null $to the last local day, "Y-m-d", when $range is null
     * @param list<int>|null $storeIds null reads every store that the caller may read
     * @param list<array{field: string, message: string}> $errors the parts of the input that {@see fromArray()} could not read
     */
    public function __construct(
        public readonly string $dataset,
        public readonly ?string $scope = null,
        public readonly ?string $dateField = null,
        public readonly array $metrics = [],
        public readonly array $dimensions = [],
        public readonly array $filters = [],
        public readonly ?string $sortKey = null,
        public readonly bool $sortDescending = true,
        public readonly ?DateRange $range = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly Comparison $compare = Comparison::None,
        public readonly ?array $storeIds = null,
        public readonly int $limit = self::DEFAULT_LIMIT,
        public readonly int $offset = 0,
        public readonly array $errors = [],
    ) {}

    /**
     * Read a request from the JSON shape of the page, the API, saved reports and the CLI. A list can also be a
     * comma-separated string, a dimension can be "code:granularity", a sort can be "-code" for descending, and
     * the filters can be a JSON string. A value that this method cannot read goes to $errors, not to an exception.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $dataset = $data['dataset'] ?? null;
        if (!is_string($dataset) || $dataset === '') {
            $errors[] = ['field' => 'dataset', 'message' => 'A dataset is required.'];
            $dataset = '';
        }

        $metrics = [];
        foreach (self::readList($data['metrics'] ?? [], 'metrics', $errors) as $metric) {
            if (is_string($metric) && $metric !== '') {
                $metrics[] = $metric;
            } else {
                $errors[] = ['field' => 'metrics', 'message' => 'Each metric must be a code.'];
            }
        }

        $dimensions = [];
        foreach (self::readList($data['dimensions'] ?? [], 'dimensions', $errors) as $dimension) {
            if (is_array($dimension)) {
                $code = $dimension['code'] ?? null;
                $granularity = $dimension['granularity'] ?? null;
            } elseif (is_string($dimension)) {
                [$code, $granularity] = array_pad(explode(':', $dimension, 2), 2, null);
            } else {
                $code = $granularity = null;
            }
            if (!is_string($code) || $code === '') {
                $errors[] = ['field' => 'dimensions', 'message' => 'Each dimension must have a code.'];
                continue;
            }
            $parsed = null;
            if ($granularity !== null && $granularity !== '') {
                $parsed = is_string($granularity) ? Granularity::tryFrom($granularity) : null;
                if ($parsed === null) {
                    $errors[] = ['field' => 'dimensions', 'message' => "The granularity of {$code} is not valid."];
                }
            }
            $dimensions[] = new RequestDimension($code, $parsed);
        }

        $filters = [];
        $rawFilters = $data['filters'] ?? [];
        if (is_string($rawFilters)) {
            try {
                $rawFilters = $rawFilters === '' ? [] : json_decode($rawFilters, true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $errors[] = ['field' => 'filters', 'message' => 'The filters must be a JSON list.'];
                $rawFilters = [];
            }
        }
        if (!is_array($rawFilters) || !array_is_list($rawFilters)) {
            $errors[] = ['field' => 'filters', 'message' => 'The filters must be a list.'];
            $rawFilters = [];
        }
        foreach ($rawFilters as $filter) {
            $field = is_array($filter) ? ($filter['field'] ?? null) : null;
            $operator = is_array($filter) && is_string($filter['operator'] ?? null) ? Operator::tryFrom($filter['operator']) : null;
            if (!is_string($field) || $field === '' || $operator === null) {
                $errors[] = ['field' => 'filters', 'message' => 'Each filter needs a field and a valid operator.'];
                continue;
            }
            $value = $filter['value'] ?? null;
            if ($operator->takesList()) {
                $value = is_array($value) ? array_values($value) : (is_scalar($value) ? [$value] : []);
                $value = array_values(array_filter($value, static fn(mixed $v): bool => is_string($v) || is_int($v) || is_float($v)));
                if ($value === []) {
                    $errors[] = ['field' => 'filters', 'message' => "The filter on {$field} needs at least one value."];
                    continue;
                }
            } elseif ($operator->takesValue() && !is_scalar($value)) {
                $errors[] = ['field' => 'filters', 'message' => "The filter on {$field} needs one value."];
                continue;
            } elseif (!$operator->takesValue()) {
                $value = null;
            }
            $filters[] = new RequestFilter($field, $operator, $value);
        }

        $sortKey = null;
        $sortDescending = true;
        $sort = $data['sort'] ?? null;
        if (is_string($sort) && $sort !== '') {
            $sortDescending = str_starts_with($sort, '-');
            $sortKey = ltrim($sort, '-+');
        } elseif (is_array($sort) && is_string($sort['key'] ?? null) && $sort['key'] !== '') {
            $sortKey = $sort['key'];
            $sortDescending = strtolower((string) ($sort['direction'] ?? 'desc')) !== 'asc';
        } elseif ($sort !== null && $sort !== '') {
            $errors[] = ['field' => 'sort', 'message' => 'The sort must be a code, or a key and a direction.'];
        }

        $range = null;
        if (is_string($data['range'] ?? null) && $data['range'] !== '' && $data['range'] !== 'custom') {
            $range = DateRange::tryFrom($data['range']);
            if ($range === null) {
                $errors[] = ['field' => 'range', 'message' => 'The range is not valid.'];
            }
        }
        $from = self::readDate($data['from'] ?? null, 'from', $errors);
        $to = self::readDate($data['to'] ?? null, 'to', $errors);

        $compare = Comparison::None;
        if (is_string($data['compare'] ?? null) && $data['compare'] !== '') {
            $compare = Comparison::tryFrom($data['compare']) ?? Comparison::None;
            if (Comparison::tryFrom($data['compare']) === null) {
                $errors[] = ['field' => 'compare', 'message' => 'The comparison is not valid.'];
            }
        }

        $storeIds = null;
        if (isset($data['storeIds']) && $data['storeIds'] !== '' && $data['storeIds'] !== []) {
            $storeIds = [];
            foreach (self::readList($data['storeIds'], 'storeIds', $errors) as $storeId) {
                if (is_int($storeId) || (is_string($storeId) && ctype_digit($storeId))) {
                    $storeIds[] = (int) $storeId;
                } else {
                    $errors[] = ['field' => 'storeIds', 'message' => 'Each store ID must be a number.'];
                }
            }
        }

        $limit = self::readInt($data['limit'] ?? self::DEFAULT_LIMIT, 'limit', 1, $errors) ?? self::DEFAULT_LIMIT;
        $offset = self::readInt($data['offset'] ?? 0, 'offset', 0, $errors) ?? 0;

        return new self(
            $dataset,
            is_string($data['scope'] ?? null) && $data['scope'] !== '' ? $data['scope'] : null,
            is_string($data['dateField'] ?? null) && $data['dateField'] !== '' ? $data['dateField'] : null,
            array_values(array_unique($metrics)),
            $dimensions,
            $filters,
            $sortKey,
            $sortDescending,
            $range,
            $from,
            $to,
            $compare,
            $storeIds,
            $limit,
            $offset,
            $errors,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'dataset' => $this->dataset,
            'scope' => $this->scope,
            'dateField' => $this->dateField,
            'metrics' => $this->metrics,
            'dimensions' => array_map(static fn(RequestDimension $d): string => $d->toString(), $this->dimensions),
            'filters' => array_map(static fn(RequestFilter $f): array => $f->toArray(), $this->filters),
            'sort' => $this->sortKey === null ? null : ['key' => $this->sortKey, 'direction' => $this->sortDescending ? 'desc' : 'asc'],
            'range' => $this->range?->value,
            'from' => $this->range === null ? $this->from : null,
            'to' => $this->range === null ? $this->to : null,
            'compare' => $this->compare === Comparison::None ? null : $this->compare->value,
            'storeIds' => $this->storeIds,
            'limit' => $this->limit === self::DEFAULT_LIMIT ? null : $this->limit,
            'offset' => $this->offset === 0 ? null : $this->offset,
        ], static fn(mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * A copy with some keys of {@see toArray()} replaced.
     *
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
    }

    public function hasDimension(string $code): bool
    {
        return array_any($this->dimensions, fn($dimension) => $dimension->code === $code);
    }

    /**
     * @param list<array{field: string, message: string}> $errors
     * @return list<mixed>
     */
    private static function readList(mixed $value, string $field, array &$errors): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : array_map(trim(...), explode(',', $value));
        }
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }
        $errors[] = ['field' => $field, 'message' => 'This must be a list.'];
        return [];
    }

    /** @param list<array{field: string, message: string}> $errors */
    private static function readDate(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date !== false && $date->format('Y-m-d') === $value) {
                return $value;
            }
        }
        $errors[] = ['field' => $field, 'message' => 'The date must have the format YYYY-MM-DD.'];
        return null;
    }

    /** @param list<array{field: string, message: string}> $errors */
    private static function readInt(mixed $value, string $field, int $min, array &$errors): ?int
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            if ((int) $value >= $min) {
                return (int) $value;
            }
        }
        $errors[] = ['field' => $field, 'message' => "This must be a whole number of at least {$min}."];
        return null;
    }
}
