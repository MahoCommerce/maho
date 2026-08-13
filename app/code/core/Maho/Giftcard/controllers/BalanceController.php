<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Customer-facing "Check Gift Card Balance" page under My Account.
 * No transaction-history view: that is deliberately admin-only.
 */
class Maho_Giftcard_BalanceController extends Mage_Core_Controller_Front_Action
{
    /**
     * Force-login, same as every other customer/account/* action.
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->getRequest()->isDispatched()) {
            return $this;
        }

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }

        return $this;
    }

    /**
     * The lookup result travels via giftcard/session, not query params, so
     * a checked code never leaks into browser history or referer logs.
     */
    #[Maho\Config\Route('/giftcard/balance', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    /**
     * "Not found", "expired", "disabled" and "wrong website" all return the
     * same opaque error so live codes cannot be enumerated. Rate-limited per
     * customer; only failed lookups count against the limit.
     */
    #[Maho\Config\Route('/giftcard/balance/check', methods: ['POST'])]
    public function checkAction(): void
    {
        // Explicit form-key check so a cross-site POST cannot burn the victim's rate-limit slots
        if (!$this->_validateFormKey()) {
            $this->_redirect('*/*/');
            return;
        }

        $session = Mage::getSingleton('giftcard/session');
        $session->setLastGiftcardLookup(null);
        $flash = Mage::getSingleton('customer/session');

        $customerId = (string) Mage::getSingleton('customer/session')->getCustomerId();
        $limiter = Mage::helper('core')->rateLimiterBy('giftcard_balance_check', $customerId, 10, 3600);
        if ($limiter->tooManyAttempts()) {
            $flash->addError(Mage::helper('giftcard')->__('Too many recent lookup attempts. Please wait a while before trying again.'));
            $this->_redirect('*/*/');
            return;
        }

        $code = trim((string) $this->getRequest()->getPost('giftcard_code', ''));
        if ($code === '') {
            $flash->addError(Mage::helper('giftcard')->__('Please enter a gift card code.'));
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
            $flash->addError(Mage::helper('giftcard')->__('We could not find an active gift card for that code on this store.'));
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
