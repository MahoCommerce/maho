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

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Provider\GiftCardProvider;
use Maho\ApiPlatform\State\Processor\GiftCardProcessor;

#[ApiResource(
    shortName: 'GiftCard',
    description: 'Gift Card resource',
    provider: GiftCardProvider::class,
    processor: GiftCardProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/giftcards/{id}',
            description: 'Get a gift card by ID',
        ),
        new Post(
            uriTemplate: '/giftcards',
            description: 'Create a new gift card',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a gift card by ID',
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List gift cards',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Query(
            name: 'checkGiftcardBalance',
            args: ['code' => ['type' => 'String!']],
            description: 'Check gift card balance by code',
            resolver: CustomQueryResolver::class,
        ),
        new Mutation(
            name: 'createGiftcard',
            args: [
                'initialBalance' => ['type' => 'Float!', 'description' => 'Initial balance amount'],
                'code' => ['type' => 'String', 'description' => 'Custom code (auto-generated if omitted)'],
                'recipientName' => ['type' => 'String', 'description' => 'Recipient name'],
                'recipientEmail' => ['type' => 'String', 'description' => 'Recipient email'],
                'senderName' => ['type' => 'String', 'description' => 'Sender name'],
                'senderEmail' => ['type' => 'String', 'description' => 'Sender email'],
                'message' => ['type' => 'String', 'description' => 'Gift card message'],
                'websiteId' => ['type' => 'Int', 'description' => 'Website ID (defaults to current)'],
                'expiresAt' => ['type' => 'String', 'description' => 'Expiration date (YYYY-MM-DD)'],
            ],
            description: 'Create a new gift card',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'adjustGiftcardBalance',
            args: [
                'code' => ['type' => 'String!', 'description' => 'Gift card code'],
                'newBalance' => ['type' => 'Float!', 'description' => 'New balance amount'],
                'comment' => ['type' => 'String', 'description' => 'Reason for adjustment'],
            ],
            description: 'Adjust gift card balance',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
    ],
)]
class GiftCard
{
    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?string $code = null;

    #[ApiProperty(writable: false)]
    public float $balance = 0.0;

    public float $initialBalance = 0.0;

    #[ApiProperty(writable: false)]
    public ?string $status = null;

    public ?string $expirationDate = null;

    #[ApiProperty(writable: false)]
    public ?string $currencyCode = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    public ?string $recipientName = null;
    public ?string $recipientEmail = null;
    public ?string $senderName = null;
    public ?string $senderEmail = null;
    public ?string $message = null;
}
