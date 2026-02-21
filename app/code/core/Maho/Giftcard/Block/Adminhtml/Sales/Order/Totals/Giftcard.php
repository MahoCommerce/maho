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

class Maho_Giftcard_Block_Adminhtml_Sales_Order_Totals_Giftcard extends Mage_Core_Block_Abstract
{
    /**
     * Add gift card total to parent totals block
     * This method is called by the parent block after _initTotals() and after tax's initTotals()
     *
     * @return $this
     */
    public function initTotals()
    {
        /** @var Mage_Sales_Block_Order_Totals|null $parent */
        $parent = $this->getParentBlock();

        if (!$parent) {
            return $this;
        }

        $order = $parent->getOrder();
        if (!$order) {
            return $this;
        }

        $giftcardAmount = $order->getGiftcardAmount();

        if ((float) $giftcardAmount !== 0.0) {
            // Get gift card codes for display
            $codes = [];
            $giftcardCodes = $order->getGiftcardCodes();
            if ($giftcardCodes) {
                $codesArray = json_decode($giftcardCodes, true);
                if (is_array($codesArray)) {
                    $codes = array_keys($codesArray);
                }
            }

            $label = Mage::helper('giftcard')->__('Gift Cards');
            if ($codes !== []) {
                $label .= ' (' . implode(', ', $codes) . ')';
            }

            // Add before grand_total
            $parent->addTotalBefore(new Maho\DataObject([
                'code'       => 'giftcard',
                'value'      => -abs((float) $giftcardAmount),
                'base_value' => -abs((float) $order->getBaseGiftcardAmount()),
                'label'      => $label,
            ]), ['grand_total', 'base_grandtotal']);

            // Ensure tax appears before giftcard
            $taxTotal = $parent->getTotal('tax');
            if ($taxTotal) {
                $parent->removeTotal('tax');
                $parent->addTotalBefore($taxTotal, 'giftcard');
            }
        }

        return $this;
    }
}
