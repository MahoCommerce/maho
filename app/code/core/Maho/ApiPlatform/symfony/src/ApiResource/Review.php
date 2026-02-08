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

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\State\Provider\ReviewProvider;
use Maho\ApiPlatform\State\Processor\ReviewProcessor;

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
            description: 'Get reviews for a product',
        ),
        new Get(
            uriTemplate: '/reviews/{id}',
            description: 'Get a single review',
        ),
        new Post(
            uriTemplate: '/products/{productId}/reviews',
            uriVariables: [
                'productId' => new Link(toProperty: 'productId'),
            ],
            description: 'Submit a review for a product (requires authentication)',
        ),
        new GetCollection(
            uriTemplate: '/customers/me/reviews',
            name: 'my_reviews',
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
class Review
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[ApiProperty(identifier: false)]
    public ?int $productId = null;
    public ?string $productName = null;
    public string $title = '';
    public string $detail = '';
    public string $nickname = '';
    public int $rating = 5;
    public string $status = 'pending';
    public ?string $createdAt = null;
    public ?int $customerId = null;
}
