<?php

/**
 * JSON endpoints for the storefront social sign-in flow.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_SocialController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/customer/social/nonce', name: 'customer.social.nonce', methods: ['POST'])]
    public function nonceAction(): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        $this->getResponse()->setBodyJson(['nonce' => Mage::getModel('sociallogin/nonce')->issue()]);
    }

    #[Maho\Config\Route('/customer/social/login', name: 'customer.social.login', methods: ['POST'])]
    public function loginAction(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $session = Mage::getSingleton('customer/session');
        if ($session->isLoggedIn()) {
            $this->getResponse()->setBodyJson(['success' => true, 'redirect' => Mage::getUrl('customer/account')]);
            return;
        }

        $request = $this->getRequest();
        // An ID token must echo a nonce that this session issued
        $nonce = (string) $request->getPost('nonce');

        try {
            $result = $this->getService()->authenticate(
                (string) $request->getPost('provider'),
                (string) $request->getPost('token'),
                (int) Mage::app()->getStore()->getId(),
                Maho_SocialLogin_Model_Channel::Web,
                Mage::getModel('sociallogin/nonce')->consume($nonce) ? $nonce : null,
                [
                    'firstname' => (string) $request->getPost('firstname'),
                    'lastname' => (string) $request->getPost('lastname'),
                ],
            );
        } catch (Mage_Core_Exception $e) {
            $this->jsonError($e->getMessage(), 400);
            return;
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->jsonError($this->__('Sign-in failed. Please try again later.'), 500);
            return;
        }

        $customer = $result['customer'];
        // Same shape as magicLinkLoginAction: identity is proven, but customers who
        // opted into 2FA must still clear the challenge before the session logs in.
        // loginById() is unusable here because it refuses 2FA-enabled customers.
        if ($session->shouldChallengeTwofa($customer)) {
            $session->startTwofaChallenge($customer);
            $this->getResponse()->setBodyJson([
                'success' => true,
                'redirect' => Mage::getUrl('customer/account/twofaChallenge'),
            ]);
            return;
        }

        $session->setCustomerAsLoggedIn($customer);
        $redirect = $this->resolveRedirect();
        if ($result['is_new']) {
            $session->addSuccess($this->__('Your account has been created.'));
            if ($result['profile_incomplete']) {
                $session->addNotice($this->__('Please complete the required fields of your profile.'));
                $redirect = Mage::getUrl('customer/account/edit');
            }
        }

        $this->getResponse()->setBodyJson([
            'success' => true,
            'redirect' => $redirect,
        ]);
    }

    /**
     * Shared gates for both endpoints; writes the error response when blocked.
     */
    protected function isAvailable(): bool
    {
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')
            || !Mage::helper('sociallogin')->isAnyProviderEnabled()
        ) {
            $this->jsonError($this->__('This sign-in method is not available.'), 404);
            return false;
        }
        if ($this->getService()->isRateLimited((int) Mage::app()->getStore()->getId())) {
            $this->jsonError($this->__('Too many requests. Please try again later.'), 429);
            return false;
        }
        return true;
    }

    protected function getService(): Maho_SocialLogin_Service_Identity
    {
        return Mage::getService('sociallogin/identity');
    }

    /**
     * Same mechanism as the regular login flow: the login form block (and the
     * checkout, wishlist, etc. controllers) store the page to return to in the
     * session as the before-auth URL. The URL was set server-side, so only the
     * pointless targets (base, login, logout) are filtered out.
     */
    protected function resolveRedirect(): string
    {
        $helper = Mage::helper('customer');
        $beforeAuthUrl = Mage::getSingleton('customer/session')->getBeforeAuthUrl(true);
        if ($beforeAuthUrl !== ''
            && $beforeAuthUrl !== Mage::getBaseUrl()
            && $beforeAuthUrl !== $helper->getLoginUrl()
            && $beforeAuthUrl !== $helper->getLogoutUrl()
            && $this->_isUrlInternal($beforeAuthUrl)
        ) {
            return $beforeAuthUrl;
        }
        return Mage::getUrl('customer/account');
    }

    protected function jsonError(string $message, int $httpCode): void
    {
        $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setBodyJson(['error' => true, 'message' => $message]);
    }
}
