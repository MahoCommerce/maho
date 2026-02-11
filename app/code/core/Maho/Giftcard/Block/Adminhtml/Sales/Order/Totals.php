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
/**
 * Admin order totals block - gift card totals are added via child block
 * @see Maho_Giftcard_Block_Adminhtml_Sales_Order_Totals_Giftcard
 */

class Maho_Giftcard_Block_Adminhtml_Sales_Order_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    // Gift card totals are added via initTotals() in child block
    // configured in layout XML (adminhtml_sales_order_view handle)
}
