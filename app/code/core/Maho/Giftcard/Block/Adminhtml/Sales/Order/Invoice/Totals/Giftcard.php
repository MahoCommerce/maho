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
 * Gift card invoice totals block
 */
class Maho_Giftcard_Block_Adminhtml_Sales_Order_Invoice_Totals_Giftcard extends Mage_Adminhtml_Block_Template
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

        $source = $parent->getSource();
        if (!$source) {
            return $this;
        }

        // Get order for gift card codes
        $order = null;
        if ($source instanceof Mage_Sales_Model_Order_Invoice) {
            $order = $source->getOrder();
        } elseif ($source instanceof Mage_Sales_Model_Order) {
            $order = $source;
        }

        // Get gift card amount from source (invoice) or order
        $giftcardAmount = $source->getGiftcardAmount();
        if (!$giftcardAmount && $order) {
            $giftcardAmount = $order->getGiftcardAmount();
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

        $label = Mage::helper('giftcard')->__('Gift Cards');
        if ($codes !== []) {
            $label .= ' (' . implode(', ', $codes) . ')';
        }

        $baseGiftcardAmount = $source->getBaseGiftcardAmount();
        if (!$baseGiftcardAmount && $order) {
            $baseGiftcardAmount = $order->getBaseGiftcardAmount();
        }

        // Add total after tax
        $parent->addTotal(new Maho\DataObject([
            'code'       => 'giftcard',
            'value'      => -abs((float) $giftcardAmount),
            'base_value' => -abs((float) $baseGiftcardAmount),
            'label'      => $label,
        ]), 'tax');

        return $this;
    }
}
