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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Provider\NewsletterProvider;
use Maho\ApiPlatform\State\Processor\NewsletterProcessor;

#[ApiResource(
    shortName: 'NewsletterSubscription',
    description: 'Newsletter subscription management',
    provider: NewsletterProvider::class,
    processor: NewsletterProcessor::class,
    operations: [
        // Get current user's subscription status (requires authentication)
        new Get(
            uriTemplate: '/newsletter/status',
            name: 'status',
            description: 'Get subscription status for authenticated customer',
        ),
        // Subscribe to newsletter (guest or authenticated)
        new Post(
            uriTemplate: '/newsletter/subscribe',
            name: 'subscribe',
            description: 'Subscribe to newsletter',
        ),
        // Unsubscribe from newsletter
        new Post(
            uriTemplate: '/newsletter/unsubscribe',
            name: 'unsubscribe',
            description: 'Unsubscribe from newsletter',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get newsletter subscription', security: "is_granted('ROLE_USER')"),
        new QueryCollection(name: 'collection_query', description: 'Get newsletter subscriptions', security: "is_granted('ROLE_ADMIN')"),
        new Query(
            name: 'newsletterStatus',
            args: [],
            description: 'Get subscription status for authenticated customer',
            security: "is_granted('ROLE_USER')",
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
)]
class NewsletterSubscription
{
    /**
     * Subscriber email address
     */
    public ?string $email = null;

    /**
     * Associated customer ID (null for guest subscriptions)
     */
    public ?int $customerId = null;

    /**
     * Subscription status: subscribed, unsubscribed, unconfirmed, not_active
     */
    public string $status = '';

    /**
     * Whether the user is currently subscribed
     */
    public bool $isSubscribed = false;

    /**
     * Human-readable message about the operation result
     */
    public ?string $message = null;

    /**
     * Whether confirmation email is required
     */
    public bool $confirmationRequired = false;
}
