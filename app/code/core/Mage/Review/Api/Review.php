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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    shortName: 'Review',
    description: 'Product review',
    provider: ReviewProvider::class,
    processor: ReviewProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/reviews',
            uriVariables: [
                'productId' => new Link(toProperty: 'productId'),
            ],
            security: 'true',
            description: 'Get reviews for a product',
        ),
        new Get(
            uriTemplate: '/reviews/{id}',
            security: 'true',
            description: 'Get a single review',
        ),
        new Post(
            uriTemplate: '/products/{productId}/reviews',
            uriVariables: [
                'productId' => new Link(toProperty: 'productId'),
            ],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Submit a review for a product (requires authentication)',
        ),
        new GetCollection(
            uriTemplate: '/customers/me/reviews',
            name: 'my_reviews',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            description: 'Get current customer submitted reviews',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a review by ID'),
        new QueryCollection(name: 'collection_query', description: 'Get reviews'),
        new QueryCollection(
            name: 'productReviews',
            description: 'Get reviews for a product',
            args: [
                'productId' => ['type' => 'Int!', 'description' => 'Product ID'],
                'page' => ['type' => 'Int', 'description' => 'Page number'],
                'pageSize' => ['type' => 'Int', 'description' => 'Reviews per page'],
            ],
        ),
        new Query(
            name: 'review',
            description: 'Get a single review by ID',
        ),
        new QueryCollection(
            name: 'myReviews',
            description: 'Get current customer submitted reviews',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'submitReview',
            description: 'Submit a product review',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            args: [
                'productId' => ['type' => 'Int!', 'description' => 'Product ID'],
                'title' => ['type' => 'String!', 'description' => 'Review title/summary'],
                'detail' => ['type' => 'String!', 'description' => 'Review content'],
                'nickname' => ['type' => 'String!', 'description' => 'Reviewer nickname'],
                'rating' => ['type' => 'Int!', 'description' => 'Rating 1-5'],
            ],
        ),
    ],
)]
class Review extends CrudResource
{
    public const MODEL = 'review/review';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(identifier: false, extraProperties: ['modelField' => 'entity_pk_value'])]
    public ?int $productId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $productName = null;

    public string $title = '';
    public string $detail = '';
    public string $nickname = '';

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public int $rating = 5;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = 'pending';

    #[ApiProperty(writable: false, extraProperties: ['modelField' => 'created_at'])]
    public ?string $createdAt = null;

    #[ApiProperty(extraProperties: ['modelField' => 'customer_id'])]
    public ?int $customerId = null;

    public static function afterLoad(self $dto, object $model): void
    {
        $statusId = (int) $model->getStatusId();
        $dto->status = match ($statusId) {
            \Mage_Review_Model_Review::STATUS_APPROVED => 'approved',
            \Mage_Review_Model_Review::STATUS_PENDING => 'pending',
            \Mage_Review_Model_Review::STATUS_NOT_APPROVED => 'not_approved',
            default => 'pending',
        };

        $rating = 0;
        $ratingVotes = $model->getRatingVotes();
        if ($ratingVotes && count($ratingVotes) > 0) {
            $totalPercent = 0;
            foreach ($ratingVotes as $vote) {
                $totalPercent += (float) $vote->getPercent();
            }
            $rating = (int) round(($totalPercent / count($ratingVotes)) / 20);
        }
        $dto->rating = max(1, min(5, $rating));
    }
}
