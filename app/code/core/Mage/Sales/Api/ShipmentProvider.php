<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShipmentProvider extends CrudProvider
{
    use SalesGridTrait;

    /**
     * @return Shipment|ArrayPaginator<Shipment>|TraversablePaginator<Shipment>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Shipment|ArrayPaginator|TraversablePaginator|null
    {
        $this->resourceClass = $operation->getClass();
        $this->modelAlias = 'sales/order_shipment';

        $operationName = $operation->getName();

        if ($operationName === 'order') {
            $orderId = (int) ($context['args']['orderId'] ?? 0);
            if (!$orderId) {
                throw new \RuntimeException('Order ID is required');
            }
            return $this->getShipmentsForOrder($orderId);
        }

        if ($operation instanceof CollectionOperationInterface) {
            // Order-scoped collection (REST /orders/{orderId}/shipments) when an
            // orderId is present; otherwise the unscoped collection (GraphQL
            // `shipments`) is an admin/API list-all across all orders.
            $orderId = (int) ($uriVariables['orderId'] ?? 0);
            if ($orderId) {
                return $this->getShipmentsForOrder($orderId);
            }
            return $this->getAllShipments($context);
        }

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

        $this->assertStoreAllowed($shipment->getStoreId(), $this->requireUser(), 'shipment');

        return Shipment::fromModel($shipment);
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

        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        $shipments = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            // Reuse the already-loaded order so afterLoad's getOrder() doesn't
            // re-load it per shipment.
            $shipments[] = Shipment::fromModel($shipment->setOrder($order));
        }

        return new ArrayPaginator($shipments, 0, count($shipments));
    }

    /**
     * Admin/API list-all across every order, DB-paginated.
     *
     * @return TraversablePaginator<Shipment>
     */
    private function getAllShipments(array $context): TraversablePaginator
    {
        $list = $this->loadGridPage(\Mage_Sales_Model_Order_Shipment::class, 'sales/order_shipment_collection', 'sales/shipment_grid', 'shipping_name', [], $context);

        // Batch-preload the tracks, items, and comments for the page;
        // Shipment::afterLoad() would otherwise lazy-load them per shipment.
        $models = $list['models'];
        if ($models !== []) {
            $shipmentIds = array_map(static fn($s) => (int) $s->getId(), $models);

            $tracksByShipment = [];
            $trackCollection = \Mage::getResourceModel('sales/order_shipment_track_collection')
                ->addFieldToFilter('parent_id', ['in' => $shipmentIds]);
            foreach ($trackCollection as $track) {
                $tracksByShipment[(int) $track->getParentId()][] = $track;
            }

            $itemsByShipment = [];
            $itemCollection = \Mage::getResourceModel('sales/order_shipment_item_collection')
                ->addFieldToFilter('parent_id', ['in' => $shipmentIds]);
            foreach ($itemCollection as $item) {
                $itemsByShipment[(int) $item->getParentId()][] = $item;
            }

            $commentsByShipment = [];
            $commentCollection = \Mage::getResourceModel('sales/order_shipment_comment_collection')
                ->addFieldToFilter('parent_id', ['in' => $shipmentIds]);
            foreach ($commentCollection as $comment) {
                $commentsByShipment[(int) $comment->getParentId()][] = $comment;
            }

            foreach ($models as $shipment) {
                $sid = (int) $shipment->getId();
                $shipment->setData('_preloaded_tracks', $tracksByShipment[$sid] ?? []);
                $shipment->setData('_preloaded_items', $itemsByShipment[$sid] ?? []);
                $shipment->setData('_preloaded_comments', $commentsByShipment[$sid] ?? []);
            }
        }

        $shipments = array_map(Shipment::fromModel(...), $models);

        return new TraversablePaginator(new \ArrayIterator($shipments), $list['page'], $list['pageSize'], $list['total']);
    }
}
