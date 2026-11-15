<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('stores a whole percent when the average of the votes is a fraction', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $rating = Mage::getModel('rating/rating')->getCollection()->addEntityFilter('product')->getFirstItem();
    $options = [];
    foreach (Mage::getModel('rating/rating_option')->getCollection()->addRatingFilter($rating->getId()) as $option) {
        $options[(int) $option->getValue()] = (int) $option->getId();
    }
    if (!$rating->getId() || !isset($options[4], $options[5])) {
        $this->markTestSkipped('Needs a product rating with the options 4 and 5');
    }
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $productId = (int) $adapter->fetchOne(
        $adapter->select()->from(['p' => $resource->getTableName('catalog/product')], ['entity_id'])
            ->where('p.entity_id NOT IN (?)', $adapter->select()->from($resource->getTableName('rating/rating_option_vote'), ['entity_pk_value']))
            ->order('p.entity_id')->limit(1),
    );

    $ratingStore = $resource->getTableName('rating/rating_store');
    $addedStores = [];
    foreach ([0, $storeId] as $id) {
        $linked = $adapter->fetchOne(
            $adapter->select()->from($ratingStore, ['rating_id'])->where('rating_id = ?', (int) $rating->getId())->where('store_id = ?', $id),
        );
        if (!$linked) {
            $adapter->insert($ratingStore, ['rating_id' => (int) $rating->getId(), 'store_id' => $id]);
            $addedStores[] = $id;
        }
    }

    $reviews = [];
    try {
        // 5 + 4 + 4 gives an average of 4.333, which is 86.67 percent
        foreach ([5, 4, 4] as $value) {
            $review = Mage::getModel('review/review');
            $review->setEntityId($review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue($productId)
                ->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)
                ->setTitle('Aggregate percent')
                ->setDetail('A vote for the aggregate percent test.')
                ->setNickname('PestAggregate')
                ->setStoreId($storeId)
                ->setStores([$storeId])
                ->save();
            $reviews[] = $review;
            Mage::getModel('rating/rating')
                ->setRatingId($rating->getId())
                ->setReviewId($review->getId())
                ->addOptionVote($options[$value], $productId);
        }

        $rows = $adapter->fetchAll(
            $adapter->select()->from($resource->getTableName('rating/rating_vote_aggregated'), ['percent', 'percent_approved'])
                ->where('rating_id = ?', (int) $rating->getId())
                ->where('entity_pk_value = ?', $productId),
        );

        expect($rows)->not->toBeEmpty();
        foreach ($rows as $row) {
            expect((int) $row['percent'])->toBe(87)
                ->and((int) $row['percent_approved'])->toBe(87);
        }
    } finally {
        foreach ($reviews as $review) {
            $review->delete();
        }
        $adapter->delete($resource->getTableName('rating/rating_vote_aggregated'), ['entity_pk_value = ?' => $productId]);
        foreach ($addedStores as $id) {
            $adapter->delete($ratingStore, ['rating_id = ?' => (int) $rating->getId(), 'store_id = ?' => $id]);
        }
    }
});
