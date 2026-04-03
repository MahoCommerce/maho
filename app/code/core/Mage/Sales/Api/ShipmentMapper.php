<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

/**
 * Shared mapper for converting Mage_Sales_Model_Order_Shipment to Shipment DTO
 */
class ShipmentMapper
{
    public static function mapToDto(\Mage_Sales_Model_Order_Shipment $shipment): Shipment
    {
        $dto = new Shipment();
        $dto->id = (int) $shipment->getId();
        $dto->orderId = (int) $shipment->getOrderId();
        $dto->incrementId = $shipment->getIncrementId();
        $dto->totalQty = (int) $shipment->getTotalQty();
        $dto->createdAt = $shipment->getCreatedAt();

        $order = $shipment->getOrder();
        $dto->orderIncrementId = $order ? $order->getIncrementId() : null;

        $dto->tracks = [];
        foreach ($shipment->getAllTracks() as $track) {
            $trackDto = new ShipmentTrack();
            $trackDto->id = (int) $track->getId();
            $trackDto->carrier = $track->getCarrierCode();
            $trackDto->title = $track->getTitle();
            $trackDto->trackNumber = $track->getTrackNumber();
            $dto->tracks[] = $trackDto;
        }

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
