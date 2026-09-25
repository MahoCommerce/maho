<?php

/**
 * Search terms of a store view with their popularity, keyed by query text and store, dated back from now by hours_ago.
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

class SearchTerms extends AbstractImporter
{
    #[\Override]
    protected function requiredColumns(): array
    {
        return ['store_code', 'query_text', 'popularity'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach ($file as $line => $row) {
            foreach ($this->requiredColumns() as $column) {
                $this->requireValue($file, $line, $row, $column);
            }
            $row['store_id'] = $this->at($file, $line, fn() => $this->resolver->storeId($row['store_code']));
            foreach (['popularity' => '1', 'num_results' => '0', 'hours_ago' => '0'] as $column => $default) {
                $row[$column] = ($row[$column] ?? '') !== '' ? $row[$column] : $default;
                if (!ctype_digit($row[$column])) {
                    $this->fail($file, $line, "$column must be a whole number");
                }
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $resource = Mage::getSingleton('core/resource');
        $now = time();
        foreach ($rows as $row) {
            $id = Mage::getResourceModel('catalogsearch/query_collection')
                ->addFieldToFilter('query_text', $row['query_text'])
                ->addFieldToFilter('store_id', (int) $row['store_id'])
                ->getFirstItem()
                ->getId();
            $query = Mage::getModel('catalogsearch/query');
            if ($id) {
                $query->load($id);
                $result->updated++;
            } else {
                $result->created++;
            }
            // setStoreId() of the query model returns nothing, so it cannot sit in the chain.
            $query->setStoreId((int) $row['store_id']);
            $query->setQueryText($row['query_text'])
                ->setPopularity((int) $row['popularity'])
                ->setNumResults((int) $row['num_results'])
                ->setDisplayInTerms(true)
                ->setIsActive(true)
                ->save();
            $resource->getConnection('core_write')->update(
                $resource->getTableName('catalogsearch/search_query'),
                ['updated_at' => gmdate('Y-m-d H:i:s', $now - (int) $row['hours_ago'] * 3600)],
                ['query_id = ?' => (int) $query->getId()],
            );
        }
        return $result;
    }
}
