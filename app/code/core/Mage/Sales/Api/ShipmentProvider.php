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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use Maho\ApiPlatform\CrudProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShipmentProvider extends CrudProvider
{
    /**
     * @return Shipment|ArrayPaginator<Shipment>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Shipment|ArrayPaginator|null
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
            $orderId = (int) ($uriVariables['orderId'] ?? 0);
            if (!$orderId) {
                throw new \RuntimeException('Order ID is required');
            }
            return $this->getShipmentsForOrder($orderId);
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

        $shipments = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            $shipments[] = Shipment::fromModel($shipment);
        }

        return new ArrayPaginator($shipments, 0, count($shipments));
    }
}
