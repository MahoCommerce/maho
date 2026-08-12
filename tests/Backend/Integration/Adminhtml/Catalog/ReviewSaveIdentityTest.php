<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin review save action loads the review named by the trusted url `id` and then applies
 * the raw POST body with addData(). Without a hard rebind of the primary key, a `review_id` in
 * the body repoints the save to a different review. The save must always write the review the
 * url selected, whatever the body carries.
 */

function reviewIdentityCreateReview(int $productId, string $detail): int
{
    $review = Mage::getModel('review/review');
    $review->setEntityId($review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
        ->setEntityPkValue($productId)
        ->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)
        ->setStoreId(1)
        ->setStores([1])
        ->setTitle('Title')
        ->setDetail($detail)
        ->setNickname('Nick')
        ->save();

    return (int) $review->getId();
}

function reviewIdentityDetail(int $reviewId): ?string
{
    $resource = Mage::getSingleton('core/resource');
    $adapter  = $resource->getConnection('core_read');
    $select   = $adapter->select()
        ->from($resource->getTableName('review/review_detail'), ['detail'])
        ->where('review_id = ?', $reviewId);

    $value = $adapter->fetchOne($select);
    return $value === false ? null : $value;
}

it('saves the review named by the url id, not a review_id injected in the body', function () {
    $productId = (int) Mage::getResourceModel('catalog/product_collection')
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    if ($productId === 0) {
        $productId = 1;
    }

    $targetId   = reviewIdentityCreateReview($productId, 'target-original');
    $victimId   = reviewIdentityCreateReview($productId, 'victim-original');

    // Attacker edits the target review (url id) but smuggles the victim's review_id in the body.
    $params = [
        'id'        => $targetId,
        'review_id' => $victimId,
        'status_id' => Mage_Review_Model_Review::STATUS_APPROVED,
        'title'     => 'Title',
        'detail'    => 'hijacked',
        'nickname'  => 'Nick',
        'stores'    => [1],
    ];

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/catalog_product_review/save', 'POST', $params),
    );
    $request->setRouteName('adminhtml')
        ->setControllerName('catalog_product_review')
        ->setActionName('save')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Adminhtml_Catalog_Product_ReviewController(
        $request,
        new Mage_Core_Controller_Response_Http(),
    );

    try {
        $controller->saveAction();
    } catch (Throwable) {
        // The trailing redirect builds an admin url that needs no assertion here; the save has
        // already run by this point. Any other failure still surfaces through the assertions.
    }

    expect(reviewIdentityDetail($victimId))->toBe('victim-original')
        ->and(reviewIdentityDetail($targetId))->toBe('hijacked');

    // Load before deleting: the post-delete re-aggregation reads entity_id off the model.
    Mage::getModel('review/review')->load($targetId)->delete();
    Mage::getModel('review/review')->load($victimId)->delete();
});
