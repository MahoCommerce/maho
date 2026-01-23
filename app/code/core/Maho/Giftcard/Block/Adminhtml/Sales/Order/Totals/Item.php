<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Adminhtml_Sales_Order_Totals_Item extends Mage_Adminhtml_Block_Sales_Order_Totals_Item
{
    /**
     * Get the display label for gift cards with codes
     *
     * @return string
     */
    public function getLabel()
    {
        $label = $this->__('Gift Cards');

        $order = $this->getParentBlock()->getSource();
        if ($order && $order->getGiftcardCodes()) {
            $codes = [];
            $giftcardCodes = $order->getGiftcardCodes();
            $codesArray = json_decode($giftcardCodes, true);
            if (is_array($codesArray)) {
                $codes = array_keys($codesArray);
            }

            if ($codes !== []) {
                $label .= ' (' . implode(', ', $codes) . ')';
            }
        }

        return $label;
    }

    /**
     * Get the field value (always negative for gift cards)
     *
     * @return float
     */
    public function getFieldValue()
    {
        $value = parent::getFieldValue();
        // Gift cards should always display as negative
        return -abs($value);
    }
}
