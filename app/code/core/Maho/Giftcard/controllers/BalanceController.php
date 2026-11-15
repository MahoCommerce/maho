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
     * The service gives one error for an unknown, expired, disabled or foreign card, so that live codes cannot be found.
     */
    #[Maho\Config\Route('/giftcard/balance/check', methods: ['POST'])]
    public function checkAction(): void
    {
        $session = Mage::getSingleton('giftcard/session');
        $session->setLastGiftcardLookup(null);

        try {
            $card = Mage::getService('giftcard/giftcard')->checkBalance(
                (string) $this->getRequest()->getPost('giftcard_code', ''),
                (int) Mage::app()->getStore()->getWebsiteId(),
                (int) Mage::getSingleton('customer/session')->getCustomerId(),
            );
            $session->setLastGiftcardLookup([
                'code'          => $card->getCode(),
                'balance'       => (float) $card->getBalance(),
                'currency_code' => (string) $card->getCurrencyCode(),
                'expires_at'    => $card->getExpiresAt(),
            ]);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('customer/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }
}
