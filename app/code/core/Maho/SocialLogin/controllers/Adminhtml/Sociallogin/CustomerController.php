<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Adminhtml_Sociallogin_CustomerController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'customer/manage';

    #[Maho\Config\Route('/admin/sociallogin_customer/unlink', methods: ['POST'])]
    public function unlinkAction(): void
    {
        $customerId = (int) $this->getRequest()->getPost('id');
        $unlinked = Mage::getModel('sociallogin/service')->unlink(
            $customerId,
            (int) $this->getRequest()->getPost('identity_id'),
        );
        $session = Mage::getSingleton('adminhtml/session');
        if ($unlinked) {
            $session->addSuccess($this->__('The sign-in provider has been unlinked.'));
        } else {
            $session->addError($this->__('The sign-in provider could not be unlinked.'));
        }
        $this->_redirect('*/customer/edit', ['id' => $customerId, 'tab' => 'customer_info_tabs_social_login']);
    }
}
