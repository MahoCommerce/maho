<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shipment State Processor - Handles shipment creation for API Platform.
 */
final class ShipmentProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Shipment
    {
        $this->requireAdminOrApiUser('Shipment creation requires admin or API access');
        $this->requireApiPermission('shipments/create');
        $operationName = $operation->getName();

        return match ($operationName) {
            'createShipment' => $this->createShipment($context),
            'add_shipment_track', 'addTrack' => $this->addTrack($uriVariables, $context),
            'remove_shipment_track', 'removeTrack' => $this->removeTrack($uriVariables, $context),
            default => $this->createShipmentFromRest($uriVariables, $context),
        };
    }

    /**
     * Resolve a shipment from the REST {id} URI variable or the GraphQL
     * shipmentId arg.
     */
    private function resolveShipment(array $uriVariables, array $context): \Mage_Sales_Model_Order_Shipment
    {
        $args = $context['args']['input'] ?? [];
        $shipmentId = (int) ($uriVariables['id'] ?? $args['shipmentId'] ?? 0);
        if (!$shipmentId) {
            throw new BadRequestHttpException('Shipment ID is required');
        }

        $shipment = \Mage::getModel('sales/order_shipment')->load($shipmentId);
        if (!$shipment->getId()) {
            throw new NotFoundHttpException('Shipment not found');
        }

        return $shipment;
    }

    /**
     * Add a tracking number to an existing shipment.
     */
    private function addTrack(array $uriVariables, array $context): Shipment
    {
        $args = $context['args']['input'] ?? [];
        $trackNumber = trim((string) ($args['trackNumber'] ?? ''));
        if ($trackNumber === '') {
            throw new BadRequestHttpException('Track number is required');
        }
        $carrierCode = $args['carrierCode'] ?? 'custom';
        $title = $args['title'] ?? $carrierCode;

        $shipment = $this->resolveShipment($uriVariables, $context);

        $track = \Mage::getModel('sales/order_shipment_track');
        $track->setCarrierCode($carrierCode);
        $track->setTitle($title);
        $track->setTrackNumber($trackNumber);
        $shipment->addTrack($track);
        $shipment->save();

        return Shipment::fromModel($shipment->load($shipment->getId()));
    }

    /**
     * Remove a tracking number from a shipment. The track must belong to the
     * referenced shipment, otherwise a 404 is returned (no cross-shipment delete).
     */
    private function removeTrack(array $uriVariables, array $context): Shipment
    {
        $args = $context['args']['input'] ?? [];
        // The {trackId} URI placeholder isn't in the operation's uriVariables map,
        // so recover it from the route params when absent.
        $trackId = (int) ($uriVariables['trackId'] ?? $args['trackId'] ?? 0);
        if (!$trackId) {
            $request = $context['request'] ?? null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                $trackId = (int) ($request->attributes->get('_route_params')['trackId'] ?? 0);
            }
        }
        if (!$trackId) {
            throw new BadRequestHttpException('Track ID is required');
        }

        $shipment = $this->resolveShipment($uriVariables, $context);

        $track = \Mage::getModel('sales/order_shipment_track')->load($trackId);
        if (!$track->getId() || (int) $track->getParentId() !== (int) $shipment->getId()) {
            throw new NotFoundHttpException('Tracking entry not found for this shipment');
        }

        $track->delete();

        return Shipment::fromModel(\Mage::getModel('sales/order_shipment')->load($shipment->getId()));
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

        // Serialize with the order's other state transitions so two concurrent
        // requests can't both pass canShip() and both register a shipment,
        // decrementing inventory twice. Shared per-order lock name, see
        // OrderService::withOrderLock().
        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'maho_order_mutate:' . (int) $order->getId();
        if (!$write->getLock($lockName, 5)) {
            throw new ConflictHttpException('Another operation is already in progress for this order');
        }

        try {
            // Re-read under the lock so canShip() reflects the live state.
            $order->load($orderId);
            return $this->buildAndRegisterShipment($order, $items, $tracks, $comment, $notifyCustomer);
        } finally {
            $write->releaseLock($lockName);
        }
    }

    private function buildAndRegisterShipment(
        \Mage_Sales_Model_Order $order,
        ?array $items,
        array $tracks,
        ?string $comment,
        bool $notifyCustomer,
    ): Shipment {
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

        return Shipment::fromModel($shipment);
    }
}
