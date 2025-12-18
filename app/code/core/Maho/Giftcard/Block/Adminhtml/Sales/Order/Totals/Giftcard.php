<?php

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
     * Add gift card total to parent block
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        $parent = $this->getParentBlock();

        if ($parent && $parent instanceof Mage_Adminhtml_Block_Sales_Order_Totals) {
            $order = $parent->getOrder();

            if ($order) {
                $giftcardAmount = $order->getGiftcardAmount();

                if ($giftcardAmount != 0) {
                    // Get gift card codes for display
                    $codes = [];
                    $giftcardCodes = $order->getGiftcardCodes();
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

                    $label = Mage::helper('giftcard')->__('Gift Cards');
                    if (!empty($codes)) {
                        $label .= ' (' . implode(', ', $codes) . ')';
                    }

                    $parent->addTotal(new Varien_Object([
                        'code'       => 'giftcard',
                        'value'      => -abs((float) $giftcardAmount),
                        'base_value' => -abs((float) $order->getBaseGiftcardAmount()),
                        'label'      => $label,
                    ]), 'discount');
                }
            }
        }

        return '';
    }
}
