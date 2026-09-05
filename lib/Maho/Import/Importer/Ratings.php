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
    private const STARS = 5;

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
        $entityId = (int) Mage::getModel('rating/rating')->getEntityIdByCode(\Mage_Rating_Model_Rating::ENTITY_PRODUCT_CODE);
        $listed = [];
        foreach ($rows as $row) {
            $listed[] = $row['code'];
            $rating = Mage::getModel('rating/rating')->load($row['code'], 'rating_code');
            $rating->getId() ? $result->updated++ : $result->created++;
            $rating->setEntityId($entityId)->setRatingCode($row['code']);
            if (($row['position'] ?? '') !== '') {
                $rating->setPosition($row['position']);
            }
            $rating->setStores($row['store_ids'])->save();
            $this->ensureOptions((int) $rating->getId());
        }
        $others = Mage::getResourceModel('rating/rating_collection')
            ->addEntityFilter($entityId)
            ->addFieldToFilter('rating_code', ['nin' => $listed]);
        $hidden = 0;
        foreach ($others as $other) {
            $rating = Mage::getModel('rating/rating')->load($other->getId());
            if ((array) $rating->getStores() !== []) {
                $rating->setStores([])->save();
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

    /**
     * A rating votes through its options, one per star: a new rating gets five of them.
     */
    private function ensureOptions(int $ratingId): void
    {
        $existing = Mage::getResourceModel('rating/rating_option_collection')->addRatingFilter($ratingId)->count();
        if ($existing > 0) {
            return;
        }
        for ($star = 1; $star <= self::STARS; $star++) {
            Mage::getModel('rating/rating_option')
                ->setRatingId($ratingId)
                ->setCode((string) $star)
                ->setValue($star)
                ->setPosition($star)
                ->save();
        }
    }
}
