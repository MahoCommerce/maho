<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

namespace Mage\Review\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Give a review submission or a moderation to the review service, and return the review.
 */
final class ReviewProcessor extends \Maho\ApiPlatform\Processor
{
    private const STATUS_MAP = [
        'approved' => \Mage_Review_Model_Review::STATUS_APPROVED,
        'pending' => \Mage_Review_Model_Review::STATUS_PENDING,
        'not_approved' => \Mage_Review_Model_Review::STATUS_NOT_APPROVED,
    ];

    /**
     * @param Review $data
     * @return Review
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        StoreContext::ensureStore();

        if ($operation->getName() === 'submit') {
            $args = $context['args']['input'] ?? [];
            return $this->submitReview(
                (int) $args['productId'],
                $args['title'],
                $args['detail'],
                $args['nickname'],
                isset($args['rating']) ? (int) $args['rating'] : null,
                isset($args['captchaToken']) ? (string) $args['captchaToken'] : null,
            );
        }

        if ($operation instanceof Put && isset($uriVariables['id']) && $data instanceof Review) {
            return $this->moderateReview((int) $uriVariables['id'], $data);
        }

        $productId = (int) ($uriVariables['productId'] ?? 0);
        if ($productId && $data instanceof Review) {
            return $this->submitReview($productId, $data->title, $data->detail, $data->nickname, $data->rating, $data->captchaToken);
        }

        throw new BadRequestHttpException('Invalid review operation');
    }

    /**
     * Moderation is admin/service-token only, enforced by the operation's own
     * `security:` expression: no ROLE_CUSTOMER, and the permission voter never
     * grants 'reviews/write' to a non-API-key token.
     */
    private function moderateReview(int $reviewId, Review $data): Review
    {
        if ($data->status !== null && !isset(self::STATUS_MAP[$data->status])) {
            throw new \Mage_Core_Exception('status must be one of: ' . implode(', ', array_keys(self::STATUS_MAP)));
        }

        $service = \Mage::getService('review/review');
        $review = $service->getById($reviewId);

        // A store-restricted token may only moderate reviews assigned to a
        // store on its allowlist. Store 0 is a marker row every save adds, so
        // only a review with no real store assignment counts as all-stores.
        $user = $this->requireUser();
        $reviewStoreIds = array_values(array_filter(
            array_map(intval(...), (array) $review->getStores()),
            static fn(int $id): bool => $id !== 0,
        ));
        $this->validateEntityStoreAccess($reviewStoreIds === [] ? [0] : $reviewStoreIds, $user, 'review');

        $storeIds = null;
        if ($data->stores) {
            try {
                $storeIds = $this->resolveStoreIds(array_map(strval(...), $data->stores), $user);
            } catch (\Mage_Core_Model_Store_Exception) {
                throw new \Mage_Core_Exception('stores contains an unknown store');
            }
        }
        $service->moderate($review, $data->status === null ? null : self::STATUS_MAP[$data->status], $storeIds);

        $dto = Review::fromModel($service->getById($reviewId));
        $dto->productName = \Mage::getModel('catalog/product')->load((int) $review->getEntityPkValue())->getName();
        return $dto;
    }

    private function submitReview(
        int $productId,
        string $title,
        string $detail,
        string $nickname,
        ?int $rating,
        ?string $captchaToken,
    ): Review {
        $customerId = $this->requireCustomerId();
        $storeId = StoreContext::getStoreId();
        $service = \Mage::getService('review/review');

        $review = \Mage::getModel('review/review')
            ->setEntityPkValue($productId)
            ->setTitle($title)
            ->setDetail($detail)
            ->setNickname($nickname);
        $service->submit(
            $review,
            $rating === null ? [] : $service->getStarVotes($rating, $storeId),
            $storeId,
            $customerId,
            ['captchaToken' => $captchaToken],
        );

        $dto = Review::fromModel($review);
        $dto->productName = $service->getProduct($productId, $storeId)->getName();
        $dto->rating = $rating ?? $dto->rating;
        $dto->status = 'pending';

        return $dto;
    }
}
