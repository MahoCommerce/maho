<?php

/**
 * Product ratings keyed by code; ratings missing from the file are hidden from every store.
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

class Ratings extends AbstractImporter
{
    #[\Override]
    protected function requiredColumns(): array
    {
        return ['code'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        $seen = [];
        foreach ($file as $line => $row) {
            $this->requireValue($file, $line, $row, 'code');
            if (isset($seen[$row['code']])) {
                $this->fail($file, $line, "rating '{$row['code']}' appears twice");
            }
            $seen[$row['code']] = true;
            if (($row['position'] ?? '') !== '' && !ctype_digit($row['position'])) {
                $this->fail($file, $line, 'position must be a whole number');
            }
            $row['store_ids'] = ($row['stores'] ?? '') === ''
                ? $this->allStoreIds()
                : array_map(fn(string $code): int => $this->at($file, $line, fn() => $this->resolver->storeId(trim($code))), explode('|', $row['stores']));
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        /** @var \Mage_Rating_Service_Rating $service */
        $service = Mage::getService('rating/rating');
        $entityId = (int) Mage::getModel('rating/rating')->getEntityIdByCode(\Mage_Rating_Model_Rating::ENTITY_PRODUCT_CODE);
        $listed = [];
        foreach ($rows as $row) {
            $listed[] = $row['code'];
            $rating = Mage::getModel('rating/rating')->load($row['code'], 'rating_code');
            $rating->getId() ? $result->updated++ : $result->created++;
            $rating->setEntityId($entityId)->setRatingCode($row['code']);
            if (($row['position'] ?? '') !== '') {
                $rating->setPosition((int) $row['position']);
            }
            $service->save($rating->setStores($row['store_ids']));
        }
        $others = Mage::getResourceModel('rating/rating_collection')->addEntityFilter($entityId);
        if ($listed !== []) {
            // An empty list would render as NOT IN (NULL), which matches nothing
            $others->addFieldToFilter('rating_code', ['nin' => $listed]);
        }
        $hidden = 0;
        foreach ($others as $other) {
            $rating = Mage::getModel('rating/rating')->load($other->getId());
            if (array_diff(array_map(intval(...), (array) $rating->getStores()), [\Mage_Core_Model_App::ADMIN_STORE_ID]) !== []) {
                $service->save($rating->setStores([]));
                $hidden++;
            }
        }
        if ($hidden > 0) {
            $reporter->info("$hidden rating(s) not in the file removed from every store");
        }
        return $result;
    }

    /**
     * @return list<int>
     */
    private function allStoreIds(): array
    {
        $ids = [];
        foreach (Mage::app()->getStores() as $store) {
            $ids[] = (int) $store->getId();
        }
        return $ids;
    }
}
