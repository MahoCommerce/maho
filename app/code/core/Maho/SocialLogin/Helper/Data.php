<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_GOOGLE_ENABLED = 'customer/social_login/google_enabled';
    public const XML_PATH_GOOGLE_CLIENT_ID = 'customer/social_login/google_client_id';
    public const XML_PATH_GOOGLE_ONE_TAP_ENABLED = 'customer/social_login/google_one_tap_enabled';
    public const XML_PATH_APPLE_ENABLED = 'customer/social_login/apple_enabled';
    public const XML_PATH_APPLE_SERVICE_ID = 'customer/social_login/apple_service_id';
    public const XML_PATH_FACEBOOK_ENABLED = 'customer/social_login/facebook_enabled';
    public const XML_PATH_FACEBOOK_APP_ID = 'customer/social_login/facebook_app_id';
    public const XML_PATH_FACEBOOK_APP_SECRET = 'customer/social_login/facebook_app_secret';
    public const XML_PATH_ALLOW_REGISTRATION = 'customer/social_login/allow_registration';
    public const XML_PATH_IP_RATE_LIMIT = 'customer/social_login/ip_rate_limit_per_hour';
    public const XML_PATH_NONCE_TTL = 'customer/social_login/nonce_ttl';

    protected $_moduleName = 'Maho_SocialLogin';

    public function isGoogleEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_GOOGLE_ENABLED, $storeId)
            && $this->getGoogleClientId($storeId) !== '';
    }

    public function getGoogleClientId(?int $storeId = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_GOOGLE_CLIENT_ID, $storeId));
    }

    public function isGoogleOneTapEnabled(?int $storeId = null): bool
    {
        return $this->isGoogleEnabled($storeId)
            && Mage::getStoreConfigFlag(self::XML_PATH_GOOGLE_ONE_TAP_ENABLED, $storeId);
    }

    public function isAppleEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_APPLE_ENABLED, $storeId)
            && $this->getAppleServiceId($storeId) !== '';
    }

    public function getAppleServiceId(?int $storeId = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_APPLE_SERVICE_ID, $storeId));
    }

    public function isFacebookEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_FACEBOOK_ENABLED, $storeId)
            && $this->getFacebookAppId($storeId) !== ''
            && $this->getFacebookAppSecret($storeId) !== '';
    }

    public function getFacebookAppId(?int $storeId = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_FACEBOOK_APP_ID, $storeId));
    }

    /**
     * The encrypted backend_model decrypts on read; do not decrypt() the value again.
     */
    public function getFacebookAppSecret(?int $storeId = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_FACEBOOK_APP_SECRET, $storeId));
    }

    public function isAnyProviderEnabled(?int $storeId = null): bool
    {
        return $this->isGoogleEnabled($storeId)
            || $this->isAppleEnabled($storeId)
            || $this->isFacebookEnabled($storeId);
    }

    /**
     * @return array<int, array<string, string|bool>>
     */
    public function getEnabledProviders(?int $storeId = null): array
    {
        $providers = [];
        if ($this->isGoogleEnabled($storeId)) {
            $providers[] = [
                'code' => 'google',
                'clientId' => $this->getGoogleClientId($storeId),
                'oneTap' => $this->isGoogleOneTapEnabled($storeId),
            ];
        }
        if ($this->isAppleEnabled($storeId)) {
            $providers[] = [
                'code' => 'apple',
                'serviceId' => $this->getAppleServiceId($storeId),
            ];
        }
        if ($this->isFacebookEnabled($storeId)) {
            $providers[] = [
                'code' => 'facebook',
                'appId' => $this->getFacebookAppId($storeId),
            ];
        }
        return $providers;
    }

    public function isRegistrationAllowedViaSocial(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ALLOW_REGISTRATION, $storeId);
    }

    public function getNonceTtl(?int $storeId = null): int
    {
        return max(60, (int) Mage::getStoreConfig(self::XML_PATH_NONCE_TTL, $storeId));
    }

    /**
     * True when a required attribute of the registration form is still empty on
     * the customer. Social sign-up skips the registration form, so these fields
     * can be missing right after the account is created. Custom required
     * customer attributes are covered because the list comes from the
     * customer_account_create EAV form, not from a hard-coded set.
     */
    public function hasIncompleteRequiredProfile(Mage_Customer_Model_Customer $customer): bool
    {
        // Attributes social sign-up always fills itself
        $filledBySocialSignup = ['firstname', 'lastname', 'email'];
        $form = Mage::getModel('customer/form')
            ->setFormCode('customer_account_create')
            ->setEntity($customer);
        foreach ($form->getAttributes() as $code => $attribute) {
            if (in_array($code, $filledBySocialSignup, true) || !$attribute->getIsVisible()) {
                continue;
            }
            if ($attribute->getIsRequired() && trim((string) $customer->getData($code)) === '') {
                return true;
            }
        }
        return false;
    }

    public function getProviderLabel(string $code): string
    {
        return match ($code) {
            'google' => 'Google',
            'apple' => 'Apple',
            'facebook' => 'Facebook',
            default => ucfirst($code),
        };
    }

    /**
     * Failed-attempt limiter: check tooManyAttempts() up front, hit() only on a
     * failed login, so page views and successful sign-ins keep the budget intact.
     */
    public function getIpRateLimiter(?int $storeId = null): \Maho\Security\RateLimiter
    {
        return Mage::helper('core')->rateLimiter(
            'sociallogin_auth',
            (int) Mage::getStoreConfig(self::XML_PATH_IP_RATE_LIMIT, $storeId),
            3600,
            \Maho\Security\RateLimitScope::Ip,
        );
    }
}
