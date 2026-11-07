<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Gift Card cart controller
 */
class Maho_Giftcard_CartController extends Mage_Core_Controller_Front_Action
{
    /**
     * Check gift card balance (AJAX)
     */
    #[Maho\Config\Route('/giftcard/cart/checkBalance', methods: ['POST'])]
    public function checkBalanceAction(): void
    {
        $result = ['success' => false];
        try {
            $store = Mage::app()->getStore();
            $giftcard = Mage::getService('giftcard/giftcard')->checkBalance(
                (string) $this->getRequest()->getPost('giftcard_code'),
                (int) $store->getWebsiteId(),
                (int) Mage::getSingleton('customer/session')->getCustomerId() ?: null,
            );
            $balance = $giftcard->getBalance($store->getCurrentCurrencyCode());
            $balanceFormatted = $store->formatPrice($balance, false);

            $result['success'] = true;
            $result['message'] = $this->__('Balance: %s', $balanceFormatted);
            $result['data'] = [
                'balance' => $balance,
                'balance_formatted' => $balanceFormatted,
            ];
        } catch (Mage_Core_Exception $e) {
            $result['message'] = $e->getMessage();
        } catch (Exception $e) {
            Mage::logException($e);
            $result['message'] = $this->__('Unable to check balance.');
        }

        $this->_sendJsonResponse($result);
    }

    /**
     * Apply gift card to cart
     */
    #[Maho\Config\Route('/giftcard/cart/apply', methods: ['POST'])]
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
            Mage::getService('checkout/cart')->applyGiftcard($this->_getQuote(), $code);

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
    #[Maho\Config\Route('/giftcard/cart/remove', methods: ['POST'])]
    public function removeAction(): void
    {
        $code = $this->getRequest()->getPost('code');

        if (!$code) {
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            Mage::getService('checkout/cart')->removeGiftcard($this->_getQuote(), (string) $code);
            Mage::getSingleton('checkout/session')->addSuccess($this->__('Gift card was removed.'));
        } catch (Mage_Core_Exception_NoSuchEntity) {
            // The card is not in the cart, so there is nothing to remove
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('checkout/session')->addError($this->__('Cannot remove gift card.'));
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * AJAX apply gift card (for checkout payment step)
     */
    #[Maho\Config\Route('/giftcard/cart/ajaxApply', methods: ['POST'])]
    public function ajaxApplyAction(): void
    {
        $result = ['success' => false, 'html' => ''];

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
            Mage::getService('checkout/cart')->applyGiftcard($quote, $code);

            $result['success'] = true;
            $result['message'] = $this->__('Gift card "%s" was applied.', $code);
            $result['data'] = $this->_getGiftcardData($quote);

            // Include updated payment methods HTML if this is a checkout context
            // and gift cards fully cover the order
            if ($result['data']['is_fully_covered'] && $this->getRequest()->getParam('checkout') === '1') {
                $result['payment_methods_html'] = $this->_getPaymentMethodsHtml($quote);
            }

        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }

        $this->_sendJsonResponse($result);
    }

    /**
     * AJAX remove gift card (for checkout payment step)
     */
    #[Maho\Config\Route('/giftcard/cart/ajaxRemove', methods: ['POST'])]
    public function ajaxRemoveAction(): void
    {
        $result = ['success' => false];

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
            Mage::getService('checkout/cart')->removeGiftcard($quote, (string) $code);

            $result['success'] = true;
            $result['message'] = $this->__('Gift card was removed.');
            $result['data'] = $this->_getGiftcardData($quote);

            // The payment methods change when the gift cards no longer cover the order
            if ($this->getRequest()->getParam('checkout') === '1') {
                $result['payment_methods_html'] = $this->_getPaymentMethodsHtml($quote);
            }
        } catch (Mage_Core_Exception_NoSuchEntity) {
            $result['message'] = $this->__('Gift card not found.');
        } catch (Exception $e) {
            Mage::logException($e);
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
        $store = $quote->getStore();

        if ($appliedCodes) {
            $codes = json_decode($appliedCodes, true);
            if (is_array($codes)) {
                // The amounts below are in display currency, so the balance has
                // to be too, not in the code stamped on the quote row.
                $displayCurrency = $store->getCurrentCurrencyCode();

                // Get the total display amount already calculated by the totals collector
                // This is already converted to display currency
                $totalDisplayAmount = abs((float) $quote->getGiftcardAmount());
                $totalBaseAmount = abs((float) $quote->getBaseGiftcardAmount());

                // Calculate the ratio to distribute display amounts proportionally
                $ratio = $totalBaseAmount > 0 ? $totalDisplayAmount / $totalBaseAmount : 0;

                foreach ($codes as $code => $baseAmount) {
                    $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);
                    $displayCode = Mage::helper('giftcard')->maskCode($code);

                    // Use the ratio to calculate display amount (avoids re-conversion)
                    $displayAmount = (float) $baseAmount * $ratio;

                    $giftcards[] = [
                        'code' => $code,
                        'display_code' => $displayCode,
                        'amount' => $displayAmount,
                        // Use formatPrice instead of currency() - amount is already in display currency
                        'amount_formatted' => $store->formatPrice($displayAmount, false),
                        'balance' => $giftcard->getId() ? $giftcard->getBalance($displayCurrency) : 0,
                    ];
                }
            }
        }

        $grandTotal = (float) $quote->getGrandTotal();
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $isFullyCovered = $giftcardAmount > 0 && $grandTotal <= 0.01;

        // Use formatPrice instead of currency() - amounts are already in display currency
        return [
            'giftcards' => $giftcards,
            'total_giftcard_amount' => $giftcardAmount,
            'total_giftcard_amount_formatted' => $store->formatPrice($giftcardAmount, false),
            'amount_due' => max(0, $grandTotal),
            'amount_due_formatted' => $store->formatPrice(max(0, $grandTotal), false),
            'subtotal_before_giftcard' => $grandTotal + $giftcardAmount,
            'subtotal_before_giftcard_formatted' => $store->formatPrice($grandTotal + $giftcardAmount, false),
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

    /**
     * Get payment methods HTML for checkout
     */
    protected function _getPaymentMethodsHtml(Mage_Sales_Model_Quote $quote): string
    {
        $this->loadLayout('checkout_onepage_paymentmethod');
        $block = $this->getLayout()->getBlock('root');
        if ($block) {
            $block->setQuote($quote);
            return $block->toHtml();
        }
        return '';
    }
}
