<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    shortName: 'Shipment',
    description: 'Order shipment resource',
    provider: ShipmentProvider::class,
    processor: ShipmentProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/shipments/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/read')",
            description: 'Get a shipment by ID',
        ),
        new GetCollection(
            uriTemplate: '/orders/{orderId}/shipments',
            uriVariables: [
                'orderId' => new Link(toProperty: 'orderId'),
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/read')",
            description: 'Get shipments for an order',
        ),
        new Post(
            uriTemplate: '/orders/{orderId}/shipments',
            uriVariables: [
                'orderId' => new Link(toProperty: 'orderId'),
            ],
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
            description: 'Create a shipment for an order',
            // The processor reads the raw body, so none of it maps to a writable
            // DTO property: without this the spec advertises no request body at all.
            openapi: new OpenApiOperation(
                summary: 'Create a shipment for an order (full or partial)',
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'items' => [
                                        'type' => 'array',
                                        'description' => 'Items to ship. Ships every remaining item if omitted.',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'orderItemId' => ['type' => 'integer'],
                                                'qty' => ['type' => 'number'],
                                            ],
                                            'required' => ['orderItemId', 'qty'],
                                        ],
                                    ],
                                    'tracks' => [
                                        'type' => 'array',
                                        'description' => 'Tracking entries to attach to the new shipment.',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'carrierCode' => ['type' => 'string', 'default' => 'custom'],
                                                'title' => ['type' => 'string'],
                                                'trackNumber' => ['type' => 'string'],
                                            ],
                                            'required' => ['trackNumber'],
                                        ],
                                    ],
                                    'comment' => ['type' => 'string'],
                                    'notifyCustomer' => ['type' => 'boolean', 'default' => false],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Post(
            uriTemplate: '/shipments/{id}/tracks',
            name: 'add_shipment_track',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
            description: 'Add a tracking number to an existing shipment',
            openapi: new OpenApiOperation(
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'carrierCode' => ['type' => 'string', 'default' => 'custom'],
                                    'title' => ['type' => 'string'],
                                    'trackNumber' => ['type' => 'string'],
                                ],
                                'required' => ['trackNumber'],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Delete(
            uriTemplate: '/shipments/{id}/tracks/{trackId}',
            name: 'remove_shipment_track',
            requirements: ['id' => '\d+', 'trackId' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
            description: 'Remove a tracking number from a shipment',
        ),
        new Post(
            uriTemplate: '/shipments/{id}/comments',
            name: 'shipment_add_comment',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
            description: 'Add a comment to an existing shipment',
            openapi: new OpenApiOperation(
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'comment' => ['type' => 'string'],
                                    'notifyCustomer' => ['type' => 'boolean', 'default' => false],
                                    'visibleOnFront' => ['type' => 'boolean', 'default' => false],
                                ],
                                'required' => ['comment'],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a shipment by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get shipments',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/read')",
        ),
        new QueryCollection(
            // Named 'order' so ApiPlatform's appended shortName yields the field
            // `orderShipments`, not a stuttering `orderShipmentsShipments`.
            name: 'order',
            extraArgs: ['orderId' => ['type' => 'Int!']],
            description: 'Get shipments for an order',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/read')",
        ),
        new Mutation(
            name: 'create',
            args: [
                'orderId' => ['type' => 'Int!', 'description' => 'Order ID to ship'],
                'items' => ['type' => 'Iterable', 'description' => 'Items to ship: [{orderItemId: ID!, qty: Float!}]. Ships all if omitted.'],
                'tracks' => ['type' => 'Iterable', 'description' => 'Tracking info: [{carrierCode: String!, title: String!, trackNumber: String!}]'],
                'comment' => ['type' => 'String', 'description' => 'Shipment comment'],
                'notifyCustomer' => ['type' => 'Boolean', 'description' => 'Send shipment notification email'],
            ],
            description: 'Create a shipment for an order (full or partial)',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
        ),
        // Names omit "Shipment": ApiPlatform appends the resource shortName, so
        // these read as addTrackShipment / removeTrackShipment, not the stuttering
        // addShipmentTrackShipment.
        new Mutation(
            name: 'addTrack',
            args: [
                'shipmentId' => ['type' => 'Int!'],
                'carrierCode' => ['type' => 'String'],
                'title' => ['type' => 'String'],
                'trackNumber' => ['type' => 'String!'],
            ],
            description: 'Add a tracking number to an existing shipment',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
        ),
        new Mutation(
            name: 'removeTrack',
            args: [
                'shipmentId' => ['type' => 'Int!'],
                'trackId' => ['type' => 'Int!'],
            ],
            description: 'Remove a tracking number from a shipment',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
        ),
    ],
)]
class Shipment extends CrudResource
{
    public const MODEL = 'sales/order_shipment';

