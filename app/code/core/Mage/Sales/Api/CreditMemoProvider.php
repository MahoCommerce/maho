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
        $this->resourceClass = $operation->getClass();
        $this->modelAlias = 'sales/order_creditmemo';

        $operationName = $operation->getName();

        if ($operationName === 'order') {
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

        $this->assertStoreAllowed((int) $creditmemo->getStoreId(), $this->requireUser(), 'credit memo');

        return CreditMemo::fromModel($creditmemo);
    }

    private function getCreditMemosForOrder(int $orderId, array $context): TraversablePaginator
    {
        $order = \Mage::getModel('sales/order');
        $order->load($orderId);

        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        ['page' => $page, 'pageSize' => $perPage] = $this->extractPagination($context);

        $collection = \Mage::getResourceModel('sales/order_creditmemo_collection');
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($perPage)->setCurPage($page);

        $models = array_values(iterator_to_array($collection));
        $this->preloadItemsAndComments($models);

        $creditmemos = [];
        foreach ($models as $creditmemo) {
            // Reuse the already-loaded order so afterLoad's getOrder() doesn't
            // re-load it per credit memo.
            $creditmemos[] = CreditMemo::fromModel($creditmemo->setOrder($order));
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
        $this->applyAllowedStoreFilter($collection, $this->requireUser());
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($perPage)->setCurPage($page);

        $models = array_values(iterator_to_array($collection));
        $this->preloadItemsAndComments($models);

        // Orders differ per memo here, so batch just the increment ids the DTO
        // needs instead of loading every order.
        if ($models !== []) {
            $orderIds = array_unique(array_map(static fn($creditmemo): int => (int) $creditmemo->getOrderId(), $models));
            $read = \Mage::getSingleton('core/resource')->getConnection('core_read');
            $incrementIds = $read->fetchPairs(
                $read->select()
                    ->from(\Mage::getSingleton('core/resource')->getTableName('sales/order'), ['entity_id', 'increment_id'])
                    ->where('entity_id IN (?)', $orderIds),
            );
            foreach ($models as $creditmemo) {
                $creditmemo->setData('_preloaded_order_increment_id', $incrementIds[$creditmemo->getOrderId()] ?? null);
            }
        }

        $creditmemos = array_map(CreditMemo::fromModel(...), $models);

        return new TraversablePaginator(new \ArrayIterator($creditmemos), $page, $perPage, (int) $collection->getSize());
    }

    /**
     * Batch-load items and comments for a page of credit memos (2 queries
     * instead of 2 per memo); CreditMemo::afterLoad() consumes the preloaded
     * sets.
     *
     * @param array<\Mage_Sales_Model_Order_Creditmemo> $creditmemos
     */
    private function preloadItemsAndComments(array $creditmemos): void
    {
        if ($creditmemos === []) {
            return;
        }
        $creditmemoIds = array_map(static fn($creditmemo): int => (int) $creditmemo->getId(), $creditmemos);

        $itemsByCreditmemo = [];
        $itemCollection = \Mage::getResourceModel('sales/order_creditmemo_item_collection')
            ->addFieldToFilter('parent_id', ['in' => $creditmemoIds]);
        foreach ($itemCollection as $item) {
            $itemsByCreditmemo[(int) $item->getParentId()][] = $item;
        }

        $commentsByCreditmemo = [];
        $commentCollection = \Mage::getResourceModel('sales/order_creditmemo_comment_collection')
            ->addFieldToFilter('parent_id', ['in' => $creditmemoIds]);
        foreach ($commentCollection as $comment) {
            $commentsByCreditmemo[(int) $comment->getParentId()][] = $comment;
        }

        foreach ($creditmemos as $creditmemo) {
            $id = (int) $creditmemo->getId();
            $creditmemo->setData('_preloaded_items', $itemsByCreditmemo[$id] ?? []);
            $creditmemo->setData('_preloaded_comments', $commentsByCreditmemo[$id] ?? []);
        }
    }
}
