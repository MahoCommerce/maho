<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Maho\Security\RateLimitScope;
use Mage_Customer_Exception_SecondFactorRequired as SecondFactorRequired;
use Mage_Customer_Model_Customer as Customer;

/**
 * Prove who a customer is, for the storefront, the API and a social sign-in: the login with a password, the second
 * factor, the magic login link, the account confirmation and the password reset.
 *
 * Each method that returns a customer to log in applies the second factor rule. A customer who uses 2FA and gives
 * no code gets Mage_Customer_Exception_SecondFactorRequired, and a wrong code gets Mage_Core_Exception_Unauthorized
 * with the error code twofa_invalid.
 */
class Mage_Customer_Service_Authentication
{
    public const XML_PATH_LOGIN_IP_RATE_LIMIT = 'system/rate_limit/auth_token_ip';
    public const XML_PATH_LOGIN_RATE_LIMIT = 'system/rate_limit/customer_login';
    public const XML_PATH_MAGIC_LINK_EMAIL_RATE_LIMIT = 'system/rate_limit/magic_link_email';
    public const XML_PATH_MAGIC_LINK_IP_RATE_LIMIT = 'system/rate_limit/magic_link_ip';
    /** Counts only a rejected token: a password reset link, an account confirmation key or a magic login link */
    public const XML_PATH_TOKEN_RATE_LIMIT = 'system/rate_limit/reset_password';
    public const XML_PATH_CONFIRMATION_LINK_RATE_LIMIT = 'system/rate_limit/confirmation_link';

    public const XML_PATH_ALLOW_2FA = 'customer/password/allow_2fa';
    public const XML_PATH_REQUIRE_2FA = 'customer/password/require_2fa';

    public const RATE_LIMIT_WINDOW = 3600;
    public const LOGIN_RATE_LIMIT_WINDOW = 60;

    public const ERROR_INVALID_CREDENTIALS = 'invalid_credentials';
    public const ERROR_TWOFA_REQUIRED = SecondFactorRequired::ERROR_CODE;
    public const ERROR_TWOFA_INVALID = 'twofa_invalid';

    /**
     * Check the email and the password of a customer of the website $websiteId, with the 2FA code when the customer
     * uses 2FA. A login that stops at the request for the 2FA code does not count in the login limits: the login
     * with the code counts.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, when the account is
     *                                        not active, or when it is not confirmed (code EXCEPTION_EMAIL_NOT_CONFIRMED)
     * @throws Mage_Core_Exception_Unauthorized with the error code invalid_credentials for a wrong email or password,
     *                                           twofa_required for a missing 2FA code and twofa_invalid for a wrong one
     */
    public function authenticate(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        int $websiteId,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): Customer {
        $this->getCustomerService()->assertAccountsEnabled();
        return $this->countLogin($email, fn(): Customer => $this->authenticateInWebsite($email, $password, $websiteId, $twofaCode));
    }

    /**
     * Check the email and the password as authenticate() does, in the website $websiteId first and then in each other
     * website, for a client that does not name its store. A missing or wrong 2FA code stops the search: the password
     * was right.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when no website
     *                                        accepts the login and an account is not confirmed or not active
     * @throws Mage_Core_Exception_Unauthorized as authenticate() does
     */
    public function authenticateInAnyWebsite(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        int $websiteId,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): Customer {
        $this->getCustomerService()->assertAccountsEnabled();
        return $this->countLogin($email, function () use ($email, $password, $websiteId, $twofaCode): Customer {
            $websiteIds = array_unique([$websiteId, ...array_map(intval(...), array_keys(Mage::app()->getWebsites()))]);
            $forbidden = $error = null;
            foreach ($websiteIds as $id) {
                try {
                    return $this->authenticateInWebsite($email, $password, $id, $twofaCode);
                } catch (Mage_Core_Exception_Forbidden $e) {
                    $forbidden ??= $e;
                } catch (Mage_Core_Exception_Unauthorized $e) {
                    if ($e->getErrorCode() !== self::ERROR_INVALID_CREDENTIALS) {
                        throw $e;
                    }
                    $error = $e;
                }
            }
            throw $forbidden ?? $error;
        });
    }

