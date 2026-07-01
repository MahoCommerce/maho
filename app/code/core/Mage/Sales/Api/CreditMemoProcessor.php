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
 * Credit Memo State Processor - Handles credit memo creation for API Platform.
 */
final class CreditMemoProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreditMemo
    {
        $this->requireAdminOrApiUser('Credit memo creation requires admin or API access');
        $this->requireApiPermission('credit-memos/create');
        $operationName = $operation->getName();

        return match ($operationName) {
            'createCreditMemo' => $this->createCreditMemoFromGraphQl($context),
            default => $this->createCreditMemoFromRest($uriVariables, $context),
        };
    }

    private function createCreditMemoFromRest(array $uriVariables, array $context): CreditMemo
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        $body = $context['request']?->toArray() ?? [];

        return $this->doCreateCreditMemo(
            $orderId,
            $body['items'] ?? [],
            $body['comment'] ?? null,
            isset($body['adjustmentPositive']) ? (float) $body['adjustmentPositive'] : null,
            isset($body['adjustmentNegative']) ? (float) $body['adjustmentNegative'] : null,
            (bool) ($body['offlineRefund'] ?? true),
        );
    }

    private function createCreditMemoFromGraphQl(array $context): CreditMemo
    {
        $args = $context['args']['input'] ?? [];
        $orderId = (int) ($args['orderId'] ?? 0);

        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        return $this->doCreateCreditMemo(
            $orderId,
            $args['items'] ?? [],
            $args['comment'] ?? null,
            isset($args['adjustmentPositive']) ? (float) $args['adjustmentPositive'] : null,
            isset($args['adjustmentNegative']) ? (float) $args['adjustmentNegative'] : null,
            (bool) ($args['offlineRefund'] ?? true),
        );
    }

    private function doCreateCreditMemo(
        int $orderId,
        array $items,
        ?string $comment,
        ?float $adjustmentPositive,
        ?float $adjustmentNegative,
        bool $offlineRefund,
    ): CreditMemo {
        /** @var \Mage_Sales_Model_Order $order */
        $order = \Mage::getModel('sales/order');
        $order->load($orderId);

        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Serialize concurrent refunds on the same order. Without this, two
        // simultaneous requests both pass canCreditmemo() and both register(),
        // issuing a double refund. The lock gives a per-order critical section
        // that releases on disconnect (mirrors OrderService::placeAdminOrder).
        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        // Shared per-order lock: refunds must be mutually exclusive with the
        // order's other state transitions (invoice/ship/cancel), not just with
        // other refunds. See OrderService::withOrderLock().
        $lockName = 'maho_order_mutate:' . (int) $order->getId();
        if (!$write->getLock($lockName, 5)) {
            throw new ConflictHttpException('A refund is already in progress for this order');
        }

        try {
            // Re-load the order under the lock so canCreditmemo() sees the live
            // total_refunded, not a value another request changed while waiting.
            $order->load($orderId);
            if (!$order->canCreditmemo()) {
                throw new BadRequestHttpException('Order cannot be refunded (already fully refunded or not in a refundable state)');
            }

            return $this->buildAndRegisterCreditMemo($order, $items, $comment, $adjustmentPositive, $adjustmentNegative, $offlineRefund);
        } finally {
            $write->releaseLock($lockName);
        }
    }

    private function buildAndRegisterCreditMemo(
        \Mage_Sales_Model_Order $order,
        array $items,
        ?string $comment,
        ?float $adjustmentPositive,
        ?float $adjustmentNegative,
        bool $offlineRefund,
    ): CreditMemo {
        // Build qty data array: ['qtys' => [orderItemId => qty]]
        $data = ['qtys' => []];
        $backToStockItems = [];

        if (!empty($items)) {
            foreach ($items as $itemData) {
                $orderItemId = (int) ($itemData['orderItemId'] ?? 0);
                $qty = (float) ($itemData['qty'] ?? 0);

                if ($orderItemId <= 0) {
                    throw new BadRequestHttpException('Each item must have a valid orderItemId');
                }
                if ($qty <= 0) {
                    throw new BadRequestHttpException('Each item must have qty > 0');
                }

                $data['qtys'][$orderItemId] = $qty;

                if (!empty($itemData['backToStock'])) {
                    $backToStockItems[$orderItemId] = true;
                }
            }
        }

        // Prepare credit memo using service/order
        /** @var \Mage_Sales_Model_Service_Order $service */
        $service = \Mage::getModel('sales/service_order', $order);
        $creditmemo = $service->prepareCreditmemo($data);

        if (!$creditmemo) {
            throw new BadRequestHttpException('Cannot create credit memo: no items to refund');
        }

        // Set adjustments
        if ($adjustmentPositive !== null) {
            $creditmemo->setAdjustmentPositive($adjustmentPositive);
        }
        if ($adjustmentNegative !== null) {
            $creditmemo->setAdjustmentNegative($adjustmentNegative);
        }

        // Handle back to stock for individual items
        if (!empty($backToStockItems)) {
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                if (isset($backToStockItems[$creditmemoItem->getOrderItemId()])) {
                    $creditmemoItem->setBackToStock(true);
                }
            }
        }

        // Handle online vs offline refund
        if ($offlineRefund) {
            $creditmemo->setOfflineRequested(true);
        }
        $creditmemo->setPaymentRefundDisallowed($offlineRefund ? 1.0 : 0.0);

        // Add comment before register/save so it is persisted atomically with
        // the credit memo. Saving it separately after the transaction commit
        // would re-trigger post-save observers on an already-refunded memo.
        if ($comment) {
            $creditmemo->addComment($comment, false);
        }

        // Register the credit memo (triggers payment gateway for online refunds)
        $creditmemo->register();

        // Save credit memo and order in a transaction
        /** @var \Mage_Core_Model_Resource_Transaction $transaction */
        $transaction = \Mage::getModel('core/resource_transaction');
        $transaction->addObject($creditmemo)
            ->addObject($order)
            ->save();

        return CreditMemo::fromModel($creditmemo);
    }
}
