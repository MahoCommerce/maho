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
