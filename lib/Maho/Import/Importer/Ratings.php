<?php

/**
 * Product ratings keyed by code; ratings missing from the file are deactivated.
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
            foreach (['position', 'is_active'] as $column) {
                if (($row[$column] ?? '') !== '' && !ctype_digit($row[$column])) {
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
            $rating->setIsActive(($row['is_active'] ?? '') === '' ? 1 : (int) $row['is_active']);
            $rating->save();
            $this->ensureOptions((int) $rating->getId());
        }
        $others = Mage::getResourceModel('rating/rating_collection')
            ->addEntityFilter($entityId)
            ->addFieldToFilter('rating_code', ['nin' => $listed]);
        $deactivated = 0;
        foreach ($others as $rating) {
            if ((int) $rating->getIsActive() === 1) {
                Mage::getModel('rating/rating')->load($rating->getId())->setIsActive(0)->save();
                $deactivated++;
            }
        }
        if ($deactivated > 0) {
            $reporter->info("$deactivated rating(s) not in the file deactivated");
        }
        return $result;
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