    /**
     * Return $customer to log in without a password, when another step proved who the customer is: a social sign-in,
     * or a link that a transport of an extension checked. Each login without a password ends here, so that none
     * skips the second factor.
     *
     * @throws Mage_Core_Exception_Forbidden when the account is not active
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Customer_Exception_SecondFactorRequired when the customer uses 2FA and $twofaCode is empty
     * @throws Mage_Core_Exception_Unauthorized with the error code twofa_invalid when $twofaCode is wrong
     */
    public function authenticateVerified(
        Customer $customer,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): Customer {
        $this->getCustomerService()->assertActive($customer);
        $this->verifySecondFactor($customer, (string) $twofaCode);
        return $customer;
    }

    /**
     * Check the 2FA code of $customer, whose identity another step proved. A customer who does not use 2FA needs no
     * code. A wrong code and a right code count in the login limits, as a login with the password does.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Customer_Exception_SecondFactorRequired when $code is empty
     * @throws Mage_Core_Exception_Unauthorized with the error code twofa_invalid when $code is wrong
     */
    public function verifySecondFactor(
        Customer $customer,
        #[\SensitiveParameter]
        string $code,
    ): void {
        if (!$customer->isTwofaRequired()) {
            return;
        }
        $this->countLogin((string) $customer->getEmail(), function () use ($customer, $code): Customer {
            if ($code === '') {
                throw $this->secondFactorRequired($customer);
            }
            if (!$customer->verifyTwofaCode($code)) {
                throw new Mage_Core_Exception_Unauthorized(Mage::helper('customer')->__('2FA verification code is invalid.'), self::ERROR_TWOFA_INVALID);
            }
            return $customer;
        });
    }

