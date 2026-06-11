<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Order extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Maho\DataObject $row)
    {
        $orderId = $row->getOrderId();

        if (!$orderId) {
            return '-';
        }

        $url = $this->getUrl('adminhtml/sales_order/view', ['order_id' => $orderId]);
        $incrementId = $row->getOrderIncrementId();

        return sprintf('<a href="%s">#%s</a>', $url, $incrementId ?: $orderId);
    }
}
