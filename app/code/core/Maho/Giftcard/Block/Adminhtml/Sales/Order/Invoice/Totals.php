<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Adminhtml_Sales_Order_Invoice_Totals extends Mage_Adminhtml_Block_Sales_Order_Invoice_Totals
{
    /**
     * Initialize gift card totals
     *
     * @return $this
     */
    #[\Override]
    protected function _initTotals()
    {
        parent::_initTotals();

        $invoice = $this->getSource();
        $order = $this->getOrder();

        // For now, use the order's gift card amount
        // TODO: Allow partial invoicing with gift cards
        $giftcardAmount = $order->getGiftcardAmount();

        if ($giftcardAmount != 0) {
            // Get gift card codes for display
            $codes = [];
            $giftcardCodes = $order->getGiftcardCodes();
            if ($giftcardCodes) {
                $codesArray = json_decode($giftcardCodes, true);
                if (is_array($codesArray)) {
                    $codes = array_keys($codesArray);
                }
            }

            $label = $this->__('Gift Cards');
            if ($codes !== []) {
                $label .= ' (' . implode(', ', $codes) . ')';
            }

            $this->addTotalBefore(new Maho\DataObject([
                'code'      => 'giftcard',
                'value'     => -abs($giftcardAmount),
                'base_value' => -abs($order->getBaseGiftcardAmount()),
                'label'     => $label,
                'area'      => 'footer',
            ]), 'grand_total');
        }

        return $this;
    }
}
