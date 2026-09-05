<?php

/**
 * Product reviews with rating votes, keyed by sku, store, nickname and title.
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

class Reviews extends AbstractImporter
{
    private const STATUSES = [
        'approved' => \Mage_Review_Model_Review::STATUS_APPROVED,
        'pending' => \Mage_Review_Model_Review::STATUS_PENDING,
        'not_approved' => \Mage_Review_Model_Review::STATUS_NOT_APPROVED,
    ];

    /** @var array<string, array<int, int>> rating code => star => option id */
    private array $ratingOptions = [];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['sku', 'store_code', 'nickname', 'title', 'detail'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        $ratingCodes = $this->ratingCodes();
        foreach ($file as $line => $row) {
            foreach ($this->requiredColumns() as $column) {
                $this->requireValue($file, $line, $row, $column);
            }
            $row['product_id'] = (int) Mage::getModel('catalog/product')->getIdBySku($row['sku']);
            if ($row['product_id'] === 0) {
                $this->fail($file, $line, "unknown sku '{$row['sku']}'");
            }
            $row['store_id'] = $this->at($file, $line, fn() => $this->resolver->storeId($row['store_code']));
            $status = ($row['status'] ?? '') !== '' ? $row['status'] : 'approved';
            if (!isset(self::STATUSES[$status])) {
                $this->fail($file, $line, "status '$status' is not one of " . implode(', ', array_keys(self::STATUSES)));
            }
            $row['status_id'] = self::STATUSES[$status];
            $row['votes'] = [];
            foreach ($ratingCodes as $code) {
                $column = strtolower($code);
                if (($row[$column] ?? '') === '') {
                    continue;
                }
                if (!ctype_digit($row[$column]) || (int) $row[$column] < 1 || (int) $row[$column] > 5) {
                    $this->fail($file, $line, "$column must be a whole number from 1 to 5");
                }
                $row['votes'][$code] = (int) $row[$column];
            }
            if (($row['created_at'] ?? '') !== '' && !Mage::helper('core')->isValidDateTime($row['created_at'])) {
                $this->fail($file, $line, "created_at '{$row['created_at']}' is not a date time");
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $entityId = (int) Mage::getModel('review/review')->getEntityIdByCode(\Mage_Review_Model_Review::ENTITY_PRODUCT_CODE);
        $touched = [];
        foreach ($rows as $row) {
            $review = $this->find($entityId, $row);
            $review->getId() ? $result->updated++ : $result->created++;
            $review->setEntityId($entityId)
                ->setEntityPkValue($row['product_id'])
                ->setStatusId($row['status_id'])
                ->setTitle($row['title'])
                ->setDetail($row['detail'])
                ->setNickname($row['nickname'])
                ->setStoreId($row['store_id'])
                ->setStores([$row['store_id']]);
            $review->save();
            if (($row['created_at'] ?? '') !== '') {
                Mage::getSingleton('core/resource')->getConnection('core_write')->update(
                    Mage::getSingleton('core/resource')->getTableName('review/review'),
                    ['created_at' => Mage::app()->getLocale()->formatDateForDb($row['created_at'])],
                    ['review_id = ?' => (int) $review->getId()],
                );
            }
            $this->replaceVotes($review, $row['votes']);
            $touched[$row['product_id']] = $review;
        }
        foreach ($touched as $review) {
            $review->aggregate();
        }
        $reporter->info(count($touched) . ' products re-aggregated');
        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function find(int $entityId, array $row): \Mage_Review_Model_Review
    {
        $collection = Mage::getResourceModel('review/review_collection')
            ->addEntityFilter($entityId, $row['product_id'])
            ->addStoreFilter($row['store_id']);
        foreach ($collection as $candidate) {
            if ($candidate->getTitle() === $row['title'] && $candidate->getNickname() === $row['nickname']) {
                return Mage::getModel('review/review')->load($candidate->getId());
            }
        }
        return Mage::getModel('review/review');
    }

    /**
     * @param array<string, int> $votes
     */
    private function replaceVotes(\Mage_Review_Model_Review $review, array $votes): void
    {
        $existing = Mage::getResourceModel('rating/rating_option_vote_collection')->setReviewFilter((int) $review->getId());
        foreach ($existing as $vote) {
            $vote->delete();
        }
        foreach ($votes as $code => $stars) {
            $options = $this->ratingOptions[$code] ??= $this->optionsOf($code);
            Mage::getModel('rating/rating')
                ->setRatingId($options['rating_id'])
                ->setReviewId((int) $review->getId())
                ->addOptionVote($options[$stars], (string) $review->getEntityPkValue());
        }
    }

    /**
     * @return array<int|string, int>
     */
    private function optionsOf(string $code): array
    {
        $rating = Mage::getModel('rating/rating')->load($code, 'rating_code');
        $options = ['rating_id' => (int) $rating->getId()];
        foreach ($rating->getOptions() as $option) {
            $options[(int) $option->getValue()] = (int) $option->getId();
        }
        return $options;
    }

    /**
     * @return list<string>
     */
    private function ratingCodes(): array
    {
        $entityId = (int) Mage::getModel('rating/rating')->getEntityIdByCode(\Mage_Rating_Model_Rating::ENTITY_PRODUCT_CODE);
        $codes = [];
        foreach (Mage::getResourceModel('rating/rating_collection')->addEntityFilter($entityId) as $rating) {
            $codes[] = (string) $rating->getRatingCode();
        }
        return $codes;
    }
}
