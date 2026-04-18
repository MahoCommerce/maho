<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Review
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Review\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Resource;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\CacheTrait;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Review Provider — extends CrudProvider with review-specific operations.
 *
 * Handles product reviews, customer reviews, and caching.
 * DTO construction uses CrudResource::fromModel() via afterLoad for computed fields.
 */
final class ReviewProvider extends CrudProvider
{
    use CacheTrait;


    private function getCacheTtl(): int
    {
        return \Maho_ApiPlatform_Model_Observer::getCacheTtl() * 3;
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $this->resourceClass = $operation->getClass();
        if (is_subclass_of($this->resourceClass, CrudResource::class)) {
            $this->modelAlias = $this->resourceClass::metadata()->model;
        }

        $operationName = $operation->getName();

        if ($operationName === 'productReviews') {
            ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 10, 100);
            return $this->getProductReviews(
                (int) ($context['args']['productId'] ?? 0),
                $page,
                $pageSize,
            );
        }

        if ($operationName === 'myReviews' || $operationName === 'my_reviews') {
            return $this->getCustomerReviews();
        }

        if ($operation instanceof CollectionOperationInterface) {
            $productId = (int) ($uriVariables['productId'] ?? 0);
            if ($productId) {
                ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 10, 100);
                return $this->getProductReviews($productId, $page, $pageSize);
            }
            return new TraversablePaginator(new \ArrayIterator([]), 1, 10, 0);
        }

        $reviewId = (int) ($uriVariables['id'] ?? 0);
        if ($reviewId) {
            return $this->getReview($reviewId);
        }

        return null;
    }

    /**
     * @return TraversablePaginator<Review>
     */
    private function getProductReviews(int $productId, int $page = 1, int $pageSize = 10): TraversablePaginator
    {
        $storeId = StoreContext::getStoreId();
        $cacheKey = "api_reviews_{$productId}_{$page}_{$pageSize}_{$storeId}";

        return $this->remember(
            $cacheKey,
            ['API_REVIEWS'],
            $this->getCacheTtl(),
            compute: function () use ($storeId, $productId, $page, $pageSize): TraversablePaginator {
                /** @var \Mage_Review_Model_Resource_Review_Collection $collection */
                $collection = \Mage::getModel('review/review')->getCollection();
                $collection->addStoreFilter($storeId);
                $collection->addStatusFilter(\Mage_Review_Model_Review::STATUS_APPROVED);
                $collection->addEntityFilter('product', $productId);
                $collection->setDateOrder();

                $collection->setPageSize($pageSize);
                $collection->setCurPage($page);
                $collection->addRateVotes();

                $total = (int) $collection->getSize();

                $productIds = [];
                foreach ($collection as $review) {
                    $productIds[] = (int) $review->getEntityPkValue();
                }
                $productNames = $this->batchLoadProductNames(array_unique($productIds));

                $reviews = [];
                foreach ($collection as $review) {
                    /** @var Review $dto */
                    $dto = $this->toDto($review);
                    $dto->productName = $productNames[$dto->productId] ?? null;
                    $reviews[] = $dto;
                }

                return new TraversablePaginator(new \ArrayIterator($reviews), $page, $pageSize, $total);
            },
            serialize: fn(TraversablePaginator $result): array => [
                'reviews' => array_map(fn(Review $r) => $this->reviewDtoToArray($r), iterator_to_array($result)),
                'page' => (int) $result->getCurrentPage(),
                'pageSize' => (int) $result->getItemsPerPage(),
                'total' => (int) $result->getTotalItems(),
            ],
            deserialize: fn(array $data): TraversablePaginator => new TraversablePaginator(
                new \ArrayIterator(array_map(fn(array $r) => $this->arrayToReviewDto($r), $data['reviews'])),
                $data['page'],
                $data['pageSize'],
                $data['total'],
            ),
        );
    }

    /**
     * @return TraversablePaginator<Review>
     */
    private function getCustomerReviews(): TraversablePaginator
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            return new TraversablePaginator(new \ArrayIterator([]), 1, 10, 0);
        }

        $storeId = StoreContext::getStoreId();

        /** @var \Mage_Review_Model_Resource_Review_Collection $collection */
        $collection = \Mage::getModel('review/review')->getCollection();
        $collection->addStoreFilter($storeId);
        $collection->addCustomerFilter($customerId);
        $collection->setDateOrder();
        $collection->addRateVotes();

        $productIds = [];
        foreach ($collection as $review) {
            $productIds[] = (int) $review->getEntityPkValue();
        }
        $productNames = $this->batchLoadProductNames(array_unique($productIds));

        $reviews = [];
        foreach ($collection as $review) {
            /** @var Review $dto */
            $dto = $this->toDto($review);
            $dto->productName = $productNames[$dto->productId] ?? null;
            $reviews[] = $dto;
        }

        $total = count($reviews);

        return new TraversablePaginator(new \ArrayIterator($reviews), 1, max($total, 100), $total);
    }

    private function getReview(int $reviewId): Review
    {
        /** @var \Mage_Review_Model_Review $review */
        $review = \Mage::getModel('review/review')->load($reviewId);

        if (!$review->getId()) {
            throw new NotFoundHttpException('Review not found');
        }

        $customerId = $this->getAuthenticatedCustomerId();
        if ((int) $review->getStatusId() !== \Mage_Review_Model_Review::STATUS_APPROVED) {
            if (!$customerId || (int) $review->getCustomerId() !== $customerId) {
                throw new NotFoundHttpException('Review not found');
            }
        }

        /** @var Review $dto */
        $dto = $this->toDto($review);

        $productNames = $this->batchLoadProductNames([$dto->productId]);
        $dto->productName = $productNames[$dto->productId] ?? null;

        return $dto;
    }

    /**
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
