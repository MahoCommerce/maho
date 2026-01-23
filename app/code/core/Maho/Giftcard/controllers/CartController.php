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
     * Rate limit: max attempts per window
     */
    protected const RATE_LIMIT_MAX_ATTEMPTS = 10;

    /**
     * Rate limit: window in seconds
     */
    protected const RATE_LIMIT_WINDOW = 60;

    /**
     * Validate form key for POST requests
     */
    #[\Override]
    public function preDispatch(): static
    {
        parent::preDispatch();

        $action = strtolower($this->getRequest()->getActionName());
        $postActions = ['apply', 'remove', 'ajaxapply', 'ajaxremove', 'checkbalance'];

        if (in_array($action, $postActions)) {
            if (!$this->getRequest()->isPost()) {
                $this->setFlag('', self::FLAG_NO_DISPATCH, true);
                $this->_redirect('checkout/cart');
                return $this;
            }

            if (!$this->_validateFormKey()) {
                $this->setFlag('', self::FLAG_NO_DISPATCH, true);
                Mage::getSingleton('checkout/session')->addError(
                    $this->__('Invalid form key. Please refresh the page and try again.'),
                );
                $this->_redirect('checkout/cart');
                return $this;
            }
        }

        return $this;
    }

    /**
     * Check if rate limited (session-based bucket)
     */
    protected function _isRateLimited(): bool
    {
        $session = Mage::getSingleton('core/session');
        $attempts = (array) $session->getGiftcardCheckAttempts();
        $now = time();

        // Clean old attempts outside the window
        $attempts = array_filter($attempts, fn($timestamp) => ($now - $timestamp) < self::RATE_LIMIT_WINDOW);

        if (count($attempts) >= self::RATE_LIMIT_MAX_ATTEMPTS) {
            return true;
        }

        // Record this attempt
        $attempts[] = $now;
        $session->setGiftcardCheckAttempts($attempts);

        return false;
    }

    /**
     * Check gift card balance (AJAX)
     */
    public function checkBalanceAction(): void
    {
        $result = ['success' => false, 'message' => ''];

        // Rate limiting
        if ($this->_isRateLimited()) {
            $result['message'] = $this->__('Too many attempts. Please wait a moment and try again.');
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
            // Load gift card by code
            $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

            // Don't leak info - return same message for non-existent, wrong website, or inactive cards
            if (!$giftcard->getId()) {
                $result['message'] = $this->__('Gift card not found.');
                $this->_sendJsonResponse($result);
                return;
            }

            $websiteId = (int) Mage::app()->getStore()->getWebsiteId();
            if ((int) $giftcard->getWebsiteId() !== $websiteId) {
                $result['message'] = $this->__('Gift card not found.');
                $this->_sendJsonResponse($result);
                return;
            }

            // Only show balance for valid (active) gift cards
            if (!$giftcard->isValid()) {
                $result['message'] = $this->__('Gift card not found.');
                $this->_sendJsonResponse($result);
                return;
            }

            // Get store currency for display
            $storeCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            $balance = $giftcard->getBalance($storeCurrencyCode);
            $balanceFormatted = Mage::app()->getStore()->formatPrice($balance, false);

            $result['success'] = true;
            $result['message'] = $this->__('Balance: %s', $balanceFormatted);
            $result['data'] = [
                'balance' => $balance,
                'balance_formatted' => $balanceFormatted,
            ];

        } catch (Exception $e) {
            Mage::logException($e);
            $result['message'] = $this->__('Unable to check balance.');
        }

        $this->_sendJsonResponse($result);
    }

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

            // Check website validity
            $websiteId = (int) $quote->getStore()->getWebsiteId();
            if ((int) $giftcard->getWebsiteId() !== $websiteId) {
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
            // Store gift card with balance in quote's base currency for proper calculation
            // The Total collector will determine actual amount to apply
            $quoteBaseCurrency = $quote->getBaseCurrencyCode();
            $appliedCodes[$code] = $giftcard->getBalance($quoteBaseCurrency);

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
        $code = $this->getRequest()->getPost('code');

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

            // Check website validity
            $websiteId = (int) $quote->getStore()->getWebsiteId();
            if ((int) $giftcard->getWebsiteId() !== $websiteId) {
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

            // Store gift card with balance in quote's base currency for proper calculation
            // The Total collector will determine actual amount to apply
            $quoteBaseCurrency = $quote->getBaseCurrencyCode();
            $appliedCodes[$code] = $giftcard->getBalance($quoteBaseCurrency);

            $quote->setGiftcardCodes(json_encode($appliedCodes));
            $quote->collectTotals()->save();

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

                // Include updated payment methods HTML if this is a checkout context
                // The payment methods need to be refreshed when removing gift cards too
                if ($this->getRequest()->getParam('checkout') === '1') {
                    $result['payment_methods_html'] = $this->_getPaymentMethodsHtml($quote);
                }
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
                $store = $quote->getStore();

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
                        'balance' => $giftcard->getId() ? $giftcard->getBalance($quoteCurrency) : 0,
                    ];
                }
            }
        }

        $grandTotal = (float) $quote->getGrandTotal();
        $giftcardAmount = abs((float) $quote->getGiftcardAmount());
        $store = $quote->getStore();
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
