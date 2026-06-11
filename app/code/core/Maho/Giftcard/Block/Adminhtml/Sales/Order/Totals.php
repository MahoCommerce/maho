<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Admin order totals block - gift card totals are added via child block
 * @see Maho_Giftcard_Block_Adminhtml_Sales_Order_Totals_Giftcard
 */

class Maho_Giftcard_Block_Adminhtml_Sales_Order_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    // Gift card totals are added via initTotals() in child block
    // configured in layout XML (adminhtml_sales_order_view handle)
}
