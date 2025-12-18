<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Model_Total_Order extends Mage_Sales_Model_Order_Total_Abstract
{
    /**
     * Collect gift card totals
     *
     * @param Mage_Sales_Model_Order $order
     * @return $this
     */
    public function collect(Mage_Sales_Model_Order $order)
    {
        parent::collect($order);

        $giftcardAmount = $order->getGiftcardAmount();
        $baseGiftcardAmount = $order->getBaseGiftcardAmount();

        if ($baseGiftcardAmount) {
            $order->setGrandTotal($order->getGrandTotal() - $giftcardAmount);
            $order->setBaseGrandTotal($order->getBaseGrandTotal() - $baseGiftcardAmount);
        }

        return $this;
    }

    /**
     * Add gift card information to order totals
     *
     * @param Mage_Sales_Model_Order $order
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

            $title = Mage::helper('maho_giftcard')->__('Gift Cards');
            if (!empty($codes)) {
                $title .= ' (' . implode(', ', $codes) . ')';
            }

            $order->addTotal([
                'code' => $this->getCode(),
                'title' => $title,
                'value' => -abs($amount),  // Always show as negative
                'base_value' => -abs($order->getBaseGiftcardAmount()),
                'area' => 'footer',
            ], 'discount');
        }
        return $this;
    }
}