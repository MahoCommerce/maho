<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\Review;
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

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @return array<Review>|Review|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Review|null
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        // GraphQL: Get reviews for a product
        if ($operationName === 'productReviews') {
            $args = $context['args'] ?? [];
            return $this->getProductReviews(
                (int) $args['productId'],
                (int) ($args['page'] ?? 1),
                (int) ($args['pageSize'] ?? 10),
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
                    (int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 10),
                );
            }
            return [];
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
     * @return array<Review>
     */
    private function getProductReviews(int $productId, int $page = 1, int $pageSize = 10): array
    {
        $storeId = StoreContext::getStoreId();

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

        $reviews = [];
        /** @var \Mage_Review_Model_Review $review */
        foreach ($collection as $review) {
            $reviews[] = $this->buildReview($review);
        }

        return $reviews;
    }

    /**
     * Get current customer's submitted reviews
     *
     * @return array<Review>
     */
    private function getCustomerReviews(): array
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            return [];
        }

        $storeId = StoreContext::getStoreId();

        /** @var \Mage_Review_Model_Resource_Review_Collection $collection */
        $collection = \Mage::getModel('review/review')->getCollection();
        $collection->addStoreFilter($storeId);
        $collection->addCustomerFilter($customerId);
        $collection->setDateOrder();
        $collection->addRateVotes();

        $reviews = [];
        /** @var \Mage_Review_Model_Review $review */
        foreach ($collection as $review) {
            $reviews[] = $this->buildReview($review);
        }

        return $reviews;
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
     */
    private function buildReview(\Mage_Review_Model_Review $review): Review
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

        // Get product name
        try {
            $product = \Mage::getModel('catalog/product')->load($review->getEntityPkValue());
            $resource->productName = $product->getName();
        } catch (\Exception $e) {
            $resource->productName = null;
        }

        return $resource;
    }
}
