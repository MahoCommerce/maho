<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Http\RequestInput;

/**
 * Read one entry of an API items list that names order items. Mage_Sales_Service_Order checks that the item is in the
 * order and that the qty is not negative, for the admin forms too.
 */
trait OrderItemsTrait
{
    /**
     * A value of the wrong type is a 400. A missing or invalid orderItemId or qty is a 422.
     *
     * @return array{orderItemId: int, qty: float}
     * @throws \Mage_Core_Exception_Input
     */
    protected function parseOrderItemEntry(RequestInput $item): array
    {
        $orderItemId = $item->int('orderItemId');
        $qty = $item->float('qty');

        $errors = new \Mage_Core_Exception_Input();
        if ($orderItemId === null || $orderItemId <= 0) {
            $errors->addError($item->pathOf('orderItemId'), 'Each item must have a valid orderItemId');
        }
        if ($qty === null) {
            $errors->addError($item->pathOf('qty'), 'Each item must have a numeric qty');
        }
        $errors->throwIfErrors();

        return ['orderItemId' => (int) $orderItemId, 'qty' => (float) $qty];
    }
}
