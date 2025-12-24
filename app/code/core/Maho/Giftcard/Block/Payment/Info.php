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
 * Gift Card Payment Info Block
 */
class Maho_Giftcard_Block_Payment_Info extends Mage_Payment_Block_Info
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/giftcard/payment/info.phtml');
    }

    /**
     * Prepare payment info
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareSpecificInformation($transport = null)
    {
        if ($this->_paymentSpecificInformation !== null) {
            return $this;
        }

        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();

        // Add gift card specific information
        if ($info->getOrder()) {
            $order = $info->getOrder();
            $codesJson = $order->getGiftcardCodes();

            if ($codesJson !== null && $codesJson !== '') {
                $codes = json_decode($codesJson, true) ?: [];
                $giftcardInfo = [];

                foreach ($codes as $code => $amount) {
                    $maskedCode = $this->maskGiftcardCode($code);
                    $formattedAmount = Mage::helper('core')->currency($amount, true, false);
                    $giftcardInfo[] = "{$maskedCode}: {$formattedAmount}";
                }

                $transport->setData($this->__('Gift Cards Applied'), implode(', ', $giftcardInfo));
                $transport->setData(
                    $this->__('Total Gift Card Amount'),
                    Mage::helper('core')->currency(abs($order->getGiftcardAmount()), true, false),
                );
            }
        }

        $this->_paymentSpecificInformation = $transport;

        return $this;
    }

    /**
     * Mask gift card code for display
     */
    protected function maskGiftcardCode(string $code): string
    {
        $length = strlen($code);
        if ($length <= 8) {
            return $code;
        }

        return substr($code, 0, 4) . str_repeat('*', $length - 8) . substr($code, -4);
    }

    /**
     * Get payment method title
     */
    public function getMethodTitle(): string
    {
        return $this->__('Gift Card');
    }
}
