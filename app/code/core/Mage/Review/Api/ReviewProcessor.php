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

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Review Processor — handles review submission with custom logic.
 *
 * Reviews have a non-standard write flow (submit only, no generic CRUD update/delete),
 * so this extends the base Processor rather than CrudProcessor. It uses
 * CrudResource::fromModel() for building responses.
 */
final class ReviewProcessor extends \Maho\ApiPlatform\Processor
{
    /**
     * @param Review $data
     * @return Review
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        if ($operationName === 'submitReview') {
            $args = $context['args']['input'] ?? [];
            return $this->submitReview(
                (int) $args['productId'],
                $args['title'],
                $args['detail'],
                $args['nickname'],
                (int) $args['rating'],
            );
        }

        $productId = (int) ($uriVariables['productId'] ?? 0);
        if ($productId && $data instanceof Review) {
            return $this->submitReview(
                $productId,
                $data->title,
                $data->detail,
                $data->nickname,
                $data->rating,
            );
        }

        throw new BadRequestHttpException('Invalid review operation');
    }

    private function submitReview(
        int $productId,
        string $title,
        string $detail,
        string $nickname,
        int $rating,
    ): Review {
        $customerId = $this->requireAuthentication();

        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product')->load($productId);
        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        $title = trim($title);
        $detail = trim($detail);
        $nickname = trim($nickname);

        if (empty($title)) {
            throw new BadRequestHttpException('Review title is required');
        }
        if (strlen($title) > 255) {
            throw new BadRequestHttpException('Review title cannot exceed 255 characters');
        }
        if (empty($detail)) {
            throw new BadRequestHttpException('Review detail is required');
        }
        if (strlen($detail) > 65535) {
            throw new BadRequestHttpException('Review detail is too long');
        }
        if (empty($nickname)) {
            throw new BadRequestHttpException('Nickname is required');
        }
        if (strlen($nickname) > 255) {
            throw new BadRequestHttpException('Nickname cannot exceed 255 characters');
        }
        if ($rating < 1 || $rating > 5) {
            throw new BadRequestHttpException('Rating must be between 1 and 5');
        }

        $storeId = StoreContext::getStoreId();

        /** @var \Mage_Review_Model_Review $review */
        $review = \Mage::getModel('review/review');
        $review->setEntityPkValue($productId);
        $review->setTitle($title);
        $review->setDetail($detail);
        $review->setNickname($nickname);
        $review->setCustomerId($customerId);
        $review->setStoreId($storeId);
        $review->setStores([$storeId]);
        $review->setStatusId(\Mage_Review_Model_Review::STATUS_PENDING);
        $review->setEntityId($review->getEntityIdByCode(\Mage_Review_Model_Review::ENTITY_PRODUCT_CODE));

        $validate = $review->validate();
        if ($validate !== true && is_array($validate)) {
            throw new BadRequestHttpException(implode(', ', $validate));
        }

        $review->save();

        $this->addRatingVote($review, $rating, $productId, $customerId, $storeId);
        $review->aggregate();

        $dto = Review::fromModel($review);
        $dto->productName = $product->getName();
        $dto->rating = $rating;
        $dto->status = 'pending';

        return $dto;
    }

    private function addRatingVote(
        \Mage_Review_Model_Review $review,
        int $rating,
        int $productId,
        int $customerId,
        int $storeId,
    ): void {
        /** @var \Mage_Rating_Model_Resource_Rating_Collection $ratingCollection */
        $ratingCollection = \Mage::getModel('rating/rating')->getCollection()
            ->addEntityFilter('product');

        $ratingModel = $ratingCollection->getFirstItem();

        if (!$ratingModel->getId()) {
            $ratingModel = \Mage::getModel('rating/rating')->load(1);
        }

        if ($ratingModel->getId()) {
            $optionId = $this->getRatingOptionId($ratingModel->getId(), $rating);

            if ($optionId) {
                /** @var \Mage_Rating_Model_Rating $ratingVote */
                $ratingVote = \Mage::getModel('rating/rating');
                $ratingVote->setRatingId($ratingModel->getId())
                    ->setReviewId($review->getId())
                    ->setCustomerId($customerId)
                    ->addOptionVote($optionId, (string) $productId);
            }
        }
    }

    private function getRatingOptionId(int $ratingId, int $starValue): ?int
    {
        /** @var \Mage_Rating_Model_Resource_Rating_Option_Collection $optionCollection */
        $optionCollection = \Mage::getModel('rating/rating_option')->getCollection();
        $optionCollection->addRatingFilter($ratingId);
        $optionCollection->setPositionOrder();

        $options = $optionCollection->getItems();

        $index = 0;
        foreach ($options as $option) {
            $index++;
            if ($index === $starValue) {
                return (int) $option->getId();
            }
        }

        return null;
    }
}
