<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
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

/**
 * @return int the ID of a product that the storefront of the store 1 shows
 */
function reviewVoteProductId(): int
{
    /** @var Mage_Catalog_Service_Product $products */
    $products = Mage::getService('catalog/product');
    $collection = Mage::getResourceModel('catalog/product_collection');
    $products->addVisibleFilter($collection, 1);
    return (int) $collection->setPageSize(1)->getFirstItem()->getId();
}

/**
 * @return list<int> the IDs of the reviews with the title $title
 */
function reviewVoteReviewIds(string $title): array
{
    $collection = Mage::getModel('review/review')->getCollection();
    $collection->getSelect()->where('detail.title = ?', $title);
    return array_values(array_map(fn(Mage_Review_Model_Review $review): int => (int) $review->getId(), $collection->getItems()));
}

/**
 * Post the review form of the product page of the store 1 as a guest, as the browser does.
 *
 * @param array<int, int> $votes the option ID by the rating ID
 * @return array{success: list<string>, errors: list<string>}
 */
function reviewVoteViaStorefront(int $productId, string $title, array $votes): array
{
    $adminStore = Mage::app()->getStore();
    Mage::app()->setCurrentStore(1);
    Mage::app()->getStore()->setConfig(Mage_Review_Service_Review::XML_PATH_GUEST_ALLOWED, '1');
    Mage::unregister('current_product');
    Mage::unregister('product');
    Mage::getSingleton('core/session')->getMessages(true);
    try {
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/review/product/post', 'POST', [
            'id' => $productId,
            'title' => $title,
            'detail' => 'Pest vote detail',
            'nickname' => 'Pest',
            'ratings' => $votes,
        ]));
        $request->setRouteName('review')->setControllerName('product')->setActionName('post')->setDispatched(true);
        new Mage_Review_ProductController($request, new Mage_Core_Controller_Response_Http())->postAction();
    } finally {
        Mage::app()->setCurrentStore($adminStore);
        Mage::unregister('current_product');
        Mage::unregister('product');
    }

    $messages = ['success' => [], 'errors' => []];
    foreach (Mage::getSingleton('core/session')->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

beforeEach(function (): void {
    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    $this->title = 'Pest vote ' . uniqid();
});

afterEach(function (): void {
    foreach (reviewVoteReviewIds($this->title) as $reviewId) {
        Mage::getModel('review/review')->load($reviewId)->delete();
    }
});

it('refuses the option of another rating in the admin save and in the storefront form', function (): void {
    $options = reviewVoteRatingOptions();
    if (count($options) < 2) {
        $this->markTestSkipped('The store has fewer than two product ratings');
    }
    [$ratingId, $otherRatingId] = array_keys($options);
    $votes = [$ratingId => $options[$otherRatingId][0]];
    $productId = reviewVoteProductId();
    expect($productId)->toBeGreaterThan(0);

    $review = Mage::getModel('review/review');
    $review->setEntityPkValue($productId)
        ->setStatusId(Mage_Review_Model_Review::STATUS_PENDING)
        ->setStoreId(1)
        ->setStores([1])
        ->setTitle($this->title)
        ->setDetail('Pest vote detail')
        ->setNickname('Pest');
    Mage::getService('review/review')->save($review);
    $reviewId = (int) $review->getId();

    $admin = AdminApiParity::admin(Mage_Adminhtml_Catalog_Product_ReviewController::class, 'save', [
        'id' => $reviewId,
        'status_id' => Mage_Review_Model_Review::STATUS_PENDING,
        'title' => $this->title,
        'detail' => 'Pest vote detail',
        'nickname' => 'Pest',
        'stores' => [1],
        'ratings' => $votes,
    ]);
    $storefront = reviewVoteViaStorefront($productId, $this->title, $votes);

    expect($storefront)->toBe(['success' => [], 'errors' => ['Please select a valid rating.']])
        ->and($admin['errors'])->toBe($storefront['errors'])
        ->and(reviewVoteReviewIds($this->title))->toBe([$reviewId])
        ->and(reviewVoteCount($reviewId))->toBe(0);
});

it('saves the option of the rating in the admin form and in the storefront form', function (): void {
    $options = reviewVoteRatingOptions();
    if ($options === []) {
        $this->markTestSkipped('The store has no product rating');
    }
    $ratingId = array_key_first($options);
    $votes = [$ratingId => $options[$ratingId][0]];
    $productId = reviewVoteProductId();
    expect($productId)->toBeGreaterThan(0);

    $admin = AdminApiParity::admin(Mage_Adminhtml_Catalog_Product_ReviewController::class, 'post', [
        'product_id' => $productId,
        'status_id' => Mage_Review_Model_Review::STATUS_PENDING,
        'title' => $this->title,
        'detail' => 'Pest vote detail',
        'nickname' => 'Pest',
        'select_stores' => [1],
        'ratings' => $votes,
    ]);
    $storefront = reviewVoteViaStorefront($productId, $this->title, $votes);

    $reviewIds = reviewVoteReviewIds($this->title);
    expect($admin['errors'])->toBe([])
        ->and($storefront['errors'])->toBe([])
        ->and($reviewIds)->toHaveCount(2)
        ->and(array_map(reviewVoteCount(...), $reviewIds))->toBe([1, 1]);
});
