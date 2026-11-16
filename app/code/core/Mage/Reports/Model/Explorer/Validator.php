<?php

/**
 * Check a report request against its dataset and the access of the caller.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Granularity;
use Maho\Report\Operator;
use Maho\Report\Planner;
use Maho\Report\Request;

class Mage_Reports_Model_Explorer_Validator
{
    public const MAX_DIMENSIONS = 3;

    /**
     * Return the request with the stores that the caller may read, or throw every problem at once.
     *
     * @throws Mage_Core_Exception_Input
     */
    public function validate(Request $request, ?Dataset $dataset, Mage_Reports_Model_Explorer_Access $access, int $maxRows): Request
    {
        $helper = Mage::helper('reports');
        $errors = new Mage_Core_Exception_Input($helper->__('The report request is not valid.'));
        foreach ($request->errors as $error) {
            $errors->addError($error['field'], $helper->__($error['message']));
        }

        if ($dataset === null || !$access->allowsDataset($dataset)) {
            $errors->addError('dataset', $helper->__('The dataset "%s" does not exist.', $request->dataset));
            $errors->throwIfErrors();
            return $request;
        }

        if ($request->scope !== null && !$dataset->hasScope($request->scope)) {
            $errors->addError('scope', $helper->__('The scope "%s" does not exist.', $request->scope));
        }
        if ($request->dateField !== null && !in_array($request->dateField, $dataset->getDateFields(), true)) {
            $errors->addError('dateField', $helper->__('The date field "%s" does not exist.', $request->dateField));
        }

        if ($request->metrics === []) {
            $errors->addError('metrics', $helper->__('Choose at least one metric.'));
        }
        foreach ($request->metrics as $code) {
            if (!$dataset->hasMetric($code) || $dataset->getMetric($code)->hidden) {
                $errors->addError('metrics', $helper->__('The metric "%s" does not exist.', $code));
            } elseif (!$access->personalData && $this->metricIsPersonal($dataset, $code)) {
                $errors->addError('metrics', $helper->__('You may not read the personal data of the metric "%s".', $code));
            }
        }

        if (count($request->dimensions) > self::MAX_DIMENSIONS) {
            $errors->addError('dimensions', $helper->__('Choose at most %d dimensions.', self::MAX_DIMENSIONS));
        }
        $seen = [];
        $periodGranularity = null;
        foreach ($request->dimensions as $requested) {
            if (isset($seen[$requested->code])) {
                $errors->addError('dimensions', $helper->__('The dimension "%s" appears twice.', $requested->code));
                continue;
            }
            $seen[$requested->code] = true;
            if (!$dataset->hasDimension($requested->code)) {
                $errors->addError('dimensions', $helper->__('The dimension "%s" does not exist.', $requested->code));
                continue;
            }
            $dimension = $dataset->getDimension($requested->code);
            if ($dimension->isPeriod()) {
                $periodGranularity = $requested->granularity ?? Granularity::Day;
                $dateField = $dataset->getField($request->dateField ?? $dataset->getDateFields()[0]);
                if ($dateField->localDate && in_array($periodGranularity, [Granularity::Hour, Granularity::HourOfDay], true)) {
                    $errors->addError('dimensions', $helper->__('This dataset has one row per day, so it has no hours.'));
                }
                continue;
            }
            if ($requested->granularity !== null) {
                $errors->addError('dimensions', $helper->__('Only the period takes a granularity.'));
            }
            if (!$access->personalData && $dataset->getField($dimension->field)->personal) {
                $errors->addError('dimensions', $helper->__('You may not read the personal data of the dimension "%s".', $requested->code));
            }
        }

        foreach ($request->filters as $filter) {
            if (!$dataset->hasField($filter->field)) {
                $errors->addError('filters', $helper->__('The field "%s" does not exist.', $filter->field));
                continue;
            }
            $field = $dataset->getField($filter->field);
            if (!$field->filterable) {
                $errors->addError('filters', $helper->__('The field "%s" does not exist.', $filter->field));
                continue;
            }
            if (!$access->personalData && $field->personal) {
                $errors->addError('filters', $helper->__('You may not filter on the personal data of the field "%s".', $filter->field));
            }
            if ($filter->operator === Operator::Contains && $field->type !== FieldType::String) {
                $errors->addError('filters', $helper->__('The field "%s" does not take the operator "%s".', $filter->field, $filter->operator->value));
            }
            if (in_array($filter->operator, [Operator::Gt, Operator::Gte, Operator::Lt, Operator::Lte], true)
                && in_array($field->type, [FieldType::String, FieldType::Boolean], true)) {
                $errors->addError('filters', $helper->__('The field "%s" does not take the operator "%s".', $filter->field, $filter->operator->value));
            }
        }

        if ($request->sortKey !== null && !in_array($request->sortKey, $request->metrics, true) && !isset($seen[$request->sortKey])) {
            $errors->addError('sort', $helper->__('Sort by a chosen metric or dimension.'));
        }

        if ($dataset->isDated()) {
            if ($request->range === null && ($request->from === null) !== ($request->to === null)) {
                $errors->addError($request->from === null ? 'from' : 'to', $helper->__('Give both the first and the last day.'));
            }
            if ($periodGranularity?->maxDays() !== null && $request->errors === []) {
                $zone = new DateTimeZone(Mage_Reports_Model_Explorer_Runner::timezone());
                [$from, $to] = Planner::localRange($request, new DateTimeImmutable('now', new DateTimeZone('UTC')), $zone->getName());
                $days = (int) $from->diff($to)->days + 1;
                if ($days > $periodGranularity->maxDays()) {
                    $errors->addError('dimensions', $helper->__(
                        'A period by %s covers at most %d days. Choose a shorter period or a larger granularity.',
                        $periodGranularity->value,
                        $periodGranularity->maxDays(),
                    ));
                }
            }
        } elseif ($request->range !== null || $request->from !== null) {
            $errors->addError('range', $helper->__('This dataset has no period.'));
        }

        $storeIds = $request->storeIds;
        if ($access->storeIds !== null) {
            if ($storeIds === null) {
                $storeIds = $access->storeIds;
            } elseif (array_diff($storeIds, $access->storeIds) !== []) {
                $errors->addError('storeIds', $helper->__('You may not read one of these stores.'));
            }
        }

        if ($request->limit > $maxRows) {
            $errors->addError('limit', $helper->__('A page has at most %d rows.', $maxRows));
        }

        $errors->throwIfErrors();
        return $storeIds === $request->storeIds ? $request : $request->with(['storeIds' => $storeIds]);
    }

    /** Whether a metric, or a metric that its formula reads, aggregates a personal field. */
    public function metricIsPersonal(Dataset $dataset, string $code): bool
    {
        $metric = $dataset->getMetric($code);
        if ($metric->formula !== null) {
            return array_any(\Maho\Report\Formula::parse($metric->formula)->references(), fn($reference) => $this->metricIsPersonal($dataset, $reference));
        }
        return array_any([$metric->field, $metric->condition], fn($field) => $field !== null && $dataset->getField($field)->personal);
    }
}
