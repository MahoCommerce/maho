<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Sales_Order_Totals extends Mage_Core_Block_Template
{
    /**
     * Initialize gift card totals for parent block
     *
     * Called by parent totals block via child block pattern
     */
    public function initTotals(): self
    {
        /** @var Mage_Sales_Block_Order_Totals|null $parent */
        $parent = $this->getParentBlock();
        if (!$parent) {
            return $this;
        }

        $source = $parent->getSource();
        if (!$source) {
            return $this;
        }

        $giftcardAmount = $source->getGiftcardAmount();

        if ($giftcardAmount != 0) {
            // Get gift card codes for display
            $codes = [];
            $giftcardCodes = $source->getGiftcardCodes();
            if ($giftcardCodes) {
                $codesArray = json_decode($giftcardCodes, true);
                if (is_array($codesArray)) {
                    // Show partial codes for security
                    foreach (array_keys($codesArray) as $code) {
                        if (strlen($code) > 10) {
                            $codes[] = substr($code, 0, 5) . '...' . substr($code, -4);
                        } else {
                            $codes[] = $code;
                        }
                    }
                }
            }

            $label = $this->__('Gift Cards');
            if ($codes !== []) {
                $label .= ' (' . implode(', ', $codes) . ')';
            }

            // Add giftcard before grand_total
            $parent->addTotalBefore(new Maho\DataObject([
                'code'       => 'giftcard',
                'value'      => -abs((float) $giftcardAmount),
                'base_value' => -abs((float) $source->getBaseGiftcardAmount()),
                'label'      => $label,
            ]), ['grand_total', 'base_grandtotal']);

            // Ensure tax appears before giftcard (fix for when tax is mispositioned after grand_total)
            $taxTotal = $parent->getTotal('tax');
            if ($taxTotal) {
                $parent->removeTotal('tax');
                $parent->addTotalBefore($taxTotal, 'giftcard');
            }
        }

        return $this;
    }
}
