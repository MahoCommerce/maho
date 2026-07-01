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
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreditMemoProvider extends CrudProvider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CreditMemo|TraversablePaginator|null
    {
        $this->requireAdminOrApiUser('Credit memo access requires admin or API access');
        $this->resourceClass = $operation->getClass();
        $this->modelAlias = 'sales/order_creditmemo';

        $operationName = $operation->getName();

        if ($operationName === 'orderCreditMemos') {
            $orderId = (int) ($context['args']['orderId'] ?? 0);
            return $this->getCreditMemosForOrder($orderId, $context);
        }

        if ($operation instanceof CollectionOperationInterface) {
            // Order-scoped collection (REST /orders/{orderId}/credit-memos) when an
            // orderId is present; otherwise the unscoped collection (GraphQL
            // `creditMemos`) is a list-all across all orders.
            $orderId = (int) ($uriVariables['orderId'] ?? 0);
            if ($orderId) {
                return $this->getCreditMemosForOrder($orderId, $context);
            }
            return $this->getAllCreditMemos($context);
        }

        $id = (int) ($uriVariables['id'] ?? 0);
        if ($id) {
            return $this->getCreditMemoById($id);
        }

        return null;
    }

    private function getCreditMemoById(int $id): CreditMemo
    {
        $creditmemo = \Mage::getModel('sales/order_creditmemo');
        $creditmemo->load($id);

        if (!$creditmemo->getId()) {
            throw new NotFoundHttpException('Credit memo not found');
        }

        return CreditMemo::fromModel($creditmemo);
    }

    private function getCreditMemosForOrder(int $orderId, array $context): TraversablePaginator
    {
        $order = \Mage::getModel('sales/order');
        $order->load($orderId);

        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        ['page' => $page, 'pageSize' => $perPage] = $this->extractPagination($context);

        $collection = \Mage::getResourceModel('sales/order_creditmemo_collection');
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($perPage)->setCurPage($page);

        $creditmemos = [];
        foreach ($collection as $creditmemo) {
            $creditmemos[] = CreditMemo::fromModel($creditmemo);
        }

        return new TraversablePaginator(new \ArrayIterator($creditmemos), $page, $perPage, (int) $collection->getSize());
    }

    /**
     * List-all across every order, DB-paginated. Admin/API access is already
     * enforced at the top of provide().
     *
     * @return TraversablePaginator<CreditMemo>
     */
    private function getAllCreditMemos(array $context): TraversablePaginator
    {
        ['page' => $page, 'pageSize' => $perPage] = $this->extractPagination($context);

        $collection = \Mage::getResourceModel('sales/order_creditmemo_collection');
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($perPage)->setCurPage($page);

        $creditmemos = [];
        foreach ($collection as $creditmemo) {
            $creditmemos[] = CreditMemo::fromModel($creditmemo);
        }

        return new TraversablePaginator(new \ArrayIterator($creditmemos), $page, $perPage, (int) $collection->getSize());
    }
}
