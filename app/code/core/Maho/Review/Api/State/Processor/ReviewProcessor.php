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

namespace Maho\Review\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\Review\Api\Resource\Review;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Review State Processor
 *
 * @implements ProcessorInterface<Review, Review>
 */
final class ReviewProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @param Review $data
     * @return Review
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        // GraphQL mutation
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

        // REST POST - /products/{productId}/reviews
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

    /**
     * Submit a product review
     */
    private function submitReview(
        int $productId,
        string $title,
        string $detail,
        string $nickname,
        int $rating,
    ): Review {
        $customerId = $this->requireAuthentication();

        // Validate product exists
        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product')->load($productId);
        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        // Validate inputs
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

        // Set to pending status (requires admin approval)
        $review->setStatusId(\Mage_Review_Model_Review::STATUS_PENDING);

        // Set entity type to product
        $review->setEntityId($review->getEntityIdByCode(\Mage_Review_Model_Review::ENTITY_PRODUCT_CODE));

        // Validate the review
        $validate = $review->validate();
        if ($validate !== true && is_array($validate)) {
            throw new BadRequestHttpException(implode(', ', $validate));
        }

        $review->save();

        // Add rating vote
        $this->addRatingVote($review, $rating, $productId, $customerId, $storeId);

        // Aggregate ratings
        $review->aggregate();

        // Build response
        $resource = new Review();
        $resource->id = (int) $review->getId();
        $resource->productId = $productId;
        $resource->productName = $product->getName();
        $resource->title = $title;
        $resource->detail = $detail;
        $resource->nickname = $nickname;
        $resource->rating = $rating;
        $resource->status = 'pending';
        $resource->createdAt = $review->getCreatedAt();
        $resource->customerId = $customerId;

        return $resource;
    }

    /**
     * Add rating vote to review
     */
    private function addRatingVote(
        \Mage_Review_Model_Review $review,
        int $rating,
        int $productId,
        int $customerId,
        int $storeId,
    ): void {
        // Get the default rating entity (usually "Quality" or "Rating")
        /** @var \Mage_Rating_Model_Resource_Rating_Collection $ratingCollection */
        $ratingCollection = \Mage::getModel('rating/rating')->getCollection()
            ->addEntityFilter('product');

        // Use the first available rating, or create one if none exist
        $ratingModel = $ratingCollection->getFirstItem();

        if (!$ratingModel->getId()) {
            // Fallback: try to get rating by ID 1 (default)
            $ratingModel = \Mage::getModel('rating/rating')->load(1);
        }

        if ($ratingModel->getId()) {
            // Convert 1-5 star to option ID
            // Rating options are typically: 1=20%, 2=40%, 3=60%, 4=80%, 5=100%
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

    /**
     * Get rating option ID for a star value
     */
    private function getRatingOptionId(int $ratingId, int $starValue): ?int
    {
        /** @var \Mage_Rating_Model_Resource_Rating_Option_Collection $optionCollection */
        /** @phpstan-ignore-next-line */
        $optionCollection = \Mage::getModel('rating/rating_option')->getCollection()
            ->addRatingFilter($ratingId)
            ->setPositionOrder();

        $options = $optionCollection->getItems();

        // Options are ordered by position (1, 2, 3, 4, 5)
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
