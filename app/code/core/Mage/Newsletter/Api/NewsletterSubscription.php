<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

namespace Mage\Newsletter\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;

#[ApiResource(
    mahoId: 'newsletter',
    mahoSection: 'Other',
    mahoOperations: ['read' => 'View Status', 'write' => 'Subscribe/Unsubscribe'],
    mahoCustomerScoped: true,
    shortName: 'NewsletterSubscription',
    description: 'View subscription status, subscribe/unsubscribe',
    provider: NewsletterProvider::class,
    processor: NewsletterProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/newsletter/status',
            name: 'status',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('newsletter/read')",
            description: 'Get subscription status for authenticated customer',
        ),
        new Post(
            uriTemplate: '/newsletter/subscribe',
            name: 'subscribe',
            security: 'true',
            description: 'Subscribe to newsletter',
        ),
        new Post(
            uriTemplate: '/newsletter/unsubscribe',
            name: 'unsubscribe',
            security: 'true',
            description: 'Unsubscribe from newsletter',
        ),
    ],
    graphQlOperations: [
        // Admin/API only: the generic by-ID query has no per-record ownership
        // check (it falls through to CrudProvider::provideItem), so exposing it
        // to ROLE_CUSTOMER would let any logged-in customer read every subscriber's
        // email/customerId by iterating IDs. Customers use newsletterStatus.
        new Query(name: 'item_query', description: 'Get newsletter subscription', security: "is_granted('ROLE_ADMIN') or is_granted('newsletter/read')"),
        new QueryCollection(name: 'collection_query', description: 'Get newsletter subscriptions', security: "is_granted('ROLE_ADMIN') or is_granted('newsletter/read')"),
        new Query(
            name: 'newsletterStatus',
            args: [],
            description: 'Get subscription status for authenticated customer',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('newsletter/read')",
            resolver: CustomQueryResolver::class,
        ),
        new Mutation(
            security: 'true',
            name: 'subscribeNewsletter',
            args: ['email' => ['type' => 'String']],
            description: 'Subscribe to newsletter',
        ),
        new Mutation(
            security: 'true',
            name: 'unsubscribeNewsletter',
            args: ['email' => ['type' => 'String']],
            description: 'Unsubscribe from newsletter',
        ),
    ],
)]
class NewsletterSubscription extends CrudResource
{
    public const MODEL = 'newsletter/subscriber';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Newsletter_SubscriberController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Newsletter_SubscriberController::ADMIN_RESOURCE;

    #[ApiProperty(extraProperties: ['modelField' => 'subscriber_email'])]
    public ?string $email = null;

    public ?int $customerId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = '';

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public bool $isSubscribed = false;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $message = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public bool $confirmationRequired = false;

    public static function mapStatus(int $status): string
    {
        return match ($status) {
            \Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED => 'subscribed',
            \Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE => 'not_active',
            \Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED => 'unsubscribed',
            \Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED => 'unconfirmed',
            default => 'unknown',
        };
    }

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->isSubscribed = (bool) $model->isSubscribed();
        $dto->status = self::mapStatus((int) $model->getSubscriberStatus());
    }
}
