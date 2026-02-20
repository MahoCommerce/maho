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

class Maho_Giftcard_Block_Adminhtml_Sales_Order_Creditmemo_Totals_Giftcard extends Mage_Adminhtml_Block_Template
{
    /**
     * Add gift card total to parent totals block
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

        // Get the source (creditmemo or order depending on context)
        $source = $parent->getSource();
        if (!$source) {
            return $this;
        }

        // For credit memo new/create page, get gift card amount from order
        $order = null;
        if ($source instanceof Mage_Sales_Model_Order_Creditmemo) {
            $order = $source->getOrder();
            $giftcardAmount = $source->getGiftcardAmount();
            // If credit memo doesn't have gift card amount yet, get from order
            if (!$giftcardAmount) {
                $giftcardAmount = $order->getGiftcardAmount();
            }
        } elseif ($source instanceof Mage_Sales_Model_Order) {
            $order = $source;
            $giftcardAmount = $source->getGiftcardAmount();
        } else {
            return $this;
        }

        if (!$giftcardAmount || abs((float) $giftcardAmount) < 0.01) {
            return $this;
        }

        // Get gift card codes for display
        $codes = [];
        if ($order && $order->getGiftcardCodes()) {
            $codesArray = json_decode($order->getGiftcardCodes(), true);
            if (is_array($codesArray)) {
                $codes = array_keys($codesArray);
            }
        }

        $label = Mage::helper('giftcard')->__('Refund to Gift Card');
        if ($codes !== []) {
            $label .= ' (' . implode(', ', $codes) . ')';
        }

        $baseGiftcardAmount = $source->getBaseGiftcardAmount() ?: ($order ? $order->getBaseGiftcardAmount() : 0);

        // Add total after tax - show as positive since it's money going back to gift card
        // Use a custom block_html to avoid it being summed into grand_total display
        $parent->addTotal(new Maho\DataObject([
            'code'       => 'giftcard',
            'value'      => abs((float) $giftcardAmount),
            'base_value' => abs((float) $baseGiftcardAmount),
            'label'      => $label,
            'is_formated' => false,
        ]), 'tax');

        return $this;
    }
}
