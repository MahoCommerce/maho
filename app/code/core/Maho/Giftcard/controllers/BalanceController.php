<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Customer-facing "Check Gift Card Balance" page.
 *
 * Lives under My Account (authentication enforced in preDispatch); the
 * customer enters a gift card code and sees the remaining balance + expiry,
 * scoped to the websites this card is valid on. No transaction-history view
 * — that's intentionally admin-only for traceability without exposing
 * internal balance-adjustment comments to the customer.
 */
class Maho_Giftcard_BalanceController extends Mage_Core_Controller_Front_Action
{
    /**
     * Force-login the customer; matches the behaviour of every other
     * customer/account/* action so the page slots into the My Account menu
     * without surprising visitors who follow a deep link while signed out.
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->getRequest()->isDispatched()) {
            return $this;
        }

        $action = strtolower((string) $this->getRequest()->getActionName());
        if (in_array($action, ['index', 'check'], true)
            && !Mage::getSingleton('customer/session')->authenticate($this)
        ) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }

        return $this;
    }

    /**
     * Render the lookup form. The result of the most recent POST (if any)
     * is read out of customer/session by the block — using session storage
     * rather than passing through query params keeps a previously-checked
     * code from leaking into browser history / referer logs.
     */
    #[Maho\Config\Route('/giftcard/balance', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('giftcard/session');
        $this->renderLayout();
    }

    /**
     * Look up a posted code. Treats "not found", "expired", "disabled" and
     * "no website membership" as the same opaque "could not be found"
     * outcome so this endpoint can't be used to enumerate which codes are
     * live — a customer who genuinely owns a code will see it; an attacker
     * walking codes can't distinguish "expired" from "doesn't exist".
     *
     * Rate-limited to 10 failed attempts per hour per customer via the
     * shared Maho\Security\RateLimiter (`Mage_Core_Helper_Data::rateLimiterBy`).
     * "Check upfront, hit only on failure" pattern so a customer with
     * several legitimate cards isn't penalised for genuine lookups.
     */
    #[Maho\Config\Route('/giftcard/balance/check', methods: ['POST'])]
    public function checkAction(): void
    {
        $session = Mage::getSingleton('giftcard/session');
        $session->setLastGiftcardLookup(null);

        $customerId = (string) Mage::getSingleton('customer/session')->getCustomerId();
        $limiter = Mage::helper('core')->rateLimiterBy('giftcard_balance_check', $customerId, 10, 3600);
        if ($limiter->tooManyAttempts()) {
            $session->addError(Mage::helper('giftcard')->__('Too many recent lookup attempts. Please wait a while before trying again.'));
            $this->_redirect('*/*/');
            return;
        }

        $code = trim((string) $this->getRequest()->getPost('giftcard_code', ''));
        if ($code === '') {
            $session->addError(Mage::helper('giftcard')->__('Please enter a gift card code.'));
            $this->_redirect('*/*/');
            return;
        }

        /** @var Maho_Giftcard_Model_Giftcard $card */
        $card = Mage::getModel('giftcard/giftcard');
        /** @var Maho_Giftcard_Model_Resource_Giftcard $resource */
        $resource = $card->getResource();
        $resource->loadByCode($card, $code);

        $websiteId = (int) Mage::app()->getStore()->getWebsiteId();

        if (!$card->getId() || !$card->isValidForWebsite($websiteId)) {
            $limiter->hit();
            $session->addError(Mage::helper('giftcard')->__('We could not find an active gift card for that code on this store.'));
            $this->_redirect('*/*/');
            return;
        }

        $session->setLastGiftcardLookup([
            'code'          => $card->getCode(),
            'balance'       => (float) $card->getBalance(),
            'currency_code' => (string) $card->getCurrencyCode(),
            'expires_at'    => $card->getExpiresAt(),
        ]);

        $this->_redirect('*/*/');
    }
}
