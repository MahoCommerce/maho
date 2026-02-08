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

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\Shipment;
use Maho\ApiPlatform\ApiResource\ShipmentItem;
use Maho\ApiPlatform\ApiResource\ShipmentTrack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shipment State Processor - Handles shipment creation for API Platform
 *
 * @implements ProcessorInterface<Shipment, Shipment>
 */
final class ShipmentProcessor implements ProcessorInterface
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Shipment
    {
        $operationName = $operation->getName();

        return match ($operationName) {
            'createShipment' => $this->createShipment($context),
            default => $this->createShipmentFromRest($uriVariables, $context),
        };
    }

    private function createShipmentFromRest(array $uriVariables, array $context): Shipment
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        $body = $context['request']?->toArray() ?? [];

        return $this->doCreateShipment(
            $orderId,
            $body['items'] ?? null,
            $body['tracks'] ?? [],
            $body['comment'] ?? null,
            (bool) ($body['notifyCustomer'] ?? false),
        );
    }

    private function createShipment(array $context): Shipment
    {
        $args = $context['args']['input'] ?? [];
        $orderId = (int) ($args['orderId'] ?? 0);

        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        return $this->doCreateShipment(
            $orderId,
            $args['items'] ?? null,
            $args['tracks'] ?? [],
            $args['comment'] ?? null,
            (bool) ($args['notifyCustomer'] ?? false),
        );
    }

    private function doCreateShipment(
        int $orderId,
        ?array $items,
        array $tracks,
        ?string $comment,
        bool $notifyCustomer,
    ): Shipment {
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        if (!$order->canShip()) {
            throw new BadRequestHttpException('Order cannot be shipped (already fully shipped or not in a shippable state)');
        }

        // Build qty map: orderItemId => qty to ship
        $qtyMap = [];
        if ($items !== null && count($items) > 0) {
            foreach ($items as $itemData) {
                $orderItemId = (int) ($itemData['orderItemId'] ?? 0);
                $qty = (float) ($itemData['qty'] ?? 0);

                if ($orderItemId <= 0) {
                    throw new BadRequestHttpException('Each item must have a valid orderItemId');
                }
                if ($qty <= 0) {
                    throw new BadRequestHttpException('Each item must have qty > 0');
                }

                $qtyMap[$orderItemId] = $qty;
            }
        }

        // Prepare shipment using service/order (handles qty validation internally)
        $shipment = \Mage::getModel('sales/service_order', $order)
            ->prepareShipment($qtyMap ?: null);

        if (!$shipment) {
            throw new BadRequestHttpException('Cannot create shipment: no items to ship');
        }

        if (!$shipment->getTotalQty()) {
            throw new BadRequestHttpException('Cannot create shipment: total quantity is zero');
        }

        // Add tracking info
        foreach ($tracks as $trackData) {
            $carrierCode = $trackData['carrierCode'] ?? 'custom';
            $title = $trackData['title'] ?? $carrierCode;
            $trackNumber = $trackData['trackNumber'] ?? '';

            if (empty($trackNumber)) {
                throw new BadRequestHttpException('Track number is required for each tracking entry');
            }

            $track = \Mage::getModel('sales/order_shipment_track');
            $track->setCarrierCode($carrierCode);
            $track->setTitle($title);
            $track->setTrackNumber($trackNumber);
            $shipment->addTrack($track);
        }

        // Add comment
        if ($comment) {
            $shipment->addComment($comment, $notifyCustomer);
        }

        // Register and save
        $shipment->register();

        \Mage::getModel('core/resource_transaction')
            ->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();

        // Send notification email
        if ($notifyCustomer) {
            $shipment->sendEmail(true, $comment ?? '');
        }

        return $this->mapToDto($shipment);
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
