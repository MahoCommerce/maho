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

/**
 * Gift Card Payment Form Block
 */
class Maho_Giftcard_Block_Payment_Form extends Mage_Payment_Block_Form
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/giftcard/payment/form.phtml');
    }

    /**
     * Get payment method title with additional message
     *
     * @return string
     */
    public function getMethodTitle(): string
    {
        return $this->__('This order will be fully paid by the gift card(s)');
    }

    /**
     * Get applied gift card codes with amounts
     *
     * @return array
     */
    public function getAppliedGiftcards(): array
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $codesJson = $quote->getGiftcardCodes();

        if (empty($codesJson)) {
            return [];
        }

        $codes = json_decode($codesJson, true) ?: [];
        $result = [];

        foreach ($codes as $code => $amount) {
            $result[] = [
                'code' => $this->maskGiftcardCode($code),
                'amount' => Mage::helper('core')->currency($amount, true, false),
            ];
        }

        return $result;
    }

    /**
     * Mask gift card code for display
     *
     * @param string $code
     * @return string
     */
    protected function maskGiftcardCode(string $code): string
    {
        $length = strlen($code);
        if ($length <= 8) {
            return $code;
        }

        return substr($code, 0, 4) . str_repeat('*', $length - 8) . substr($code, -4);
    }
}
