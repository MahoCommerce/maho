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
    public function applyAction(): void
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
                if ($giftcard->getStatus() === Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED) {
                    Mage::throwException($this->__('Gift card "%s" has expired.', $code));
                } elseif ($giftcard->getStatus() === Maho_Giftcard_Model_Giftcard::STATUS_USED) {
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
            // We set the gift card balance as the max amount it can use (in quote currency)
            $quoteCurrency = $quote->getQuoteCurrencyCode();
            $appliedCodes[$code] = $giftcard->getBalance($quoteCurrency);

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
    public function removeAction(): void
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

                if ($appliedCodes === []) {
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
     * AJAX apply gift card (for checkout payment step)
     */
    public function ajaxApplyAction(): void
    {
        $result = ['success' => false, 'message' => '', 'html' => ''];

        if (!$this->getRequest()->isPost()) {
            $result['message'] = $this->__('Invalid request.');
            $this->_sendJsonResponse($result);
            return;
        }

        $code = trim((string) $this->getRequest()->getPost('giftcard_code'));

        if (!$code) {
            $result['message'] = $this->__('Please enter a gift card code.');
            $this->_sendJsonResponse($result);
            return;
        }

        try {
            $quote = $this->_getQuote();

            // Check if cart has gift card products
            foreach ($quote->getAllItems() as $item) {
                if ($item->getProductType() === 'giftcard') {
                    $result['message'] = $this->__('Gift cards cannot be used to purchase gift card products.');
                    $this->_sendJsonResponse($result);
                    return;
                }
            }

            // Load gift card by code
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

            if (!$giftcard->getId()) {
                $result['message'] = $this->__('Gift card "%s" is not valid.', $code);
                $this->_sendJsonResponse($result);
                return;
            }

            if (!$giftcard->isValid()) {
                if ($giftcard->getStatus() === Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED) {
                    $result['message'] = $this->__('Gift card "%s" has expired.', $code);
                } elseif ($giftcard->getStatus() === Maho_Giftcard_Model_Giftcard::STATUS_USED) {
                    $result['message'] = $this->__('Gift card "%s" has been fully used.', $code);
                } else {
                    $result['message'] = $this->__('Gift card "%s" is not active.', $code);
                }
                $this->_sendJsonResponse($result);
                return;
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
                $result['message'] = $this->__('Gift card "%s" is already applied.', $code);
                $this->_sendJsonResponse($result);
                return;
            }

            // Apply the gift card
            $quoteCurrency = $quote->getQuoteCurrencyCode();
            $appliedCodes[$code] = $giftcard->getBalance($quoteCurrency);

            $quote->setGiftcardCodes(json_encode($appliedCodes));
            $quote->collectTotals()->save();

            $result['success'] = true;
            $result['message'] = $this->__('Gift card "%s" was applied.', $code);
            $result['data'] = $this->_getGiftcardData($quote);

        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }

        $this->_sendJsonResponse($result);
    }

    /**
     * AJAX remove gift card (for checkout payment step)
     */
    public function ajaxRemoveAction(): void
    {
        $result = ['success' => false, 'message' => ''];

        if (!$this->getRequest()->isPost()) {
            $result['message'] = $this->__('Invalid request.');
            $this->_sendJsonResponse($result);
            return;
        }

        $code = $this->getRequest()->getPost('code');

        if (!$code) {
            $result['message'] = $this->__('Invalid gift card code.');
            $this->_sendJsonResponse($result);
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

                if ($appliedCodes === []) {
                    $quote->setGiftcardCodes(null);
                    $quote->setGiftcardAmount(0);
                    $quote->setBaseGiftcardAmount(0);
                } else {
                    $quote->setGiftcardCodes(json_encode($appliedCodes));
                }

                $quote->collectTotals()->save();

                $result['success'] = true;
                $result['message'] = $this->__('Gift card was removed.');
                $result['data'] = $this->_getGiftcardData($quote);
            } else {
                $result['message'] = $this->__('Gift card not found.');
            }

        } catch (Exception $e) {
            $result['message'] = $this->__('Cannot remove gift card.');
        }

        $this->_sendJsonResponse($result);
    }

    /**
     * Get gift card data for JSON response
     */
    protected function _getGiftcardData(Mage_Sales_Model_Quote $quote): array
    {
        $appliedCodes = $quote->getGiftcardCodes();
        $giftcards = [];

        if ($appliedCodes) {
            $codes = json_decode($appliedCodes, true);
            if (is_array($codes)) {
                $quoteCurrency = $quote->getQuoteCurrencyCode();
                foreach ($codes as $code => $amount) {
                    $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);
                    $displayCode = strlen($code) > 10
                        ? substr($code, 0, 4) . '...' . substr($code, -4)
                        : $code;

                    $giftcards[] = [
                        'code' => $code,
                        'display_code' => $displayCode,
                        'amount' => (float) $amount,
                        'amount_formatted' => Mage::helper('core')->currency($amount, true, false),
                        'balance' => $giftcard->getId() ? $giftcard->getBalance($quoteCurrency) : 0,
                    ];
                }
            }
        }

        $grandTotal = (float) $quote->getGrandTotal();
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $isFullyCovered = $giftcardAmount > 0 && $grandTotal <= 0.01;

        return [
            'giftcards' => $giftcards,
            'total_giftcard_amount' => $giftcardAmount,
            'total_giftcard_amount_formatted' => Mage::helper('core')->currency($giftcardAmount, true, false),
            'amount_due' => max(0, $grandTotal),
            'amount_due_formatted' => Mage::helper('core')->currency(max(0, $grandTotal), true, false),
            'subtotal_before_giftcard' => $grandTotal + $giftcardAmount,
            'subtotal_before_giftcard_formatted' => Mage::helper('core')->currency($grandTotal + $giftcardAmount, true, false),
            'is_fully_covered' => $isFullyCovered,
        ];
    }

    /**
     * Send JSON response
     */
    protected function _sendJsonResponse(array $data): void
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json', true)
            ->setBody(Mage::helper('core')->jsonEncode($data));
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