    /**
     * Return the secret of $customer for an authenticator app. Create and save it when the customer has none.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store does not allow 2FA
     * @throws Mage_Core_Exception_Conflict when the customer already uses 2FA
     */
    public function getSecondFactorSecret(Customer $customer): string
    {
        $this->assertSecondFactorAllowed();
        if ($customer->getTwofaEnabled()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('customer')->__('Two-factor authentication is already enabled.'));
        }
        $secret = $customer->getTwofaSecret();
        if ($secret === null) {
            $secret = Mage::helper('core/security')->generateTotpSecret();
            $customer->setTwofaSecret($secret)->save();
        }
        return $secret;
    }

    /**
     * Turn 2FA on or off for $customer, who gives the current password. To turn it on, the customer also gives
     * the $code of the authenticator app, which proves that the app has the secret.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store does not allow 2FA
     * @throws Mage_Core_Exception_TooManyRequests when the client or the customer is over the password change limit
     * @throws Mage_Core_Exception_Forbidden when the store requires 2FA and $enable is false
     * @throws Mage_Core_Exception when $currentPassword is wrong, or with the code EXCEPTION_2FA_INVALID when $code
     *                             is wrong
     */
    public function setSecondFactor(
        Customer $customer,
        #[\SensitiveParameter]
        string $currentPassword,
        bool $enable,
        #[\SensitiveParameter]
        ?string $code = null,
    ): Customer {
        $helper = Mage::helper('customer');
        $this->assertSecondFactorAllowed();
        $this->getCustomerService()->verifyPassword($customer, $currentPassword);

        if (!$enable) {
            if (Mage::getStoreConfigFlag(self::XML_PATH_REQUIRE_2FA)) {
                throw new Mage_Core_Exception_Forbidden($helper->__('Two-factor authentication is required and cannot be disabled.'));
            }
            $customer->setTwofaEnabled(false)->setTwofaSecret(null)->save();
            return $customer;
        }
        if ($customer->getTwofaSecret() === null || !$customer->verifyTwofaCode((string) $code)) {
            throw new Mage_Core_Exception($helper->__('Invalid 2FA verification code'), Customer::EXCEPTION_2FA_INVALID);
        }
        $customer->setTwofaEnabled()->save();
        return $customer;
    }

    /**
     * Email a password reset link to the customer of the website $websiteId that has $email. Throw nothing when
     * no customer has the email, or when the email fails, so that the answer does not show which addresses have
     * an account. $captchaFields is what the client sent, the form POST or the API body: the CAPTCHA module finds
     * its token in it.
     * Each request is a row of customer_flowpassword, so a cache flush does not reset the limit.
     *
     * @param array<array-key, mixed> $captchaFields
     * @throws Mage_Core_Exception_Input when the email is empty or not valid, or when the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendPasswordResetLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
        array $captchaFields,
    ): void {
        $this->assertEmail($email);
        Mage::helper('core')->assertCaptcha($captchaFields);
        $this->emailPasswordResetLink($email, $websiteId);
    }

    /**
     * Do what sendPasswordResetLink() does for the staff, who asks the link for a customer. The staff is not an
     * anonymous client, so this entry asks no CAPTCHA. The limits are the same.
     *
     * @throws Mage_Core_Exception_Input when the email is empty or not valid
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendPasswordResetLinkByStaff(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
    ): void {
        $this->assertEmail($email);
        $this->emailPasswordResetLink($email, $websiteId);
    }

    protected function emailPasswordResetLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
    ): void {
        $this->countPasswordResetRequest($email);

        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($customer->getId()) {
            try {
                $customer->sendPasswordResetLinkEmail();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    /**
     * Email the confirmation link again to the customer of the website $websiteId that has $email and waits for
     * a confirmation. Throw nothing when no such customer has the email, or when the email fails, so that the
     * answer does not show which addresses have an account. See sendPasswordResetLink() for $captchaFields.
     *
     * @param array<array-key, mixed> $captchaFields
     * @throws Mage_Core_Exception_Input when the email is empty or not valid, or when the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendConfirmationLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
        array $captchaFields,
    ): void {
        $this->assertEmail($email);
        Mage::helper('core')->assertCaptcha($captchaFields);
        $this->emailConfirmationLink($email, $websiteId);
    }

    /**
     * Do what sendConfirmationLink() does for the staff, who asks the link for a customer. The staff is not an
     * anonymous client, so this entry asks no CAPTCHA. The limits are the same.
     *
     * @throws Mage_Core_Exception_Input when the email is empty or not valid
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendConfirmationLinkByStaff(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
    ): void {
        $this->assertEmail($email);
        $this->emailConfirmationLink($email, $websiteId);
    }

    protected function emailConfirmationLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
    ): void {
        $core = Mage::helper('core');

        // The limit of the client comes first: a client that sends many addresses gets past the limit of one address
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_CONFIRMATION_LINK_RATE_LIMIT);
        $core->rateLimiter('confirmation_link', $limit, self::RATE_LIMIT_WINDOW, RateLimitScope::Ip)->attemptOrThrow();
        $core->rateLimiterBy('confirmation_link', 'confirmation_link:email:' . strtolower($email), $limit, self::RATE_LIMIT_WINDOW)->attemptOrThrow();

        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($customer->getId() && $customer->getConfirmation()) {
            try {
                $this->getCustomerService()->sendConfirmationEmail($customer, (int) $customer->getStoreId());
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    /**
     * Email a login link to the active customer of the website $websiteId that has $email. Throw nothing when
     * no active customer has the email, or when the email fails, so that the answer does not show which
     * addresses have an account.
     *
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off
     * @throws Mage_Core_Exception_NoSuchEntity when the magic link login is disabled
     * @throws Mage_Core_Exception_Input when the email is empty or not valid
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendMagicLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
    ): void {
        $core = Mage::helper('core');
        $this->getCustomerService()->assertAccountsEnabled();
        $this->assertMagicLinkEnabled();
        $this->assertEmail($email);

        $core->rateLimiter('magic_link', (int) Mage::getStoreConfig(self::XML_PATH_MAGIC_LINK_IP_RATE_LIMIT), self::RATE_LIMIT_WINDOW, RateLimitScope::Ip)
            ->attemptOrThrow();
        $core->rateLimiterBy(
            'magic_link',
            'magic_link:email:' . strtolower($email),
            (int) Mage::getStoreConfig(self::XML_PATH_MAGIC_LINK_EMAIL_RATE_LIMIT),
            self::RATE_LIMIT_WINDOW,
        )->attemptOrThrow();

        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($customer->getId() && $customer->getIsActive()) {
            try {
                $customer->sendMagicLinkEmail();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    /**
     * Return the customer $customerId when $token is its password reset link and the link has not expired.
     * The customer is not logged in: the link only lets the customer set a new password.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the link is not valid
     */
    public function getByResetToken(
        int $customerId,
        #[\SensitiveParameter]
        string $token,
    ): Customer {
        return $this->checkToken(function () use ($customerId, $token): Customer {
            $customer = $this->getCustomerService()->findById($customerId);
            if ($customer === null || !$this->isResetTokenValid($customer, $token)) {
                throw new Mage_Core_Exception(Mage::helper('customer')->__('Your password reset link has expired.'));
            }
            return $customer;
        });
    }

    /**
     * Set the new password of the customer $customerId, who follows the password reset link $token.
     * $confirmation defaults to $newPassword.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the link is not valid
     * @throws Mage_Core_Exception_Input when the new password is not valid
     */
    public function resetPasswordById(
        int $customerId,
        #[\SensitiveParameter]
        string $token,
        #[\SensitiveParameter]
        string $newPassword,
        #[\SensitiveParameter]
        ?string $confirmation = null,
    ): Customer {
        $customer = $this->getByResetToken($customerId, $token);
        $this->completeReset($customer, $newPassword, $confirmation);
        return $customer;
    }

    /**
     * Set the new password of the customer of the current website that has $email, who gives the token of the
     * password reset link. A wrong email, a wrong token and an expired token get the same error.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the token is not valid
     * @throws Mage_Core_Exception_Input when the new password is not valid
     */
    public function resetPassword(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $token,
        #[\SensitiveParameter]
        string $newPassword,
    ): bool {
        $customer = $this->checkToken(function () use ($email, $token): Customer {
            $customer = $this->getCustomerService()->getByEmail($email);
            if ($customer === null || !$this->isResetTokenValid($customer, $token)) {
                throw new Mage_Core_Exception(Mage::helper('customer')->__('Invalid or expired reset token.'));
            }
            return $customer;
        });
        $this->completeReset($customer, $newPassword, null);
        return true;
    }

    /**
     * Confirm the account $customerId with the key of the confirmation link. Return true when the account waited
     * for this confirmation: the customer then gets the "confirmed" welcome email. Return false for an account that
     * was already confirmed. This method logs nobody in: see authenticateByConfirmation().
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the account or the key is wrong
     */
    public function confirm(
        int $customerId,
        #[\SensitiveParameter]
        string $key,
    ): bool {
        return (bool) $this->confirmByKey($customerId, $key)->getIsJustConfirmed();
    }

    /**
     * Confirm the account $customerId as confirm() does, and return the customer to log in. Return null for an
     * account that was already confirmed: its key proves nothing, so nobody is logged in. A customer who uses 2FA
     * is confirmed, and then gets Mage_Customer_Exception_SecondFactorRequired.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception_Forbidden when the account is not active
     * @throws Mage_Customer_Exception_SecondFactorRequired when the customer uses 2FA
     * @throws Mage_Core_Exception when the account or the key is wrong
     */
    public function authenticateByConfirmation(
        int $customerId,
        #[\SensitiveParameter]
        string $key,
    ): ?Customer {
        $customer = $this->confirmByKey($customerId, $key);
        return $customer->getIsJustConfirmed() ? $this->authenticateVerified($customer) : null;
    }

    /**
     * Confirm the account of $customer, whose email another step proved: the key of the confirmation link, or
     * a social sign-in with this email. An account that waited for the confirmation gets getIsJustConfirmed() true
     * and the "confirmed" welcome email.
     */
    public function confirmVerified(Customer $customer): Customer
    {
        if ($customer->getConfirmation()) {
            $customer->setConfirmation(null)->save();
            $customer->setIsJustConfirmed(true);
            $this->getCustomerService()->sendConfirmedEmail($customer, (int) $customer->getStoreId());
        }
        return $customer;
    }

    /**
     * Return the customer of the magic login link $token to log in, and invalidate the link, for a client with a
     * session. A customer who uses 2FA gets Mage_Customer_Exception_SecondFactorRequired after the link is invalid:
     * the client keeps the customer ID of the exception in its session and asks for the code.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the magic link login is disabled
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when the
     *                                        account is not active
     * @throws Mage_Customer_Exception_SecondFactorRequired when the customer uses 2FA
     * @throws Mage_Core_Exception when the link is not valid
     */
    public function getByMagicLinkToken(
        #[\SensitiveParameter]
        string $token,
    ): Customer {
        $customer = $this->findByMagicLinkToken($token);
        $customer->clearMagicLinkToken()->save();
        return $this->authenticateVerified($customer);
    }

    /**
     * Log in the customer of the magic login link $token, for a client that has no session to keep the customer
     * between two requests. Check the 2FA code before the link becomes invalid, so that the client can send the
     * same link again with the code.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the magic link login is disabled
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens or of logins
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when the
     *                                        account is not active
     * @throws Mage_Customer_Exception_SecondFactorRequired when the customer uses 2FA and $twofaCode is empty
     * @throws Mage_Core_Exception_Unauthorized with the error code twofa_invalid when $twofaCode is wrong
     * @throws Mage_Core_Exception when the link is not valid
     */
    public function authenticateByMagicLink(
        #[\SensitiveParameter]
        string $token,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): Customer {
        $customer = $this->authenticateVerified($this->findByMagicLinkToken($token), $twofaCode);
        $customer->clearMagicLinkToken()->save();
        return $customer;
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception
     */
    private function confirmByKey(
        int $customerId,
        #[\SensitiveParameter]
        string $key,
    ): Customer {
        $helper = Mage::helper('customer');
        $customer = $this->checkToken(function () use ($customerId, $key, $helper): Customer {
            $customer = $this->getCustomerService()->findById($customerId);
            if ($customer === null) {
                throw new Mage_Core_Exception($helper->__('Wrong customer account specified.'));
            }
            if ($customer->getConfirmation() && !hash_equals((string) $customer->getConfirmation(), $key)) {
                throw new Mage_Core_Exception($helper->__('Wrong confirmation key.'));
            }
            return $customer;
        });

        return $this->confirmVerified($customer);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception_Forbidden
     * @throws Mage_Core_Exception
     */
    private function findByMagicLinkToken(
        #[\SensitiveParameter]
        string $token,
    ): Customer {
        $this->assertMagicLinkEnabled();
        $this->getCustomerService()->assertAccountsEnabled();
        $helper = Mage::helper('customer');
        $customer = $this->checkToken(function () use ($token, $helper): Customer {
            /** @var Customer $customer */
            $customer = Mage::getModel('customer/customer')->getCollection()
                ->addAttributeToSelect('*')
                ->addFieldToFilter('rp_token', $token)
                ->getFirstItem();
            if (!$customer->getId()) {
                throw new Mage_Core_Exception($helper->__('Invalid or expired login link.'));
            }
            if (!$customer->validateMagicLinkToken($token) || $customer->isMagicLinkTokenExpired()) {
                throw new Mage_Core_Exception($helper->__('Your login link has expired. Please request a new one.'));
            }
            return $customer;
        });

        $this->getCustomerService()->assertActive($customer);
        return $customer;
    }

    /**
     * @throws Mage_Core_Exception_Forbidden when the account is not confirmed, with the code
     *                                        EXCEPTION_EMAIL_NOT_CONFIRMED, or when it is not active
     * @throws Mage_Core_Exception_Unauthorized
     */
    private function authenticateInWebsite(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        int $websiteId,
        #[\SensitiveParameter]
        ?string $twofaCode,
    ): Customer {
        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId);
        try {
            $customer->authenticate($email, $password, $twofaCode);
        } catch (Mage_Core_Exception $e) {
            throw match ($e->getCode()) {
                Customer::EXCEPTION_EMAIL_NOT_CONFIRMED => new Mage_Core_Exception_Forbidden(
                    Mage::helper('customer')->__('This account is not confirmed. Please check your email for the confirmation link.'),
                    $e->getCode(),
                    $e,
                ),
                Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD => new Mage_Core_Exception_Unauthorized($e->getMessage(), self::ERROR_INVALID_CREDENTIALS),
                Customer::EXCEPTION_2FA_INVALID => (string) $twofaCode === ''
                    ? $this->secondFactorRequired($customer)
                    : new Mage_Core_Exception_Unauthorized($e->getMessage(), self::ERROR_TWOFA_INVALID),
                default => $e,
            };
        }
        // After the password: only the owner of the account learns that it is not active
        $this->getCustomerService()->assertActive($customer);
        return $customer;
    }

    /**
     * Run $login, which returns the customer or throws. Refuse a client or an email that is over the login limit.
     * Count each login that ends, right or wrong. A login that stops at the request for the 2FA code does not
     * count: the client sends it again with the code.
     *
     * @param callable(): Customer $login
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception
     */
    private function countLogin(#[\SensitiveParameter] string $email, callable $login): Customer
    {
        $core = Mage::helper('core');
        // The limit of the client comes first: a client that tries many addresses gets past the limit of one address
        $limiters = [
            $core->rateLimiter('customer_login', (int) Mage::getStoreConfig(self::XML_PATH_LOGIN_IP_RATE_LIMIT), self::LOGIN_RATE_LIMIT_WINDOW, RateLimitScope::Ip),
            $core->rateLimiterBy(
                'customer_login',
                'customer_login:email:' . strtolower($email),
                (int) Mage::getStoreConfig(self::XML_PATH_LOGIN_RATE_LIMIT),
                self::LOGIN_RATE_LIMIT_WINDOW,
            ),
        ];
        foreach ($limiters as $limiter) {
            $limiter->throwIfTooManyAttempts();
        }
        try {
            return $login();
        } catch (SecondFactorRequired $e) {
            $limiters = [];
            throw $e;
        } finally {
            foreach ($limiters as $limiter) {
                $limiter->hit();
            }
        }
    }

    /**
     * Save the request for a password reset email and refuse it when the email or the client is over the limit of
     * admin/security/forgot_password_*. A refused request counts too.
     *
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function countPasswordResetRequest(#[\SensitiveParameter] string $email): void
    {
        $helper = Mage::helper('customer');
        if ($helper->getCustomerForgotPasswordFlowSecure() === Mage_Customer_Model_Config_Forgotpassword::FLOW_DISABLED) {
            return;
        }
        $email = strtolower($email);
        $request = Mage::getModel('customer/flowpassword')->setEmail($email);
        $request->save();
        if (!$request->checkCustomerForgotPasswordFlowEmail($email)) {
            throw new Mage_Core_Exception_TooManyRequests(
                $helper->__('You have exceeded requests to times per 24 hours from 1 e-mail.'),
                Mage_Customer_Model_Flowpassword::EMAIL_WINDOW,
            );
        }
        if (!$request->checkCustomerForgotPasswordFlowIp()) {
            throw new Mage_Core_Exception_TooManyRequests(
                $helper->__('You have exceeded requests to times per hour from 1 IP.'),
                Mage_Customer_Model_Flowpassword::IP_WINDOW,
            );
        }
    }

    /**
     * Run $check, which throws a Mage_Core_Exception for a rejected token. Count each rejected token against the
     * client, so that a valid link never counts.
     *
     * @template T
     * @param callable(): T $check
     * @return T
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception
     */
    private function checkToken(callable $check): mixed
    {
        $limiter = Mage::helper('core')->rateLimiter(
            'customer_token',
            (int) Mage::getStoreConfig(self::XML_PATH_TOKEN_RATE_LIMIT),
            self::RATE_LIMIT_WINDOW,
        );
        $limiter->throwIfTooManyAttempts(Mage::helper('customer')->__('Too many attempts. Please try again later.'));
        try {
            return $check();
        } catch (Mage_Core_Exception $e) {
            $limiter->hit();
            throw $e;
        }
    }

    /**
     * @throws Mage_Core_Exception_Input
     */
    private function completeReset(
        Customer $customer,
        #[\SensitiveParameter]
        string $password,
        #[\SensitiveParameter]
        ?string $confirmation,
    ): void {
        $customer->setPassword($password)->setPasswordConfirmation($confirmation ?? $password);
        Mage_Core_Exception_Input::assertValid($customer->validateResetPassword(), 'password');

        $customer->completePasswordReset()->save();
    }

    private function isResetTokenValid(
        Customer $customer,
        #[\SensitiveParameter]
        string $token,
    ): bool {
        $stored = $customer->getRpToken();
        return $token !== '' && $stored !== null && hash_equals($stored, $token) && !$customer->isResetPasswordLinkTokenExpired();
    }

    /**
     * @throws Mage_Core_Exception_Input
     */
    private function assertEmail(#[\SensitiveParameter] string $email): void
    {
        $helper = Mage::helper('customer');
        $core = Mage::helper('core');
        if (!$core->isValidNotBlank($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Please enter your email.'));
        }
        if (!$core->isValidEmail($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Invalid email address.'));
        }
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function assertMagicLinkEnabled(): void
    {
        if (!Mage::helper('customer')->isMagicLinkEnabled()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('This sign-in method is not available.'));
        }
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function assertSecondFactorAllowed(): void
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_ALLOW_2FA)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('Two-factor authentication is not available.'));
        }
    }

    private function secondFactorRequired(Customer $customer): SecondFactorRequired
    {
        return new SecondFactorRequired(Mage::helper('customer')->__('Two-factor authentication code is required.'), (int) $customer->getId());
    }

    private function getCustomerService(): Mage_Customer_Service_Customer
    {
        return Mage::getService('customer/customer');
    }
}
