<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

namespace Mage\Review\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Resource;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\CacheTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Review Provider, extends CrudProvider with review-specific operations.
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

        if ($operationName === 'product') {
            ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 10, 100);
            return $this->getProductReviews(
                (int) ($context['args']['productId'] ?? 0),
                $page,
                $pageSize,
            );
        }

        if ($operationName === 'my' || $operationName === 'my_reviews') {
            return $this->getCustomerReviews();
        }

        if ($operation instanceof CollectionOperationInterface) {
            $productId = (int) ($uriVariables['productId'] ?? 0);
            if ($productId) {
                ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 10, 100);
                return $this->getProductReviews($productId, $page, $pageSize);
            }
            if ($this->hasBackendAccess('reviews')) {
                ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 10, 100);
                return $this->getModerationQueue($context, $page, $pageSize);
            }
            return new TraversablePaginator(new \ArrayIterator([]), 1, 10, 0);
        }

        $reviewId = (int) ($uriVariables['id'] ?? 0);
        if ($reviewId) {
            // The moderation Put reads the current state through this provider
            // before the processor runs. Authorization for writes lives in the
            // processor (403 on a review outside the token's store allowlist),
            // so the read-visibility gates must not turn that into a 404 here.
            return $this->getReview($reviewId, checkVisibility: !$operation instanceof Put);
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
                'reviews' => array_map($this->reviewDtoToArray(...), iterator_to_array($result)),
                'page' => (int) $result->getCurrentPage(),
                'pageSize' => (int) $result->getItemsPerPage(),
                'total' => (int) $result->getTotalItems(),
            ],
            deserialize: fn(array $data): TraversablePaginator => new TraversablePaginator(
                new \ArrayIterator(array_map($this->arrayToReviewDto(...), $data['reviews'])),
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

    /**
     * Cross-store moderation listing for backend callers, newest first.
     * Optional status filter (?status=pending|approved|not_approved); tokens
     * restricted to specific stores only see reviews assigned to those stores.
     * Not cached: results depend on the token and change on every moderation.
     *
     * @return TraversablePaginator<Review>
     */
    private function getModerationQueue(array $context, int $page, int $pageSize): TraversablePaginator
    {
        $status = $context['args']['status'] ?? $context['filters']['status'] ?? null;
        $statusId = match ($status) {
            null, '' => null,
            'approved' => \Mage_Review_Model_Review::STATUS_APPROVED,
            'pending' => \Mage_Review_Model_Review::STATUS_PENDING,
            'not_approved' => \Mage_Review_Model_Review::STATUS_NOT_APPROVED,
            default => throw new BadRequestHttpException('status must be one of: approved, pending, not_approved'),
        };

        /** @var \Mage_Review_Model_Resource_Review_Collection $collection */
        $collection = \Mage::getModel('review/review')->getCollection();
        if ($statusId !== null) {
            $collection->addStatusFilter($statusId);
        }

        $user = $this->security?->getUser();
        if ($user instanceof ApiUser && $user->getAllowedStoreIds() !== null) {
            // An IN-subquery, not addStoreFilter()'s join: a review in several
            // allowed stores duplicates join rows and inflates getSize()'s COUNT(*).
            $storeSelect = $collection->getConnection()->select()
                ->from($collection->getTable('review/review_store'), 'review_id')
                ->where('store_id IN (?)', $user->getAllowedStoreIds() ?: [-1]);
            $collection->getSelect()->where(
                'main_table.review_id IN (?)',
                new \Maho\Db\Expr('(' . $storeSelect->assemble() . ')'),
            );
        }

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
    }

    private function getReview(int $reviewId, bool $checkVisibility = true): Review
    {
        /** @var \Mage_Review_Model_Review $review */
        $review = \Mage::getModel('review/review')->load($reviewId);

        if (!$review->getId()) {
            throw new NotFoundHttpException('Review not found');
        }

        // Store scoping: outside the backend a review is only visible in
        // the store views it is assigned to. Store 0 is a marker row added on
        // every save, so it grants visibility only when the review carries no
        // real store assignment (a deliberate all-stores review). Service
        // tokens bypass the current-store check but a store-restricted one
        // still only sees reviews assigned to at least one allowed store.
        $storeIds = array_values(array_filter(
            array_map(intval(...), (array) $review->getStores()),
            static fn(int $id): bool => $id !== 0,
        ));
        if ($checkVisibility && !$this->isAdmin() && $storeIds !== []) {
            if ($this->isApiUser()) {
                $allowedStoreIds = $this->requireUser()->getAllowedStoreIds();
                if ($allowedStoreIds !== null && array_intersect($storeIds, $allowedStoreIds) === []) {
                    throw new NotFoundHttpException('Review not found');
                }
            } elseif (!in_array(StoreContext::getStoreId(), $storeIds, true)) {
                throw new NotFoundHttpException('Review not found');
            }
        }

        // Non-approved reviews are visible to their author, to admins and to
        // service tokens scoped for moderation; everyone else gets 404.
        if ($checkVisibility
            && (int) $review->getStatusId() !== \Mage_Review_Model_Review::STATUS_APPROVED
            && !$this->hasBackendAccess('reviews')
        ) {
            $customerId = $this->getAuthenticatedCustomerId();
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
            'ratings' => $review->ratings,
            'status' => $review->status,
            'createdAt' => $review->createdAt,
            'customerId' => $review->customerId,
            'stores' => $review->stores,
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
        $review->ratings = $data['ratings'] ?? null;
        $review->status = $data['status'];
        $review->createdAt = $data['createdAt'];
        $review->customerId = isset($data['customerId']) ? (int) $data['customerId'] : null;
        $review->stores = $data['stores'] ?? null;

        return $review;
    }
}
