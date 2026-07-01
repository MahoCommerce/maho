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
        ),
        new Post(
            uriTemplate: '/shipments/{id}/tracks',
            name: 'add_shipment_track',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
            description: 'Add a tracking number to an existing shipment',
        ),
        new Delete(
            uriTemplate: '/shipments/{id}/tracks/{trackId}',
            name: 'remove_shipment_track',
            requirements: ['id' => '\d+', 'trackId' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/create')",
            description: 'Remove a tracking number from a shipment',
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
            name: 'orderShipments',
            args: ['orderId' => ['type' => 'Int!']],
            description: 'Get shipments for an order',
            security: "is_granted('ROLE_ADMIN') or is_granted('shipments/read')",
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
            security: "is_granted('shipments/create')",
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
    public ?string $createdAt = null;

    /** @var array<int, array<string, mixed>> Tracking entries; plain-DTO elements so kept as Iterable scalar to avoid the IterableCursorConnection null-edges bug. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $tracks = [];

    /** @var array<int, array<string, mixed>> Shipment line items; same reason as $tracks above. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $items = [];

    public static function afterLoad(self $dto, object $model): void
    {
        $order = $model->getOrder();
        $dto->orderIncrementId = $order ? $order->getIncrementId() : null;

        $dto->tracks = [];
        foreach ($model->getAllTracks() as $track) {
            $trackDto = new ShipmentTrack();
            $trackDto->id = (int) $track->getId();
            $trackDto->carrier = $track->getCarrierCode();
            $trackDto->title = $track->getTitle();
            $trackDto->trackNumber = $track->getTrackNumber();
            $dto->tracks[] = $trackDto;
        }

        $dto->items = [];
        foreach ($model->getAllItems() as $item) {
            $itemDto = new ShipmentItem();
            $itemDto->sku = $item->getSku();
            $itemDto->name = $item->getName();
            $itemDto->qty = (float) $item->getQty();
            $dto->items[] = $itemDto;
        }
    }
}
