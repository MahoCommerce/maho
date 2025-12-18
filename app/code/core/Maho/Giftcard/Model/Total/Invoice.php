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
