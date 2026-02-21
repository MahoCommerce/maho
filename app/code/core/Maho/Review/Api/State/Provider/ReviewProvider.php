<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Review\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\Review\Api\Resource\Review;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Review State Provider
 *
 * @implements ProviderInterface<Review>
 */
final class ReviewProvider implements ProviderInterface
{
    use AuthenticationTrait;

    /**
     * Reviews change less frequently, use 3x the base TTL
     */
    private function getCacheTtl(): int
    {
        return \Maho_ApiPlatform_Model_Observer::getCacheTtl() * 3;
    }

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @return ArrayPaginator<Review>|Review|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator|Review|null
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        // GraphQL: Get reviews for a product
        if ($operationName === 'productReviews') {
            $args = $context['args'] ?? [];
            return $this->getProductReviews(
                (int) $args['productId'],
                (int) ($args['page'] ?? 1),
                max(1, min((int) ($args['pageSize'] ?? 10), 100)),
            );
        }

        // GraphQL: Get current customer's reviews
        if ($operationName === 'myReviews') {
            return $this->getCustomerReviews();
        }

        // REST: /customers/me/reviews
        if ($operationName === 'my_reviews') {
            return $this->getCustomerReviews();
        }

        // REST Collection: /products/{productId}/reviews
        if ($operation instanceof CollectionOperationInterface) {
            $productId = (int) ($uriVariables['productId'] ?? 0);
            if ($productId) {
                $filters = $context['filters'] ?? [];
                return $this->getProductReviews(
                    $productId,
                    (int) ($filters['page'] ?? 1),
                    max(1, min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 10), 100)),
                );
            }
            return new ArrayPaginator(items: [], currentPage: 1, itemsPerPage: 10, totalItems: 0);
        }

        // Single review by ID
        $reviewId = (int) ($uriVariables['id'] ?? 0);
        if ($reviewId) {
            return $this->getReview($reviewId);
        }

        return null;
    }

    /**
     * Get reviews for a product (only approved reviews)
     *
     * @return ArrayPaginator<Review>
     */
    private function getProductReviews(int $productId, int $page = 1, int $pageSize = 10): ArrayPaginator
    {
        $storeId = StoreContext::getStoreId();
        $cacheKey = "api_reviews_{$productId}_{$page}_{$pageSize}_{$storeId}";

        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if ($data !== null) {
                $reviews = array_map(fn(array $r) => $this->arrayToReviewDto($r), $data['reviews']);
                return new ArrayPaginator(
                    items: $reviews,
                    currentPage: $data['page'],
                    itemsPerPage: $data['pageSize'],
                    totalItems: $data['total'],
                );
            }
        }

        /** @var \Mage_Review_Model_Resource_Review_Collection $collection */
        $collection = \Mage::getModel('review/review')->getCollection();
        $collection->addStoreFilter($storeId);
        $collection->addStatusFilter(\Mage_Review_Model_Review::STATUS_APPROVED);
        $collection->addEntityFilter('product', $productId);
        $collection->setDateOrder();

        // Pagination
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        // Add rating data
        $collection->addRateVotes();

        $total = (int) $collection->getSize();

        // Batch load product names to avoid N+1
        $productIds = [];
        /** @var \Mage_Review_Model_Review $review */
        foreach ($collection as $review) {
            $productIds[] = (int) $review->getEntityPkValue();
        }
        $productNames = $this->batchLoadProductNames(array_unique($productIds));

        $reviews = [];
        foreach ($collection as $review) {
            $reviews[] = $this->buildReview($review, $productNames);
        }

        \Mage::app()->getCache()->save(
            (string) json_encode([
                'reviews' => array_map(fn(Review $r) => $this->reviewDtoToArray($r), $reviews),
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
            ]),
            $cacheKey,
            ['API_REVIEWS'],
            $this->getCacheTtl(),
        );

        return new ArrayPaginator(
            items: $reviews,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: $total,
        );
    }

    /**
     * Get current customer's submitted reviews
     *
     * @return ArrayPaginator<Review>
     */
    private function getCustomerReviews(): ArrayPaginator
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            return new ArrayPaginator(items: [], currentPage: 1, itemsPerPage: 10, totalItems: 0);
        }

        $storeId = StoreContext::getStoreId();

        /** @var \Mage_Review_Model_Resource_Review_Collection $collection */
        $collection = \Mage::getModel('review/review')->getCollection();
        $collection->addStoreFilter($storeId);
        $collection->addCustomerFilter($customerId);
        $collection->setDateOrder();
        $collection->addRateVotes();

        // Batch load product names
        $productIds = [];
        /** @var \Mage_Review_Model_Review $review */
        foreach ($collection as $review) {
            $productIds[] = (int) $review->getEntityPkValue();
        }
        $productNames = $this->batchLoadProductNames(array_unique($productIds));

        $reviews = [];
        foreach ($collection as $review) {
            $reviews[] = $this->buildReview($review, $productNames);
        }

        $total = count($reviews);

        return new ArrayPaginator(
            items: $reviews,
            currentPage: 1,
            itemsPerPage: max($total, 100),
            totalItems: $total,
        );
    }

    /**
     * Get single review by ID
     */
    private function getReview(int $reviewId): Review
    {
        /** @var \Mage_Review_Model_Review $review */
        $review = \Mage::getModel('review/review')->load($reviewId);

        if (!$review->getId()) {
            throw new NotFoundHttpException('Review not found');
        }

        // Only show approved reviews to public
        $customerId = $this->getAuthenticatedCustomerId();
        if ((int) $review->getStatusId() !== \Mage_Review_Model_Review::STATUS_APPROVED) {
            // Allow customer to see their own pending reviews
            if (!$customerId || (int) $review->getCustomerId() !== $customerId) {
                throw new NotFoundHttpException('Review not found');
            }
        }

        return $this->buildReview($review);
    }

    /**
     * Build Review resource from model
     *
     * @param array<int, string> $productNames Pre-loaded product names keyed by product ID
     */
    private function buildReview(\Mage_Review_Model_Review $review, array $productNames = []): Review
    {
        $resource = new Review();
        $resource->id = (int) $review->getId();
        $resource->productId = (int) $review->getEntityPkValue();
        $resource->title = $review->getTitle();
        $resource->detail = $review->getDetail();
        $resource->nickname = $review->getNickname();
        $resource->createdAt = $review->getCreatedAt();
        $resource->customerId = $review->getCustomerId() ? (int) $review->getCustomerId() : null;

        // Get status
        $statusId = (int) $review->getStatusId();
        $resource->status = match ($statusId) {
            \Mage_Review_Model_Review::STATUS_APPROVED => 'approved',
            \Mage_Review_Model_Review::STATUS_PENDING => 'pending',
            \Mage_Review_Model_Review::STATUS_NOT_APPROVED => 'not_approved',
            default => 'pending',
        };

        // Get rating (average of all rating votes)
        $rating = 0;
        $ratingVotes = $review->getRatingVotes();
        if ($ratingVotes && count($ratingVotes) > 0) {
            $totalPercent = 0;
            foreach ($ratingVotes as $vote) {
                $totalPercent += (float) $vote->getPercent();
            }
            // Convert percent (0-100) to 1-5 star rating
            $rating = (int) round(($totalPercent / count($ratingVotes)) / 20);
        }
        $resource->rating = max(1, min(5, $rating));

        $resource->productName = $productNames[$resource->productId] ?? null;

        return $resource;
    }

    /**
     * Batch load product names by IDs (single query instead of N+1)
     *
     * @param array<int> $productIds
     * @return array<int, string>
     */
    private function batchLoadProductNames(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $collection = \Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('name')
            ->addIdFilter($productIds);

        $names = [];
        /** @var \Mage_Catalog_Model_Product $product */
        foreach ($collection as $product) {
            $names[(int) $product->getId()] = $product->getName();
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewDtoToArray(Review $review): array
    {
        return [
            'id' => $review->id,
            'productId' => $review->productId,
            'productName' => $review->productName,
            'title' => $review->title,
            'detail' => $review->detail,
            'nickname' => $review->nickname,
            'rating' => $review->rating,
            'status' => $review->status,
            'createdAt' => $review->createdAt,
            'customerId' => $review->customerId,
        ];
    }

    private function arrayToReviewDto(array $data): Review
    {
        $review = new Review();
        $review->id = (int) $data['id'];
        $review->productId = (int) $data['productId'];
        $review->productName = $data['productName'] ?? null;
        $review->title = $data['title'];
        $review->detail = $data['detail'];
        $review->nickname = $data['nickname'];
        $review->rating = (int) $data['rating'];
        $review->status = $data['status'];
        $review->createdAt = $data['createdAt'];
        $review->customerId = isset($data['customerId']) ? (int) $data['customerId'] : null;

        return $review;
    }
}
