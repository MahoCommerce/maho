<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

namespace Mage\Review\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View', 'write' => 'Submit'],
    mahoCustomerScoped: true,
    shortName: 'Review',
    description: 'View and submit product reviews',
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

    /** Admin ACL gate. No backend ReviewController declares ADMIN_RESOURCE; use the standard reviews path. */
    public const ADMIN_RESOURCE = 'catalog/reviews_ratings/reviews';

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

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

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
