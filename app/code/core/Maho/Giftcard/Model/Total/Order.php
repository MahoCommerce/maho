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

class Maho_Giftcard_Model_Total_Order extends Mage_Sales_Model_Order_Total_Abstract
{
    /**
     * Collect gift card totals
     *
     * Gift card amounts are already deducted from grand_total during quote
     * total collection (via _addAmount in Total_Quote::collect) and carried
     * over during quote-to-order conversion. No further adjustment needed.
     *
     * @return $this
     */
    public function collect(Mage_Sales_Model_Order $order)
    {
        return $this;
    }

    /**
     * Add gift card information to order totals
     *
     * @return $this
     */
    public function fetch(Mage_Sales_Model_Order $order)
    {
        $amount = $order->getGiftcardAmount();
        if ($amount != 0) {
            // Get gift card codes for display
            $codes = [];
            $giftcardCodes = $order->getGiftcardCodes();
            if ($giftcardCodes) {
                $codesArray = json_decode($giftcardCodes, true);
                if (is_array($codesArray)) {
                    foreach (array_keys($codesArray) as $code) {
                        if (strlen($code) > 10) {
                            $codes[] = substr($code, 0, 5) . '...' . substr($code, -4);
                        } else {
                            $codes[] = $code;
                        }
                    }
                }
            }

            $title = Mage::helper('giftcard')->__('Gift Cards');
            if ($codes !== []) {
                $title .= ' (' . implode(', ', $codes) . ')';
            }

            // Total is added via layout XML and block rendering
            // The gift card amount is stored on the order and displayed in order totals
        }
        return $this;
    }
}
