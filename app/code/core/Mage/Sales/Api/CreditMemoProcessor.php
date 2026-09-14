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
        $operationName = $operation->getName();

        return match ($operationName) {
            'create' => $this->createCreditMemoFromGraphQl($context),
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

        $items = $body['items'] ?? null;
        if ($items !== null && !is_array($items)) {
            throw new BadRequestHttpException('Items must be a list of {orderItemId, qty} objects');
        }

        return $this->doCreateCreditMemo(
            $orderId,
            $items,
            $body['comment'] ?? null,
            isset($body['adjustmentPositive']) ? (float) $body['adjustmentPositive'] : null,
            isset($body['adjustmentNegative']) ? (float) $body['adjustmentNegative'] : null,
            isset($body['shippingAmount']) ? (float) $body['shippingAmount'] : null,
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

        $items = $args['items'] ?? null;
        if ($items !== null && !is_array($items)) {
            throw new BadRequestHttpException('Items must be a list of {orderItemId, qty} objects');
        }

        return $this->doCreateCreditMemo(
            $orderId,
            $items,
            $args['comment'] ?? null,
            isset($args['adjustmentPositive']) ? (float) $args['adjustmentPositive'] : null,
            isset($args['adjustmentNegative']) ? (float) $args['adjustmentNegative'] : null,
            isset($args['shippingAmount']) ? (float) $args['shippingAmount'] : null,
            (bool) ($args['offlineRefund'] ?? true),
        );
    }

    private function doCreateCreditMemo(
        int $orderId,
        ?array $items,
        ?string $comment,
        ?float $adjustmentPositive,
        ?float $adjustmentNegative,
        ?float $shippingAmount,
        bool $offlineRefund,
    ): CreditMemo {
        /** @var \Mage_Sales_Model_Order $order */
        $order = \Mage::getModel('sales/order');
        $order->load($orderId);

        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

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

            return $this->buildAndRegisterCreditMemo($order, $items, $comment, $adjustmentPositive, $adjustmentNegative, $shippingAmount, $offlineRefund);
        } finally {
            $write->releaseLock($lockName);
        }
    }

    private function buildAndRegisterCreditMemo(
        \Mage_Sales_Model_Order $order,
        ?array $items,
        ?string $comment,
        ?float $adjustmentPositive,
        ?float $adjustmentNegative,
        ?float $shippingAmount,
        bool $offlineRefund,
    ): CreditMemo {
        // Build qty data array: ['qtys' => [orderItemId => qty]]
        // Adjustments go through $data so prepareCreditmemo() applies them before
        // its single collectTotals() pass. Setting them afterwards and collecting
        // again would double every total (the creditmemo collectors accumulate
        // into grand total and never reset), over-refunding the order.
        $data = ['qtys' => []];
        if ($adjustmentPositive !== null) {
            $data['adjustment_positive'] = $adjustmentPositive;
        }
        if ($adjustmentNegative !== null) {
            $data['adjustment_negative'] = $adjustmentNegative;
        }
        $backToStockItems = [];

        // An absent items key means "refund every refundable item", which is what
        // prepareCreditmemo() does with an empty qtys map. A present items key
        // means "refund exactly these", so an empty list refunds no item at all
        // and leaves an adjustment-only memo (the goodwill refund the admin can
        // record by zeroing every qty).
        if ($items !== null) {
            foreach ($items as $itemData) {
                if (!is_array($itemData)) {
                    throw new BadRequestHttpException('Each item must be an object with orderItemId and qty');
                }

                $orderItemId = $itemData['orderItemId'] ?? null;
                $qty = $itemData['qty'] ?? 0;

                if (!is_numeric($orderItemId) || (int) $orderItemId <= 0) {
                    throw new BadRequestHttpException('Each item must have a valid orderItemId');
                }
                if (!is_numeric($qty) || (float) $qty < 0) {
                    throw new BadRequestHttpException('Each item must have qty >= 0');
                }

                $orderItemId = (int) $orderItemId;
                if (!$order->getItemById($orderItemId)) {
                    throw new BadRequestHttpException("Order item {$orderItemId} does not belong to this order");
                }

                $data['qtys'][$orderItemId] = (float) $qty;

                if (!empty($itemData['backToStock'])) {
                    $backToStockItems[$orderItemId] = true;
                }
            }

            // A zero for every order item is the shape the admin form posts for
            // an adjustment-only memo. It keeps the map non-empty, so
            // prepareCreditmemo() refunds nothing, and register() drops the
            // zero-qty items.
            if ($data['qtys'] === []) {
                foreach ($order->getAllItems() as $orderItem) {
                    $data['qtys'][(int) $orderItem->getId()] = 0.0;
                }
            }
        }

        // The shipping collector refunds the order's whole remaining shipping
        // unless an amount is given, so a memo that refunds no item would still
        // carry the delivery charge. Default it to zero there; the admin form
        // has the same field and the same manual zeroing.
        if ($shippingAmount !== null) {
            $data['shipping_amount'] = $shippingAmount;
        } elseif ($items !== null && !array_filter($data['qtys'], static fn(float $qty): bool => $qty > 0)) {
            $data['shipping_amount'] = 0.0;
        }

        // Prepare credit memo using service/order
        /** @var \Mage_Sales_Model_Service_Order $service */
        $service = \Mage::getModel('sales/service_order', $order);
        $creditmemo = $service->prepareCreditmemo($data);

        if (!$creditmemo) {
            throw new BadRequestHttpException('Cannot create credit memo: no items to refund');
        }

        // Cap the refund at what the order can still refund, so an inflated
        // adjustmentPositive can't over-refund or mint excess store credit.
        // Totals were already collected by prepareCreditmemo() above.
        $refundable = (float) $order->getBaseTotalPaid() - (float) $order->getBaseTotalRefunded();
        if ((float) $creditmemo->getBaseGrandTotal() > $refundable + 0.0001) {
            throw new BadRequestHttpException('Refund amount exceeds the order\'s refundable balance');
        }

        // Same rule as the admin form: a memo that refunds nothing is a no-op
        // record, not a refund. Free orders opt out through the zero-total flag.
        if ((float) $creditmemo->getBaseGrandTotal() <= 0 && !$creditmemo->getAllowZeroGrandTotal()) {
            throw new BadRequestHttpException('Credit memo total must be greater than zero');
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
