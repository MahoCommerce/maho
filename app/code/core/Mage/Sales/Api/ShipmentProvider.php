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

        if ($operationName === 'orderShipments') {
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
            $shipments[] = Shipment::fromModel($shipment);
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

        $shipments = [];
        foreach ($collection as $shipment) {
            $shipments[] = Shipment::fromModel($shipment);
        }

        return new TraversablePaginator(new \ArrayIterator($shipments), $page, $perPage, (int) $collection->getSize());
    }
}
