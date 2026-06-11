<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

class Mage_Oauth_Model_Observer
{
    /**
     * Retrieve oauth_token param from request
     *
     * @return string|null
     */
    protected function _getOauthToken()
    {
        return Mage::helper('oauth')->getOauthToken();
    }

    /**
     * Redirect admin to authorize controller after login success
     */
    #[Maho\Config\Observer('admin_session_user_login_success')]
    public function afterAdminLogin(\Maho\Event\Observer $observer)
    {
        if ($this->_getOauthToken() !== null) {
            $url = Mage::helper('oauth')->getAuthorizeUrl();
            Mage::app()->getResponse()
                ->setRedirect($url)
                ->sendResponse();
            exit();
        }
    }

    /**
     * Redirect admin to authorize controller after login fail
     */
    #[Maho\Config\Observer('admin_session_user_login_failed')]
    public function afterAdminLoginFailed(\Maho\Event\Observer $observer)
    {
        if ($this->_getOauthToken() !== null) {
            /** @var Mage_Admin_Model_Session $session */
            $session = Mage::getSingleton('admin/session');
            $session->addError($observer->getException()->getMessage());

            $url = Mage::helper('oauth')->getAuthorizeUrl();
            Mage::app()->getResponse()
                ->setRedirect($url)
                ->sendResponse();
            exit();
        }
    }
}
