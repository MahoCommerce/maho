<?php

/**
 * CMS static blocks keyed by identifier and store set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

class CmsBlocks extends AbstractCmsImporter
{
    #[\Override]
    protected function requiredColumns(): array
    {
        return ['identifier'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach ($file as $line => $row) {
            $identifier = $this->requireValue($file, $line, $row, 'identifier');
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $identifier)) {
                $this->fail($file, $line, "identifier '$identifier' must be lowercase letters, digits, dashes or underscores");
            }
            $row['store_ids'] = $this->storeIds($file, $line, $row);
            $row['is_active'] = $this->at($file, $line, fn() => CsvFile::bool($row['is_active'] ?? '', true));
            $row['block_id'] = $this->find($identifier, $row['store_ids']);
            $row['body'] = $this->content($file, $line, $row, $options, $row['block_id'] === null);
            if ($row['block_id'] === null && ($row['title'] ?? '') === '') {
                $this->fail($file, $line, 'title is required for a new block');
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        foreach ($rows as $row) {
            $block = Mage::getModel('cms/block');
            if ($row['block_id'] !== null) {
                $block->load($row['block_id']);
                $result->updated++;
            } else {
                $result->created++;
            }
            $block->setIdentifier($row['identifier'])->setStores($row['store_ids'])->setIsActive($row['is_active'] ? 1 : 0);
            if (($row['title'] ?? '') !== '') {
                $block->setTitle($row['title']);
            }
            if ($row['body'] !== null) {
                $block->setContent($row['body']);
            }
            $block->save();
        }
        $this->resolver->reset();
        $reporter->info(count($rows) . ' blocks');
        return $result;
    }

    /**
     * @param list<int> $storeIds
     */
    private function find(string $identifier, array $storeIds): ?int
    {
        $blocks = Mage::getResourceModel('cms/block_collection')->addFieldToFilter('identifier', $identifier);
        foreach ($blocks as $candidate) {
            $block = Mage::getModel('cms/block')->load($candidate->getId());
            $stores = array_map(intval(...), (array) $block->getStoreId());
            sort($stores);
            if ($stores === $storeIds) {
                return (int) $block->getId();
            }
        }
        return null;
    }
}
