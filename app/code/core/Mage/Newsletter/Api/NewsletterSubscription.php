<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Newsletter
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Newsletter\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;

#[ApiResource(
    shortName: 'NewsletterSubscription',
    description: 'Newsletter subscription management',
    provider: NewsletterProvider::class,
    processor: NewsletterProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/newsletter/status',
            name: 'status',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
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
        new Query(name: 'item_query', description: 'Get newsletter subscription', security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')"),
        new QueryCollection(name: 'collection_query', description: 'Get newsletter subscriptions', security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')"),
        new Query(
            name: 'newsletterStatus',
            args: [],
            description: 'Get subscription status for authenticated customer',
            security: "is_granted('ROLE_USER') or is_granted('ROLE_API_USER')",
            resolver: CustomQueryResolver::class,
        ),
        new Mutation(
            name: 'subscribeNewsletter',
            args: ['email' => ['type' => 'String']],
            description: 'Subscribe to newsletter',
        ),
        new Mutation(
            name: 'unsubscribeNewsletter',
            args: ['email' => ['type' => 'String']],
            description: 'Unsubscribe from newsletter',
        ),
    ],
    extraProperties: [
        'model' => 'newsletter/subscriber',
    ],
)]
class NewsletterSubscription extends CrudResource
{
    #[ApiProperty(extraProperties: ['modelField' => 'subscriber_email'])]
    public ?string $email = null;

    #[ApiProperty(extraProperties: ['modelField' => 'customer_id'])]
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