    /** Admin ACL gate. References backend abstract controller's constant. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Controller_Sales_Shipment::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false)]
    public ?int $orderId = null;

    #[ApiProperty(writable: false)]
    public ?string $incrementId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $orderIncrementId = null;

    #[ApiProperty(writable: false)]
    public int $totalQty = 0;

    #[ApiProperty(writable: false)]
    public float $totalWeight = 0;

    #[ApiProperty(writable: false)]
    public ?int $storeId = null;

    #[ApiProperty(writable: false)]
    public bool $emailSent = false;

    #[ApiProperty(writable: false)]
    public ?int $shipmentStatus = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    /** @var array<int, mixed> Package definitions as stored at shipment creation (decoded). */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $packages = [];

    /** @var array<int, array<string, mixed>> Shipment comments; plain arrays, same Iterable rationale as $tracks. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $comments = [];

    /** @var array<int, array<string, mixed>> Tracking entries; plain-DTO elements so kept as Iterable scalar to avoid the IterableCursorConnection null-edges bug. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $tracks = [];

    /** @var array<int, array<string, mixed>> Shipment line items; same reason as $tracks above. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $items = [];

    public static function afterLoad(self $dto, object $model): void
    {
        // List paths batch-preload these relations (see
        // ShipmentProvider::getAllShipments()); fall back to the lazy per-model
        // loads only for single-shipment and per-order views.
        if ($model->hasData('_preloaded_order_increment_id')) {
            $dto->orderIncrementId = $model->getData('_preloaded_order_increment_id');
        } else {
            $order = $model->getOrder();
            $dto->orderIncrementId = $order ? $order->getIncrementId() : null;
        }

        $dto->tracks = [];
        foreach ($model->getData('_preloaded_tracks') ?? $model->getAllTracks() as $track) {
            $trackDto = new ShipmentTrack();
            $trackDto->id = (int) $track->getId();
            $trackDto->carrier = $track->getCarrierCode();
            $trackDto->title = $track->getTitle();
            $trackDto->trackNumber = $track->getTrackNumber();
            $trackDto->description = $track->getDescription();
            $trackDto->weight = $track->getWeight() !== null ? (float) $track->getWeight() : null;
            $trackDto->qty = $track->getQty() !== null ? (float) $track->getQty() : null;
            $trackDto->createdAt = $track->getCreatedAt();
            $trackDto->updatedAt = $track->getUpdatedAt();
            $dto->tracks[] = $trackDto;
        }

        $dto->items = [];
        foreach ($model->getData('_preloaded_items') ?? $model->getAllItems() as $item) {
            $itemDto = new ShipmentItem();
            $itemDto->id = (int) $item->getId();
            $itemDto->orderItemId = (int) $item->getOrderItemId();
            $itemDto->productId = $item->getProductId() !== null ? (int) $item->getProductId() : null;
            $itemDto->sku = $item->getSku();
            $itemDto->name = $item->getName();
            $itemDto->qty = (float) $item->getQty();
            $itemDto->price = (float) $item->getPrice();
            $itemDto->rowTotal = (float) $item->getRowTotal();
            $itemDto->weight = (float) $item->getWeight();
            $itemDto->description = $item->getDescription();
            $dto->items[] = $itemDto;
        }

        // The resource model unserializes `packages` on load, but a raw JSON
        // string can still surface on unsaved/legacy rows; decode defensively.
        $packages = $model->getPackages();
        if (is_string($packages) && $packages !== '') {
            try {
                $packages = \Mage::helper('core')->jsonDecode($packages);
            } catch (\JsonException) {
                $packages = [];
            }
        }
        $dto->packages = is_array($packages) ? $packages : [];

        $dto->comments = [];
        foreach ($model->getData('_preloaded_comments') ?? $model->getCommentsCollection() as $comment) {
            $dto->comments[] = [
                'id' => (int) $comment->getId(),
                'comment' => $comment->getComment(),
                'createdAt' => $comment->getCreatedAt(),
                'isCustomerNotified' => (bool) $comment->getIsCustomerNotified(),
                'isVisibleOnFront' => (bool) $comment->getIsVisibleOnFront(),
            ];
        }
    }
}
