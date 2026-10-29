<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

/**
 * @deprecated since 26.9 Use Maho_ApiPlatform instead.
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
     * Revoke the tokens of an admin user that a save deactivates. Without this, a reactivation
     * of the account brings the old tokens back. The OAuth tokens grid can enable them again.
     */
    #[Maho\Config\Observer('admin_user_save_after')]
    public function revokeTokensOfDeactivatedAdmin(\Maho\Event\Observer $observer): void
    {
        $user = $observer->getEvent()->getObject();
        if (!$user instanceof Mage_Admin_Model_User || !$user->getId() || $user->getIsActive() !== false) {
            return;
        }

        /** @var Mage_Oauth_Model_Resource_Token $resource */
        $resource = Mage::getResourceSingleton('oauth/token');
        $resource->revokeAdminTokens((int) $user->getId());
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
