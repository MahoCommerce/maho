<?php

/**
 * Describe the report datasets that a caller may read: the metrics, dimensions, filters, scopes and presets.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Granularity;
use Maho\Report\Request;

class Mage_Reports_Model_Explorer_Catalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function describe(Mage_Reports_Model_Explorer_Access $access): array
    {
        $datasets = [];
        foreach (Mage::getSingleton('reports/explorer_registry')->getDatasets() as $dataset) {
            if ($access->allowsDataset($dataset)) {
                $datasets[] = $this->describeDataset($dataset, $access);
            }
        }
        return $datasets;
    }

    /** @return array<string, mixed> */
    public function describeDataset(Dataset $dataset, Mage_Reports_Model_Explorer_Access $access): array
    {
        $validator = Mage::getSingleton('reports/explorer_validator');

        $metrics = [];
        foreach ($dataset->getMetrics() as $metric) {
            if ($metric->hidden || (!$access->personalData && $validator->metricIsPersonal($dataset, $metric->code))) {
                continue;
            }
            $metrics[] = array_filter([
                'code' => $metric->code,
                'label' => $metric->label,
                'format' => $metric->format->value,
                'higherIsBetter' => $metric->higherIsBetter,
                'additive' => $dataset->isAdditive($metric->code),
                'description' => $metric->description,
            ], static fn(mixed $v): bool => $v !== null);
        }

        $dimensions = [];
        foreach ($dataset->getDimensions() as $dimension) {
            if ($dimension->isPeriod()) {
                $dimensions[] = [
                    'code' => $dimension->code,
                    'label' => $dimension->label,
                    'granularities' => self::granularities($dataset),
                ];
                continue;
            }
            $field = $dataset->getField($dimension->field);
            if ($field->personal && !$access->personalData) {
                continue;
            }
            $dimensions[] = array_filter([
                'code' => $dimension->code,
                'label' => $dimension->label,
                // The field that a drill on a value of the dimension filters on
                'field' => $field->filterable ? $field->code : null,
                'nonAdditive' => $dimension->nonAdditive ?: null,
            ], static fn(mixed $v): bool => $v !== null);
        }

        $fields = [];
        foreach ($dataset->getFields() as $field) {
            if (!$field->filterable || ($field->personal && !$access->personalData)) {
                continue;
            }
            $fields[] = array_filter([
                'code' => $field->code,
                'label' => $field->label,
                'type' => $field->type->value,
                'options' => $field->options === [] ? null : array_map(
                    static fn(string|int $value, string $label): array => ['value' => $value, 'label' => $label],
                    array_keys($field->options),
                    array_values($field->options),
                ),
            ], static fn(mixed $v): bool => $v !== null);
        }

        $dateFields = [];
        foreach ($dataset->getDateFields() as $code) {
            $dateFields[] = ['code' => $code, 'label' => $dataset->getField($code)->label];
        }

        $scopes = [];
        foreach ($dataset->getScopes() as $scope) {
            $scopes[] = ['code' => $scope->code, 'label' => $scope->label];
        }

        $presets = [];
        foreach ($dataset->getPresets() as $preset) {
            if (!$this->presetIsAllowed($dataset, $preset->request, $access)) {
                continue;
            }
            $presets[] = array_filter([
                'code' => $preset->code,
                'label' => $preset->label,
                'group' => $preset->group,
                'chart' => $preset->chart->value,
                'description' => $preset->description,
                'request' => Request::fromArray(['dataset' => $dataset->code] + $preset->request)->toArray(),
            ], static fn(mixed $v): bool => $v !== null);
        }

        return [
            'code' => $dataset->code,
            'label' => $dataset->label,
            'group' => $dataset->group,
            'dated' => $dataset->isDated(),
            'hasStores' => $dataset->storeField !== null,
            'records' => $access->allowsRecords($dataset),
            'dateFields' => $dateFields,
            'scopes' => $scopes,
            'defaultScope' => $dataset->getDefaultScope(),
            'metrics' => $metrics,
            'dimensions' => $dimensions,
            'fields' => $fields,
            'presets' => $presets,
        ];
    }

    /**
     * Find a preset by its code in the datasets that the caller may read.
     *
     * @return array{0: Dataset, 1: \Maho\Report\Definition\Preset}|null
     */
    public function findPreset(string $code, Mage_Reports_Model_Explorer_Access $access): ?array
    {
        foreach (Mage::getSingleton('reports/explorer_registry')->getDatasets() as $dataset) {
            $preset = $dataset->getPresets()[$code] ?? null;
            if ($preset !== null && $access->allowsDataset($dataset) && $this->presetIsAllowed($dataset, $preset->request, $access)) {
                return [$dataset, $preset];
            }
        }
        return null;
    }

    /** @param array<string, mixed> $request */
    private function presetIsAllowed(Dataset $dataset, array $request, Mage_Reports_Model_Explorer_Access $access): bool
    {
        if ($access->personalData) {
            return true;
        }
        $parsed = Request::fromArray(['dataset' => $dataset->code] + $request);
        $validator = Mage::getSingleton('reports/explorer_validator');
        foreach ($parsed->metrics as $code) {
            if ($dataset->hasMetric($code) && $validator->metricIsPersonal($dataset, $code)) {
                return false;
            }
        }
        foreach ($parsed->dimensions as $requested) {
            if ($dataset->hasDimension($requested->code)) {
                $dimension = $dataset->getDimension($requested->code);
                if (!$dimension->isPeriod() && $dataset->getField($dimension->field)->personal) {
                    return false;
                }
            }
        }
        return array_all($parsed->filters, fn($filter) => !($dataset->hasField($filter->field) && $dataset->getField($filter->field)->personal));
    }

    /**
     * The steps of the period. A date field that holds local days has no hours, so a data set whose date fields all
     * hold local days offers no hour steps.
     *
     * @return list<string>
     */
    private static function granularities(Dataset $dataset): array
    {
        $hasTime = false;
        foreach ($dataset->getDateFields() as $code) {
            $hasTime = $hasTime || !$dataset->getField($code)->localDate;
        }
        $granularities = [];
        foreach (Granularity::cases() as $granularity) {
            if ($hasTime || !in_array($granularity, [Granularity::Hour, Granularity::HourOfDay], true)) {
                $granularities[] = $granularity->value;
            }
        }
        return $granularities;
    }
}
