<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Gift Card Cart Total Renderer
 */
class Maho_Giftcard_Block_Cart_Total extends Mage_Checkout_Block_Total_Default
{
    protected $_template = 'checkout/total/giftcard.phtml';

    /**
     * Get applied gift card codes
     */
    public function getGiftCardCodes(): array
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $codesJson = $quote->getGiftcardCodes();

        if (!$codesJson) {
            return [];
        }

        $codes = json_decode($codesJson, true);
        return is_array($codes) ? $codes : [];
    }
}
