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

class Maho_Giftcard_Model_Total_Creditmemo extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();

        // Get total gift card amount used on order (stored as negative, so use abs)
        $orderBaseGiftcardAmount = abs((float) $order->getBaseGiftcardAmount());
        $orderGiftcardAmount = abs((float) $order->getGiftcardAmount());

        if ($orderBaseGiftcardAmount <= 0) {
            return $this;
        }

        // Check if order was fully paid by gift card (original grand_total was ~0)
        $orderWasFullyPaidByGiftcard = abs((float) $order->getGrandTotal()) < 0.01;

        // Get current grand total (this includes subtotal, shipping, tax, and adjustments)
        $baseGrandTotal = (float) $creditmemo->getBaseGrandTotal();
        $grandTotal = (float) $creditmemo->getGrandTotal();

        // Calculate how much gift card has already been refunded in previous credit memos
        $baseGiftcardRefunded = 0;
        $giftcardRefunded = 0;
        foreach ($order->getCreditmemosCollection() as $previousCreditmemo) {
            if ($previousCreditmemo->getId() && $previousCreditmemo->getId() != $creditmemo->getId()) {
                $baseGiftcardRefunded += abs((float) $previousCreditmemo->getBaseGiftcardAmount());
                $giftcardRefunded += abs((float) $previousCreditmemo->getGiftcardAmount());
            }
        }

        // Calculate remaining gift card amount that can be refunded
        $baseGiftcardRemaining = $orderBaseGiftcardAmount - $baseGiftcardRefunded;
        $giftcardRemaining = $orderGiftcardAmount - $giftcardRefunded;

        if ($baseGiftcardRemaining <= 0) {
            return $this;
        }

        if ($orderWasFullyPaidByGiftcard) {
            // For orders fully paid by gift card, refund the credit memo amount
            // but never more than what remains of the original gift card amount
            $baseGiftcardToRefund = min($baseGrandTotal, $baseGiftcardRemaining);
            $giftcardToRefund = min($grandTotal, $giftcardRemaining);
        } else {
            // For partial gift card orders, calculate proportional refund
            // based on how much of the order total is being refunded

            // Calculate ratio based on order total (before gift card)
            $orderBaseTotal = abs((float) $order->getBaseSubtotal())
                + abs((float) $order->getBaseShippingAmount())
                + abs((float) $order->getBaseTaxAmount());

            $creditmemoBaseTotal = abs((float) $creditmemo->getBaseSubtotal())
                + abs((float) $creditmemo->getBaseShippingAmount())
                + abs((float) $creditmemo->getBaseTaxAmount());

            if ($orderBaseTotal > 0) {
                $refundRatio = min(1.0, $creditmemoBaseTotal / $orderBaseTotal);
            } else {
                $refundRatio = 1.0;
            }

            // Calculate proportional gift card refund
            $baseGiftcardToRefund = min($baseGiftcardRemaining, round($orderBaseGiftcardAmount * $refundRatio, 2));
            $giftcardToRefund = min($giftcardRemaining, round($orderGiftcardAmount * $refundRatio, 2));

            // For full refunds, use remaining to avoid rounding issues
            if ($creditmemo->isLast() && $refundRatio >= 0.99) {
                $baseGiftcardToRefund = $baseGiftcardRemaining;
                $giftcardToRefund = $giftcardRemaining;
            }
        }

        if ($baseGiftcardToRefund > 0) {
            // Store the gift card amount being refunded (as negative to match order convention)
            $creditmemo->setBaseGiftcardAmount(-$baseGiftcardToRefund);
            $creditmemo->setGiftcardAmount(-$giftcardToRefund);

            // Reduce the grand total by the gift card refund amount
            $creditmemo->setGrandTotal($grandTotal - $giftcardToRefund);
            $creditmemo->setBaseGrandTotal($baseGrandTotal - $baseGiftcardToRefund);

            // Ensure grand total doesn't go negative
            if ($creditmemo->getGrandTotal() < 0) {
                $creditmemo->setGrandTotal(0);
            }
            if ($creditmemo->getBaseGrandTotal() < 0) {
                $creditmemo->setBaseGrandTotal(0);
            }

            // Allow zero grand total when gift card covers the entire refund
            // This bypasses the "Credit memo's total must be positive" validation
            if ($creditmemo->getGrandTotal() < 0.01) {
                $creditmemo->setAllowZeroGrandTotal(true);
            }
        }

        return $this;
    }
}
