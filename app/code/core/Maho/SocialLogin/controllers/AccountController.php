<?php

/**
 * Customer-facing "Connected Accounts" page under My Account.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_AccountController extends Mage_Core_Controller_Front_Action
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

    #[Maho\Config\Route('/sociallogin/account', name: 'sociallogin.account', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->getLayout()->getBlock('head')?->setTitle($this->__('Connected Accounts'));
        $this->renderLayout();
    }

    /**
     * Unlinking is always allowed: Magic Link and password reset keep the
     * account recoverable by email even when no provider remains linked.
     */
    #[Maho\Config\Route('/sociallogin/account/unlink', name: 'sociallogin.account.unlink', methods: ['POST'])]
    public function unlinkAction(): void
    {
        $session = Mage::getSingleton('customer/session');
        if (!$this->_validateFormKey()) {
            $this->_redirect('*/*/');
            return;
        }

        $unlinked = Mage::getModel('sociallogin/service')->unlink(
            (int) $session->getCustomerId(),
            (int) $this->getRequest()->getPost('identity_id'),
        );
        if ($unlinked) {
            $session->addSuccess($this->__('The sign-in provider has been unlinked.'));
        } else {
            $session->addError($this->__('The sign-in provider could not be unlinked.'));
        }
        $this->_redirect('*/*/');
    }
}
