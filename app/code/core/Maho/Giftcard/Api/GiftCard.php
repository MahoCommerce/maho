<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

namespace Maho\Giftcard\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;

#[ApiResource(
    mahoId: 'giftcards',
    mahoLabel: 'Gift Cards',
    mahoSection: 'Other',
    mahoOperations: ['read' => 'Check Balance', 'create' => 'Create', 'write' => 'Update Status & Adjust Balance'],
    shortName: 'GiftCard',
    description: 'Gift Card resource',
    provider: GiftCardProvider::class,
    processor: GiftCardProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/giftcards/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('giftcards/read')",
            description: 'Get a gift card by ID',
        ),
        new Post(
            uriTemplate: '/giftcards',
            security: "is_granted('ROLE_ADMIN') or is_granted('giftcards/create')",
            description: 'Create a new gift card',
        ),
        new Put(
            uriTemplate: '/giftcards/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('giftcards/write')",
            description: 'Update gift card status and/or adjust its balance (records a history entry)',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            security: "is_granted('ROLE_ADMIN') or is_granted('giftcards/read')",
            description: 'Get a gift card by ID',
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List gift cards',
            security: "is_granted('ROLE_ADMIN') or is_granted('giftcards/read')",
        ),
        new Query(
            security: 'true',
            name: 'checkBalance',
            args: ['code' => ['type' => 'String!']],
            description: 'Check gift card balance by code',
            resolver: CustomQueryResolver::class,
        ),
        new Mutation(
            name: 'create',
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
            security: "is_granted('ROLE_ADMIN') or is_granted('giftcards/create')",
        ),
        new Mutation(
            name: 'adjustBalance',
            args: [
                'code' => ['type' => 'String!', 'description' => 'Gift card code'],
                'newBalance' => ['type' => 'Float!', 'description' => 'New balance amount'],
                'comment' => ['type' => 'String', 'description' => 'Reason for adjustment'],
            ],
            description: 'Adjust gift card balance',
            security: "is_granted('ROLE_ADMIN') or is_granted('giftcards/write')",
        ),
    ],
)]
class GiftCard extends CrudResource
{
    public const MODEL = 'giftcard/giftcard';

    /** Admin ACL gate. Mirrors backend Maho_Giftcard_Adminhtml_GiftcardController. */
    public const ADMIN_RESOURCE = \Maho_Giftcard_Adminhtml_GiftcardController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?string $code = null;

    /** Writable only through the admin/service-gated Post and Put operations. */
    public ?float $balance = null;

    public float $initialBalance = 0.0;

    /** Writable only through the admin/service-gated Post and Put operations. */
    public ?string $status = null;

    public ?string $expiresAt = null;
    public ?int $websiteId = null;

    #[ApiProperty(writable: false)]
    public ?string $currencyCode = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    #[ApiProperty(writable: false)]
    public ?int $purchaseOrderId = null;

    #[ApiProperty(writable: false)]
    public ?int $purchaseOrderItemId = null;

    #[ApiProperty(writable: false)]
    public ?string $emailScheduledAt = null;

    #[ApiProperty(writable: false)]
    public ?string $emailSentAt = null;

    public ?string $recipientName = null;
    public ?string $recipientEmail = null;
    public ?string $senderName = null;
    public ?string $senderEmail = null;
    public ?string $message = null;

    /** Not persisted on the card: recorded as the giftcard_history comment of a balance adjustment. */
    #[ApiProperty(readable: false)]
    public ?string $comment = null;

    /**
     * Balance change log, newest first. Populated only on the permission-gated
     * reads (fromModel); the public balance check builds its DTO by hand and
     * never includes it.
     * @var array<int, array<string, mixed>>
     */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $history = [];

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->currencyCode = $model->getCurrencyCode();

        foreach ($model->getHistoryCollection() as $entry) {
            $dto->history[] = [
                'action' => $entry->getData('action'),
                'amount' => (float) $entry->getData('base_amount'),
                'balanceBefore' => (float) $entry->getData('balance_before'),
                'balanceAfter' => (float) $entry->getData('balance_after'),
                'orderId' => $entry->getData('order_id') !== null ? (int) $entry->getData('order_id') : null,
                'comment' => $entry->getData('comment'),
                'createdAt' => $entry->getData('created_at'),
            ];
        }
    }
}
