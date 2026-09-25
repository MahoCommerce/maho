<?php

/**
 * Product view events of a store view, up to the number of views each row asks for, spread over the last days.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\AbstractImporter;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

class ProductViews extends AbstractImporter
{
    /** Skip the refresh of the report statistics, for a caller that refreshes them once after several files. */
    public const OPTION_SKIP_STATISTICS = 'skip_statistics';

    /** The report statistics that read the view events. */
    public const STATISTICS = ['viewed'];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['sku', 'store_code', 'views'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach ($file as $line => $row) {
            foreach ($this->requiredColumns() as $column) {
                $this->requireValue($file, $line, $row, $column);
            }
            $row['product_id'] = (int) Mage::getModel('catalog/product')->getIdBySku($row['sku']);
            if ($row['product_id'] === 0) {
                $this->fail($file, $line, "unknown sku '{$row['sku']}'");
            }
            $row['store_id'] = $this->at($file, $line, fn() => $this->resolver->storeId($row['store_code']));
            $row['days'] = ($row['days'] ?? '') !== '' ? $row['days'] : '30';
            if (!ctype_digit($row['views'])) {
                $this->fail($file, $line, 'views must be a whole number');
            }
            if (!ctype_digit($row['days']) || (int) $row['days'] < 1) {
                $this->fail($file, $line, 'days must be a whole number of 1 or more');
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    /**
     * The file gives the number of views a product has in a store, so a rerun adds only the views that are missing.
     */
    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('reports/event');
        $now = time();
        foreach ($rows as $row) {
            $have = (int) $write->fetchOne(
                $write->select()->from($table, 'COUNT(*)')
                    ->where('event_type_id = ?', \Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW)
                    ->where('object_id = ?', $row['product_id'])
                    ->where('store_id = ?', $row['store_id']),
            );
            $want = (int) $row['views'];
            if ($have >= $want) {
                continue;
            }
            $span = (int) $row['days'] * 86400;
            $seed = crc32($row['sku'] . '@' . $row['store_code']);
            $events = [];
            for ($i = $have; $i < $want; $i++) {
                $events[] = [
                    'logged_at' => gmdate('Y-m-d H:i:s', $now - ($seed + $i * 7919 * 97) % $span),
                    'event_type_id' => \Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW,
                    'object_id' => $row['product_id'],
                    'subject_id' => 0,
                    'subtype' => 1,
                    'store_id' => $row['store_id'],
                ];
            }
            // One insert binds 6 values for each view, and PostgreSQL accepts 65535 values at most.
            foreach (array_chunk($events, 1000) as $chunk) {
                $write->insertMultiple($table, $chunk);
            }
            $have === 0 ? $result->created++ : $result->updated++;
        }
        if ($result->created + $result->updated > 0 && !($options[self::OPTION_SKIP_STATISTICS] ?? false)) {
            Mage::getModel('reports/statistics')->refreshLifetime(self::STATISTICS);
        }
        return $result;
    }
}
