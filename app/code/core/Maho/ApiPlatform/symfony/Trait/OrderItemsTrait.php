<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shared validation for an API items list that names order items.
 *
 * Centralises the checks that InvoiceProcessor, ShipmentProcessor and
 * CreditMemoProcessor each held their own copy of. The three copies had already
 * drifted apart on a non numeric order item id.
 */
trait OrderItemsTrait
{
    /**
     * Validate one entry of an API items list against the order.
     *
     * A credit memo passes $allowZeroQty, because an adjustment-only refund
     * sends a qty of 0 for every item.
     *
     * @return array{item: \Mage_Sales_Model_Order_Item, qty: float}
     */
    protected function parseOrderItemEntry(
        mixed $itemData,
        \Mage_Sales_Model_Order $order,
        bool $allowZeroQty = false,
    ): array {
        if (!is_array($itemData)) {
            throw new BadRequestHttpException('Each item must be an object with orderItemId and qty');
        }

        $orderItemId = $itemData['orderItemId'] ?? null;
        if (!is_numeric($orderItemId) || (int) $orderItemId <= 0) {
            throw new BadRequestHttpException('Each item must have a valid orderItemId');
        }

        $qty = $itemData['qty'] ?? null;
        if (!is_numeric($qty) || ($allowZeroQty ? (float) $qty < 0 : (float) $qty <= 0)) {
            throw new BadRequestHttpException($allowZeroQty
                ? 'Each item must have qty >= 0'
                : 'Each item must have qty > 0');
        }

        $orderItemId = (int) $orderItemId;
        $orderItem = $order->getItemById($orderItemId);
        if (!$orderItem) {
            throw new BadRequestHttpException("Order item {$orderItemId} does not belong to this order");
        }

        return ['item' => $orderItem, 'qty' => (float) $qty];
    }
}
