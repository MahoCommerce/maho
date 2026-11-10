<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Read one entry of an API items list that names order items. Mage_Sales_Service_Order checks that the item is in the
 * order and that the qty is not negative, for the admin forms too.
 */
trait OrderItemsTrait
{
    /**
     * @return array{orderItemId: int, qty: float}
     */
    protected function parseOrderItemEntry(mixed $itemData): array
    {
        if (!is_array($itemData)) {
            throw new BadRequestHttpException('Each item must be an object with orderItemId and qty');
        }

        $orderItemId = $itemData['orderItemId'] ?? null;
        if (!is_numeric($orderItemId) || (int) $orderItemId <= 0) {
            throw new UnprocessableEntityHttpException('Each item must have a valid orderItemId');
        }

        $qty = $itemData['qty'] ?? null;
        if (!is_numeric($qty)) {
            throw new UnprocessableEntityHttpException('Each item must have a numeric qty');
        }

        return ['orderItemId' => (int) $orderItemId, 'qty' => (float) $qty];
    }
}
