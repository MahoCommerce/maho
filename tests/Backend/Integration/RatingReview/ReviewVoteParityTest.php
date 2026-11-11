<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The storefront, the API and the admin save the votes of a review through Mage_Review_Service_Review (#1348).
 * Each of them refuses an option that is not an option of its rating.
 */

/**
 * @return array<int, list<int>> the option IDs of each product rating of the store 1, by rating ID
 */
function reviewVoteRatingOptions(): array
{
    $ratings = Mage::getModel('rating/rating')->getResourceCollection()
        ->addEntityFilter(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE)
        ->setStoreFilter(1)
        ->load()
        ->addOptionToItems();
    $options = [];
    foreach ($ratings as $rating) {
        foreach ((array) $rating->getOptions() as $option) {
            $options[(int) $rating->getId()][] = (int) $option->getId();
        }
    }
    return $options;
}

function reviewVoteCount(int $reviewId): int
{
    return Mage::getModel('rating/rating_option_vote')->getResourceCollection()->setReviewFilter($reviewId)->getSize();
}

beforeEach(function (): void {
    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    $this->reviewIds = [];
});

afterEach(function (): void {
    foreach ($this->reviewIds as $reviewId) {
        Mage::getModel('review/review')->load($reviewId)->delete();
    }
});

it('refuses the option of another rating in the admin save and in the storefront submit', function (): void {
    $options = reviewVoteRatingOptions();
    if (count($options) < 2) {
        $this->markTestSkipped('The store has fewer than two product ratings');
    }
    [$ratingId, $otherRatingId] = array_keys($options);
    $votes = [$ratingId => $options[$otherRatingId][0]];

    /** @var Mage_Catalog_Service_Product $products */
    $products = Mage::getService('catalog/product');
    $collection = Mage::getResourceModel('catalog/product_collection');
    $products->addVisibleFilter($collection, 1);
    $productId = (int) $collection->setPageSize(1)->getFirstItem()->getId();
    expect($productId)->toBeGreaterThan(0);

    /** @var Mage_Review_Service_Review $service */
    $service = Mage::getService('review/review');
    $review = Mage::getModel('review/review');
    $review->setEntityPkValue($productId)
        ->setStatusId(Mage_Review_Model_Review::STATUS_PENDING)
        ->setStoreId(1)
        ->setStores([1])
        ->setTitle('Pest vote')
        ->setDetail('Pest vote detail')
        ->setNickname('Pest');
    $service->save($review);
    $this->reviewIds[] = $reviewId = (int) $review->getId();

    $admin = AdminApiParity::admin(Mage_Adminhtml_Catalog_Product_ReviewController::class, 'save', [
        'id' => $reviewId,
        'status_id' => Mage_Review_Model_Review::STATUS_PENDING,
        'title' => 'Pest vote',
        'detail' => 'Pest vote detail',
        'nickname' => 'Pest',
        'stores' => [1],
        'ratings' => $votes,
    ]);

    $customerId = (int) Mage::getResourceModel('customer/customer_collection')->setPageSize(1)->getFirstItem()->getId();
    $submitted = Mage::getModel('review/review');
    $submitted->setEntityPkValue($productId)->setTitle('Pest vote')->setDetail('Pest vote detail')->setNickname('Pest');
    $storefront = [];
    try {
        $service->submit($submitted, $votes, 1, $customerId, Mage_Review_Model_Channel::Web);
        $this->reviewIds[] = (int) $submitted->getId();
    } catch (Mage_Core_Exception_Input $e) {
        $storefront = array_column($e->getErrors(), 'message');
    }

    expect($storefront)->toBe(['Please select a valid rating.'])
        ->and($admin['errors'])->toBe($storefront)
        ->and(reviewVoteCount($reviewId))->toBe(0);
});

it('saves the option of the rating in the admin save', function (): void {
    $options = reviewVoteRatingOptions();
    if ($options === []) {
        $this->markTestSkipped('The store has no product rating');
    }
    $ratingId = array_key_first($options);
    $productId = (int) Mage::getResourceModel('catalog/product_collection')->setPageSize(1)->getFirstItem()->getId();

    /** @var Mage_Review_Service_Review $service */
    $service = Mage::getService('review/review');
    $review = Mage::getModel('review/review');
    $review->setEntityPkValue($productId)
        ->setStatusId(Mage_Review_Model_Review::STATUS_PENDING)
        ->setStoreId(1)
        ->setStores([1])
        ->setTitle('Pest vote')
        ->setDetail('Pest vote detail')
        ->setNickname('Pest');
    $service->save($review, [$ratingId => $options[$ratingId][0]]);
    $this->reviewIds[] = (int) $review->getId();

    expect(reviewVoteCount((int) $review->getId()))->toBe(1);
});
