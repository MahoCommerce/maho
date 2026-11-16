<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Report\Request;

uses(Tests\MahoBackendTestCase::class);

/**
 * Every preset, metric and grouping of every data set runs on the database of the test run. This finds SQL that
 * one engine refuses, such as a function that SQLite has and PostgreSQL does not.
 */

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
});

/**
 * Run each request and return the code and the error of each request that failed.
 *
 * @param array<string, array<string, mixed>> $requests
 * @return array<string, string>
 */
function explorerFailures(array $requests): array
{
    $service = Mage::getService('reports/explorer');
    $access = Mage_Reports_Model_Explorer_Access::full();
    $failures = [];
    foreach ($requests as $label => $request) {
        try {
            $result = $service->run(Request::fromArray($request), $access);
            if (!isset($result['columns'], $result['rows'], $result['totals'])) {
                $failures[$label] = 'The result has no columns, rows or totals';
            }
        } catch (Throwable $e) {
            $failures[$label] = $e::class . ': ' . $e->getMessage();
        }
    }
    return $failures;
}

it('runs every preset of every data set, also with a comparison', function () {
    $requests = [];
    foreach (Mage::getService('reports/explorer')->datasets(Mage_Reports_Model_Explorer_Access::full()) as $dataset) {
        foreach ($dataset['presets'] as $preset) {
            $request = ['dataset' => $dataset['code']] + $preset['request'];
            $requests["{$dataset['code']}/{$preset['code']}"] = $request;
            if ($dataset['dated']) {
                $requests["{$dataset['code']}/{$preset['code']} compared"] = ['compare' => 'previous_year'] + $request;
            }
        }
    }

    expect($requests)->not->toBeEmpty()
        ->and(explorerFailures($requests))->toBe([]);
});

it('runs every metric, every grouping and every filter field of every data set', function () {
    $requests = [];
    foreach (Mage::getService('reports/explorer')->datasets(Mage_Reports_Model_Explorer_Access::full()) as $dataset) {
        $code = $dataset['code'];
        $metrics = array_column($dataset['metrics'], 'code');
        $first = [$metrics[0]];
        $requests["{$code} all metrics"] = ['dataset' => $code, 'metrics' => $metrics];
        foreach ($dataset['dimensions'] as $dimension) {
            foreach ($dimension['granularities'] ?? [null] as $granularity) {
                $value = $granularity === null ? $dimension['code'] : "{$dimension['code']}:{$granularity}";
                $requests["{$code} by {$value}"] = ['dataset' => $code, 'metrics' => $first, 'dimensions' => [$value]] + ($dataset['dated'] ? ['range' => 'last_7_days'] : []);
            }
        }
        foreach ($dataset['fields'] as $field) {
            $filter = match ($field['type']) {
                'boolean' => ['operator' => 'eq', 'value' => 1],
                'string' => ['operator' => 'contains', 'value' => 'a'],
                'date' => ['operator' => 'not_empty', 'value' => null],
                default => ['operator' => 'gte', 'value' => 0],
            };
            $requests["{$code} where {$field['code']}"] = ['dataset' => $code, 'metrics' => $first, 'filters' => [['field' => $field['code']] + $filter]];
        }
        foreach ($dataset['scopes'] as $scope) {
            $requests["{$code} scope {$scope['code']}"] = ['dataset' => $code, 'metrics' => $first, 'scope' => $scope['code']];
        }
        foreach ($dataset['dateFields'] as $dateField) {
            $requests["{$code} date {$dateField['code']}"] = ['dataset' => $code, 'metrics' => $first, 'dateField' => $dateField['code'], 'dimensions' => ['period:month']];
        }
    }

    expect(explorerFailures($requests))->toBe([]);
});
