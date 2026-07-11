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
        $this->requireAdminOrApiUser('Shipment access requires admin or API access');

        $shipment = \Mage::getModel('sales/order_shipment')->load($id);
        if (!$shipment->getId()) {
            throw new NotFoundHttpException('Shipment not found');
        }
        return Shipment::fromModel($shipment);
    }

    /**
     * @return ArrayPaginator<Shipment>
     */
    private function getShipmentsForOrder(int $orderId): ArrayPaginator
    {
        $this->requireAdminOrApiUser('Shipment access requires admin or API access');

        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

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
        $this->requireAdminOrApiUser('Shipment listing requires admin or API access');

        ['page' => $page, 'pageSize' => $perPage] = $this->extractPagination($context);

        $collection = \Mage::getResourceModel('sales/order_shipment_collection');
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($perPage)->setCurPage($page);

        // Batch-preload the order increment ids, tracks, and items for the
        // page; Shipment::afterLoad() would otherwise lazy-load all three per
        // shipment (~3 extra queries per row on every list response).
        $models = array_values(iterator_to_array($collection));
        if ($models !== []) {
            $shipmentIds = array_map(static fn($s) => (int) $s->getId(), $models);
            $orderIds = array_unique(array_map(static fn($s) => (int) $s->getOrderId(), $models));

            $read = \Mage::getSingleton('core/resource')->getConnection('core_read');
            $incrementIds = $read->fetchPairs(
                $read->select()
                    ->from(\Mage::getSingleton('core/resource')->getTableName('sales/order'), ['entity_id', 'increment_id'])
                    ->where('entity_id IN (?)', $orderIds),
            );

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

            foreach ($models as $shipment) {
                $sid = (int) $shipment->getId();
                $shipment->setData('_preloaded_order_increment_id', $incrementIds[$shipment->getOrderId()] ?? null);
                $shipment->setData('_preloaded_tracks', $tracksByShipment[$sid] ?? []);
                $shipment->setData('_preloaded_items', $itemsByShipment[$sid] ?? []);
            }
        }

        $shipments = array_map(Shipment::fromModel(...), $models);

        return new TraversablePaginator(new \ArrayIterator($shipments), $page, $perPage, (int) $collection->getSize());
    }
}
