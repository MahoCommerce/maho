<?php

/**
 * The report explorer: the datasets that a caller may read, the run of a request or a preset, and the export.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Explainer;
use Maho\Report\Operator;
use Maho\Report\Request;

class Mage_Reports_Service_Explorer
{
    public const string XML_PATH_EXPORT_MAX_ROWS = 'reports/explorer/export_max_rows';

    /** The most dimensions that one explanation reads: each one costs two queries. */
    public const int EXPLAIN_MAX_DIMENSIONS = 12;

    /**
     * @return list<array<string, mixed>>
     */
    public function datasets(Mage_Reports_Model_Explorer_Access $access): array
    {
        return Mage::getModel('reports/explorer_catalog')->describe($access);
    }

    /**
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input
     */
    public function run(Request $request, Mage_Reports_Model_Explorer_Access $access): array
    {
        $result = Mage::getSingleton('reports/explorer_runner')->run($request, $access);
        return $this->addTimeContext($result, Request::fromArray($result['request']), $access);
    }

    /**
     * Add the notes on the days of a report by the period, and the targets of its metrics. A target applies only to a
     * report with no grouping besides the period, no filter, and all the store views or one: otherwise the target
     * would compare with a part of the sales.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function addTimeContext(array $result, Request $request, Mage_Reports_Model_Explorer_Access $access): array
    {
        $result['annotations'] = [];
        $result['targets'] = [];
        $period = $result['period'] ?? null;
        $column = $result['columns'][0] ?? null;
        $granularity = \Maho\Report\Definition\Granularity::tryFrom((string) ($column['granularity'] ?? ''));
        if ($period === null || $granularity === null || !in_array($granularity->value, ['day', 'week', 'month', 'year'], true)) {
            return $result;
        }
        $zone = new DateTimeZone($period['timezone']);
        $firstDay = Mage_Reports_Model_Explorer_Runner::firstDayOfWeek();
        foreach (Mage::getService('reports/annotation')->between($period['from'], $period['to']) as $note) {
            $note['key'] = \Maho\Report\Buckets::keyOf($granularity, new DateTimeImmutable($note['date'], $zone), $firstDay);
            $result['annotations'][] = $note;
        }

        $dimensions = array_filter($result['columns'], static fn(array $c): bool => $c['kind'] === 'dimension');
        $dataset = Mage::getSingleton('reports/explorer_registry')->getDataset($request->dataset);
        if (count($dimensions) !== 1 || $request->filters !== [] || ($request->storeIds !== null && count($request->storeIds) > 1) || $dataset === null) {
            return $result;
        }
        $storeId = $request->storeIds[0] ?? ($access->storeIds === null ? 0 : -1);
        if ($storeId < 0) {
            return $result;
        }
        foreach ($request->metrics as $metric) {
            if (!$dataset->isAdditive($metric)) {
                continue;
            }
            $monthly = Mage::getService('reports/target')->monthly($dataset->code, $metric, $storeId);
            $spread = $monthly === [] ? null : \Maho\Report\Targets::spread(
                $monthly,
                $granularity,
                new DateTimeImmutable($period['from'], $zone),
                new DateTimeImmutable($period['to'], $zone),
                $firstDay,
            );
            if ($spread !== null) {
                $result['targets'][$metric] = $spread;
            }
        }
        return $result;
    }

    /**
     * The rows behind the numbers of a request, such as the orders that a report counts.
     *
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input
     */
    public function records(Request $request, Mage_Reports_Model_Explorer_Access $access, ?string $sort = null, int $limit = Mage_Reports_Model_Explorer_Runner::RECORDS_LIMIT, int $offset = 0): array
    {
        return Mage::getSingleton('reports/explorer_runner')->records($request, $access, $sort, $limit, $offset);
    }

    /**
     * Explain the change of one metric between the period of a request and an earlier period: for each dimension,
     * the values that changed the most. Without a comparison in the request, the earlier period is the period before.
     *
     * @param list<string>|null $dimensions the dimension codes to read; null reads the dimensions that explain well
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input
     */
    public function explain(Request $request, Mage_Reports_Model_Explorer_Access $access, string $metric, ?array $dimensions = null, int $top = 5): array
    {
        $helper = Mage::helper('reports');
        $runner = Mage::getSingleton('reports/explorer_runner');
        $dataset = Mage::getSingleton('reports/explorer_registry')->getDataset($request->dataset);
        if ($dataset !== null && !$dataset->isDated()) {
            throw new Mage_Core_Exception_Input($helper->__('This dataset has no period to compare.'))
                ->addError('dataset', $helper->__('This dataset has no period to compare.'));
        }

        $compare = $request->compare === \Maho\Report\Comparison::None ? 'previous_period' : $request->compare->value;
        $base = $request->with(['metrics' => [$metric], 'dimensions' => [], 'sort' => null, 'offset' => 0, 'compare' => $compare]);
        $total = $runner->run($base, $access);
        if ($dataset === null || $total['period'] === null || $total['comparePeriod'] === null) {
            throw new LogicException('The runner accepted a request without a period.');
        }
        $current = $total['totals']['values'][$metric] ?? null;
        $earlier = $total['totals']['compare'][$metric] ?? null;
        $totalChange = $current === null && $earlier === null ? null : (float) $current - (float) $earlier;

        $filtered = [];
        foreach ($request->filters as $filter) {
            if ($filter->operator === Operator::Eq) {
                $filtered[$filter->field] = true;
            }
        }
        $records = $dataset->getRecords();
        $candidates = [];
        foreach ($dataset->getDimensions() as $dimension) {
            if ($dimension->isPeriod()) {
                continue;
            }
            $field = $dataset->getField($dimension->field);
            $chosen = $dimensions === null
                ? !$field->personal && $field->code !== $records?->idField && !isset($filtered[$field->code])
                : in_array($dimension->code, $dimensions, true);
            if ($chosen && ($access->personalData || !$field->personal)) {
                $candidates[] = $dimension;
            }
        }
        $candidates = array_slice($candidates, 0, self::EXPLAIN_MAX_DIMENSIONS);

        $metricIsAdditive = $dataset->isAdditive($metric);
        $explained = [];
        foreach ($candidates as $dimension) {
            $read = static fn(array $period): array => $runner->run($base->with([
                'dimensions' => [$dimension->code],
                'range' => null,
                'from' => $period['from'],
                'to' => $period['to'],
                'compare' => 'none',
                'limit' => Mage_Reports_Model_Explorer_Runner::maxRows(),
            ]), $access, false)['rows'];
            $values = [];
            foreach (['current' => $total['period'], 'earlier' => $total['comparePeriod']] as $side => $period) {
                foreach ($read($period) as $row) {
                    $key = (string) ($row['dimensions'][$dimension->code] ?? '');
                    $values[$key] ??= ['label' => (string) ($row['labels'][$dimension->code] ?? $key), 'current' => null, 'earlier' => null];
                    $values[$key][$side] = $row['values'][$metric] === null ? null : (float) $row['values'][$metric];
                }
            }
            // A dimension with one value tells nothing: that value has the whole change
            if (count($values) < 2) {
                continue;
            }
            $additive = $metricIsAdditive && !$dimension->nonAdditive;
            $drivers = Explainer::drivers($values, $totalChange, $additive, $top);
            // Two groupings that split the records the same way, such as a customer group and a customer type, show once
            $numbers = static fn(array $list): array => array_map(static fn(array $driver): array => [$driver['change'], $driver['current']], $list);
            if (in_array($numbers($drivers), array_map(static fn(array $shown): array => $numbers($shown['drivers']), $explained), true)) {
                continue;
            }
            if ($drivers !== []) {
                $explained[] = ['code' => $dimension->code, 'label' => $dimension->label, 'field' => $dimension->field, 'additive' => $additive, 'drivers' => $drivers];
            }
        }
        usort($explained, static fn(array $a, array $b): int => abs($b['drivers'][0]['change']) <=> abs($a['drivers'][0]['change']));

        $definition = $dataset->getMetric($metric);
        return [
            'dataset' => $dataset->code,
            'request' => $base->toArray(),
            'metric' => [
                'code' => $metric,
                'label' => $definition->label,
                'format' => $definition->format->value,
                'higherIsBetter' => $definition->higherIsBetter,
                'additive' => $metricIsAdditive,
            ],
            'total' => [
                'current' => $current,
                'earlier' => $earlier,
                'change' => $totalChange,
                'percent' => $earlier === null || abs((float) $earlier) < 0.00005 || $totalChange === null ? null : round($totalChange / abs((float) $earlier) * 100, 2),
            ],
            'period' => $total['period'],
            'comparePeriod' => $total['comparePeriod'],
            'currency' => $total['currency'],
            'dimensions' => $explained,
        ];
    }

    /**
     * The request of a preset, with the keys of $overrides replaced, such as the range or the stores.
     *
     * @param array<string, mixed> $overrides
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function presetRequest(string $code, Mage_Reports_Model_Explorer_Access $access, array $overrides = []): Request
    {
        $found = Mage::getModel('reports/explorer_catalog')->findPreset($code, $access);
        if ($found === null) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('reports')->__('The report "%s" does not exist.', $code));
        }
        [$dataset, $preset] = $found;
        return $this->requestWith(array_merge(['dataset' => $dataset->code], $preset->request), $overrides);
    }

    /**
     * The request of $data, with the keys of $overrides replaced. An empty override keeps the value of $data.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $overrides
     */
    public function requestWith(array $data, array $overrides): Request
    {
        $overrides = array_filter($overrides, static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        // A period of its own replaces the period of the request, named or not
        if (isset($overrides['range']) || isset($overrides['from']) || isset($overrides['to'])) {
            unset($data['range'], $data['from'], $data['to']);
        }
        return Request::fromArray(array_merge($data, $overrides));
    }

    /**
     * Export the rows of a request as CSV. Up to the page limit, the CSV comes back as a stream at once. A larger
     * export goes to the queue, which sends a link to the inbox of $adminUserId.
     *
     * @return array{queued: false, stream: resource, rows: int}|array{queued: true}
     * @throws Mage_Core_Exception_Input
     */
    public function export(Request $request, Mage_Reports_Model_Explorer_Access $access, ?int $adminUserId): array
    {
        $runner = Mage::getSingleton('reports/explorer_runner');
        $probe = $runner->run($request->with(['offset' => 0]), $access);
        if ((int) $probe['totalRows'] > Mage_Reports_Model_Explorer_Runner::maxRows() && $adminUserId !== null) {
            \Maho\Queue\QueueManager::dispatch(
                new Mage_Reports_Model_Queue_ExplorerExport($request->toArray(), $access->toArray(), $adminUserId),
                queue: Mage_Reports_Model_Queue_ExplorerExport::QUEUE_NAME,
            );
            return ['queued' => true];
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Cannot open a stream for the report export.');
        }
        $rows = Mage::getModel('reports/explorer_csv')->write($stream, $request, $access, Mage_Reports_Model_Explorer_Runner::maxRows());
        rewind($stream);
        return ['queued' => false, 'stream' => $stream, 'rows' => $rows];
    }

    public static function exportMaxRows(): int
    {
        return max(1, (int) Mage::getStoreConfig(self::XML_PATH_EXPORT_MAX_ROWS));
    }
}
