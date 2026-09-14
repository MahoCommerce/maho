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
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Credit Memo State Processor - Handles credit memo creation for API Platform.
 */
final class CreditMemoProcessor extends \Maho\ApiPlatform\Processor
{
    use \Maho\ApiPlatform\Trait\OrderItemsTrait;

    private OrderService $orderService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->orderService = new OrderService();
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreditMemo
    {
        $this->normalizeGraphQlInput($context);

        return $this->createCreditMemo($uriVariables, $context);
    }

    private function createCreditMemo(array $uriVariables, array $context): CreditMemo
    {
        $args = $context['args']['input'] ?? [];
        $orderId = (int) ($uriVariables['orderId'] ?? $args['orderId'] ?? 0);
        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        $items = $args['items'] ?? null;
        if ($items !== null && !is_array($items)) {
            throw new BadRequestHttpException('Items must be a list of {orderItemId, qty} objects');
        }

        $shippingAmount = isset($args['shippingAmount']) ? (float) $args['shippingAmount'] : null;
        if ($shippingAmount !== null && $shippingAmount < 0) {
            throw new BadRequestHttpException('Shipping amount must be >= 0');
        }

        return $this->doCreateCreditMemo(
            $orderId,
            $items,
            $args['comment'] ?? null,
            isset($args['adjustmentPositive']) ? (float) $args['adjustmentPositive'] : null,
            isset($args['adjustmentNegative']) ? (float) $args['adjustmentNegative'] : null,
            $shippingAmount,
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

        // Report an unrefundable order before the item input. OrderService
        // checks this again under the lock, where the answer is authoritative.
        if (!$order->canCreditmemo()) {
            throw new BadRequestHttpException('Order cannot be refunded (already fully refunded or not in a refundable state)');
        }

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

        // An absent items key refunds every refundable item. A present items key
        // refunds exactly the listed quantities, so an empty list refunds no item.
        if ($items !== null) {
            foreach ($items as $itemData) {
                $entry = $this->parseOrderItemEntry($itemData, $order, allowZeroQty: true);
                $orderItemId = (int) $entry['item']->getId();

                if ($entry['qty'] > 0) {
                    $data['qtys'][$orderItemId] = $entry['qty'];
                }

                if (!empty($itemData['backToStock'])) {
                    $backToStockItems[$orderItemId] = true;
                }
            }
        }

        // An empty qty map makes prepareCreditmemo() refund every item, so use a
        // key that matches no order item.
        $refundsNoItem = $items !== null && $data['qtys'] === [];
        if ($refundsNoItem) {
            $data['qtys'][0] = 0.0;
        }

        // The shipping collector refunds the order's whole remaining shipping
        // unless an amount is given, so a memo that refunds no item would still
        // carry the delivery charge.
        if ($shippingAmount !== null) {
            $data['shipping_amount'] = $shippingAmount;
        } elseif ($refundsNoItem) {
            $data['shipping_amount'] = 0.0;
        }

        try {
            $creditmemo = $this->orderService->createCreditMemoForOrder($order, $data, $comment, $offlineRefund, $backToStockItems);
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\RuntimeException) {
            throw new ConflictHttpException('A refund is already in progress for this order');
        }

        if (!$creditmemo) {
            throw new BadRequestHttpException('Order cannot be refunded (already fully refunded or not in a refundable state)');
        }

        return CreditMemo::fromModel($creditmemo);
    }
}
