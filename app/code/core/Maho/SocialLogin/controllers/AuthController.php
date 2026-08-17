<?php

/**
 * JSON endpoints for the storefront social sign-in flow.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_AuthController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/sociallogin/auth/nonce', name: 'sociallogin.auth.nonce', methods: ['POST'])]
    public function nonceAction(): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        $this->getResponse()->setBodyJson(['nonce' => Mage::getModel('sociallogin/nonce')->issue()]);
    }

    #[Maho\Config\Route('/sociallogin/auth/login', name: 'sociallogin.auth.login', methods: ['POST'])]
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
        $provider = (string) $request->getPost('provider');
        $token = (string) $request->getPost('token');

        try {
            $expectedNonce = null;
            if ($provider !== 'facebook') {
                // Google and Apple ID tokens must echo a nonce this session issued
                $expectedNonce = (string) $request->getPost('nonce');
                if (!Mage::getModel('sociallogin/nonce')->consume($expectedNonce)) {
                    Mage::throwException($this->__('Your sign-in attempt has expired. Please try again.'));
                }
            }

            $result = Mage::getModel('sociallogin/service')->authenticate(
                $provider,
                $token,
                (int) Mage::app()->getStore()->getId(),
                $expectedNonce,
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
            // The registration form was skipped, so admin-required profile
            // fields can be empty; send the customer to fill them in.
            if (Mage::helper('sociallogin')->hasIncompleteRequiredProfile($customer)) {
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
        $helper = Mage::helper('sociallogin');
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')
            || !$helper->isAnyProviderEnabled()
        ) {
            $this->jsonError($this->__('This sign-in method is not available.'), 404);
            return false;
        }
        if (!$this->_validateFormKey()) {
            $this->jsonError($this->__('Invalid form key. Please refresh the page and try again.'), 403);
            return false;
        }
        if ($helper->isIpRateLimited()) {
            $this->jsonError($this->__('Too many attempts. Please try again later.'), 429);
            return false;
        }
        return true;
    }

    /**
     * Accepts only same-site relative paths so a tampered redirect value
     * cannot leave the store.
     */
    protected function resolveRedirect(): string
    {
        $redirect = (string) $this->getRequest()->getPost('redirect');
        if ($redirect !== ''
            && str_starts_with($redirect, '/')
            && !str_starts_with($redirect, '//')
            && !str_contains($redirect, '\\')
            && !str_contains($redirect, '://')
        ) {
            return $redirect;
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
