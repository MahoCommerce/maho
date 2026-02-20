<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
