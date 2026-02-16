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

namespace Maho\Sales\Api\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use Maho\Sales\Api\Resource\Shipment;
use Maho\Sales\Api\Resource\ShipmentItem;
use Maho\Sales\Api\Resource\ShipmentTrack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shipment State Provider - Fetches shipment data for API Platform
 *
 * @implements ProviderInterface<Shipment>
 */
final class ShipmentProvider implements ProviderInterface
{
    /**
     * @return Shipment|ArrayPaginator<Shipment>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Shipment|ArrayPaginator|null
    {
        $operationName = $operation->getName();

        // GraphQL: orderShipments query
        if ($operationName === 'orderShipments') {
            $orderId = (int) ($context['args']['orderId'] ?? 0);
            if (!$orderId) {
                throw new \RuntimeException('Order ID is required');
            }
            return $this->getShipmentsForOrder($orderId);
        }

        // REST collection: GET /orders/{orderId}/shipments
        if ($operation instanceof CollectionOperationInterface) {
            $orderId = (int) ($uriVariables['orderId'] ?? 0);
            if (!$orderId) {
                throw new \RuntimeException('Order ID is required');
            }
            return $this->getShipmentsForOrder($orderId);
        }

        // Single shipment: GET /shipments/{id} or item_query
        $id = (int) ($uriVariables['id'] ?? 0);
        if ($id) {
            return $this->getShipmentById($id);
        }

        return null;
    }

    private function getShipmentById(int $id): Shipment
    {
        $shipment = \Mage::getModel('sales/order_shipment')->load($id);
        if (!$shipment->getId()) {
            throw new NotFoundHttpException('Shipment not found');
        }
        return $this->mapToDto($shipment);
    }

    /**
     * @return ArrayPaginator<Shipment>
     */
    private function getShipmentsForOrder(int $orderId): ArrayPaginator
    {
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        $shipments = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            $shipments[] = $this->mapToDto($shipment);
        }

        return new ArrayPaginator($shipments, 0, count($shipments));
    }

    private function mapToDto(\Mage_Sales_Model_Order_Shipment $shipment): Shipment
    {
        $dto = new Shipment();
        $dto->id = (int) $shipment->getId();
        $dto->orderId = (int) $shipment->getOrderId();
        $dto->incrementId = $shipment->getIncrementId();
        $dto->totalQty = (int) $shipment->getTotalQty();
        $dto->createdAt = $shipment->getCreatedAt();

        $order = $shipment->getOrder();
        $dto->orderIncrementId = $order ? $order->getIncrementId() : null;

        // Map tracks
        $dto->tracks = [];
        foreach ($shipment->getAllTracks() as $track) {
            $trackDto = new ShipmentTrack();
            $trackDto->id = (int) $track->getId();
            $trackDto->carrier = $track->getCarrierCode();
            $trackDto->title = $track->getTitle();
            $trackDto->trackNumber = $track->getTrackNumber();
            $dto->tracks[] = $trackDto;
        }

        // Map items
        $dto->items = [];
        foreach ($shipment->getAllItems() as $item) {
            $itemDto = new ShipmentItem();
            $itemDto->sku = $item->getSku();
            $itemDto->name = $item->getName();
            $itemDto->qty = (float) $item->getQty();
            $dto->items[] = $itemDto;
        }

        return $dto;
    }
}
