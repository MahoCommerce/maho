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
 * Gift Card cart controller
 */
class Maho_Giftcard_CartController extends Mage_Core_Controller_Front_Action
{
    /**
     * Apply gift card to cart
     */
    public function applyAction()
    {
        $code = trim((string) $this->getRequest()->getParam('giftcard_code'));

        if (!$code) {
            Mage::getSingleton('checkout/session')->addError(
                $this->__('Please enter a gift card code.'),
            );
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $quote = $this->_getQuote();

            // Check if cart has gift card products
            foreach ($quote->getAllItems() as $item) {
                if ($item->getProductType() === 'giftcard') {
                    Mage::getSingleton('checkout/session')->addError(
                        $this->__('Gift cards cannot be used to purchase gift card products.'),
                    );
                    $this->_redirect('checkout/cart');
                    return;
                }
            }

            // Load gift card by code
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

            if (!$giftcard->getId()) {
                Mage::throwException($this->__('Gift card "%s" is not valid.', $code));
            }

            if (!$giftcard->isValid()) {
                if ($giftcard->getStatus() === 'pending') {
                    Mage::throwException($this->__('Gift card "%s" is pending activation.', $code));
                } elseif ($giftcard->getStatus() === 'expired') {
                    Mage::throwException($this->__('Gift card "%s" has expired.', $code));
                } elseif ($giftcard->getStatus() === 'used') {
                    Mage::throwException($this->__('Gift card "%s" has been fully used.', $code));
                } else {
                    Mage::throwException($this->__('Gift card "%s" is not active.', $code));
                }
            }

            // Get currently applied codes
            $appliedCodes = $quote->getGiftcardCodes();
            if ($appliedCodes) {
                $appliedCodes = json_decode($appliedCodes, true);
            } else {
                $appliedCodes = [];
            }

            // Check if already applied
            if (isset($appliedCodes[$code])) {
                Mage::throwException($this->__('Gift card "%s" is already applied.', $code));
            }

            // For cart page application, we need to set an initial amount
            // The total collector will adjust this based on the actual cart total
            // We set the gift card balance as the max amount it can use
            $appliedCodes[$code] = $giftcard->getBalance();

            $quote->setGiftcardCodes(json_encode($appliedCodes));
            $quote->collectTotals()->save();

            Mage::getSingleton('checkout/session')->addSuccess(
                $this->__('Gift card "%s" was applied.', $code),
            );

        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * Remove gift card from cart
     */
    public function removeAction()
    {
        $code = $this->getRequest()->getParam('code');

        if (!$code) {
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $quote = $this->_getQuote();

            // Get currently applied codes
            $appliedCodes = $quote->getGiftcardCodes();
            if ($appliedCodes) {
                $appliedCodes = json_decode($appliedCodes, true);
            } else {
                $appliedCodes = [];
            }

            // Remove the code
            if (isset($appliedCodes[$code])) {
                unset($appliedCodes[$code]);

                if (empty($appliedCodes)) {
                    $quote->setGiftcardCodes(null);
                    $quote->setGiftcardAmount(0);
                    $quote->setBaseGiftcardAmount(0);
                } else {
                    $quote->setGiftcardCodes(json_encode($appliedCodes));
                }

                $quote->collectTotals()->save();

                Mage::getSingleton('checkout/session')->addSuccess(
                    $this->__('Gift card was removed.'),
                );
            }

        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError(
                $this->__('Cannot remove gift card.'),
            );
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * Get quote
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }
}
