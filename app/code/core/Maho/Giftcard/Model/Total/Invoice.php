<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Model_Total_Invoice extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();

        $giftcardAmount = $order->getGiftcardAmount();
        $baseGiftcardAmount = $order->getBaseGiftcardAmount();

        if ($giftcardAmount) {
            $invoice->setGiftcardAmount($giftcardAmount);
            $invoice->setBaseGiftcardAmount($baseGiftcardAmount);

            $invoice->setGrandTotal($invoice->getGrandTotal() - $giftcardAmount);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() - $baseGiftcardAmount);
        }

        return $this;
    }
}
