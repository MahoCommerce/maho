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

namespace Maho\Sales\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\Sales\Api\State\Provider\ShipmentProvider;
use Maho\Sales\Api\State\Processor\ShipmentProcessor;

#[ApiResource(
    shortName: 'Shipment',
    description: 'Order shipment resource',
    provider: ShipmentProvider::class,
    processor: ShipmentProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/shipments/{id}',
            description: 'Get a shipment by ID',
        ),
        new GetCollection(
            uriTemplate: '/orders/{orderId}/shipments',
            uriVariables: [
                'orderId' => new Link(toProperty: 'orderId'),
            ],
            description: 'Get shipments for an order',
        ),
        new Post(
            uriTemplate: '/orders/{orderId}/shipments',
            uriVariables: [
                'orderId' => new Link(toProperty: 'orderId'),
            ],
            description: 'Create a shipment for an order',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a shipment by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get shipments',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new QueryCollection(
            name: 'orderShipments',
            args: ['orderId' => ['type' => 'Int!']],
            description: 'Get shipments for an order',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
        new Mutation(
            name: 'createShipment',
            args: [
                'orderId' => ['type' => 'Int!', 'description' => 'Order ID to ship'],
                'items' => ['type' => 'Iterable', 'description' => 'Items to ship: [{orderItemId: ID!, qty: Float!}]. Ships all if omitted.'],
                'tracks' => ['type' => 'Iterable', 'description' => 'Tracking info: [{carrierCode: String!, title: String!, trackNumber: String!}]'],
                'comment' => ['type' => 'String', 'description' => 'Shipment comment'],
                'notifyCustomer' => ['type' => 'Boolean', 'description' => 'Send shipment notification email'],
            ],
            description: 'Create a shipment for an order (full or partial)',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_API_USER')",
        ),
    ],
)]
class Shipment
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[ApiProperty(identifier: false)]
    public ?int $orderId = null;

    #[ApiProperty(writable: false)]
    public ?string $incrementId = null;

    #[ApiProperty(writable: false)]
    public ?string $orderIncrementId = null;

    #[ApiProperty(writable: false)]
    public int $totalQty = 0;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    /** @var ShipmentTrack[] */
    #[ApiProperty(writable: false)]
    public array $tracks = [];

    /** @var ShipmentItem[] */
    #[ApiProperty(writable: false)]
    public array $items = [];
}
