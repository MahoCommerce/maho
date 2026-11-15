<?php

/**
 * Orchestrates social sign-in: token verification, identity lookup,
 * auto-linking by provider-verified email, and account creation.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Service_Identity
{
    public const SUPPORTED_PROVIDERS = ['google', 'apple', 'facebook'];

    public const LOG_FILE = 'social_login.log';

    /** The storefront and the API share the limit of the token endpoint, and each sign-in attempt counts. */
    public const XML_PATH_RATE_LIMIT = 'system/rate_limit/auth_token_ip';

    public const RATE_LIMIT_WINDOW = 60;

    /** Whether the client has no sign-in attempt left. This reads the counter and changes nothing. */
    public function isRateLimited(int $storeId): bool
    {
        return $this->getRateLimiter($storeId)->tooManyAttempts();
    }

    /**
     * Sign in the customer of the provider token $token, and return the customer to log in. Link the identity to
     * the customer that has the email of the provider, or register a new customer.
     *
     * $expectedNonce is the nonce that the token must repeat. A caller that keeps the nonces it issued gives the
     * nonce of this sign-in, or an empty string when its client sent no nonce that it issued: a provider that needs
     * a nonce then refuses the sign-in. Null is for a caller that issues no nonce, such as a token exchange for a
     * server: the token is checked without a nonce.
     *
     * A customer who uses 2FA gives $twofaCode. Without it, the identity is linked and the customer is not logged in.
     *
     * @param array{firstname?: string, lastname?: string} $nameHints Names forwarded by the caller
     *        (Apple sends them only in the first authorization response); used only when a new
     *        account is created, never for existing accounts
     * @return array{customer: Mage_Customer_Model_Customer, is_new: bool, profile_incomplete: bool}
     * @throws Mage_Core_Exception_TooManyRequests when the client is over its limit, or over the registration limit
     *                                              of Mage_Customer_Service_Customer::register() for a new account
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when the account
     *                                        is not active
     * @throws Mage_Customer_Exception_SecondFactorRequired when the customer uses 2FA and $twofaCode is empty
     * @throws Mage_Core_Exception_Unauthorized with the error code twofa_invalid when $twofaCode is wrong
     * @throws Maho_SocialLogin_Model_ProviderUnavailableException when the provider does not answer
     * @throws Mage_Core_Exception With a user-facing message on any failure
     */
    public function authenticate(
        string $provider,
        #[\SensitiveParameter]
        string $token,
        int $storeId,
        ?string $expectedNonce = null,
        array $nameHints = [],
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): array {
        $helper = Mage::helper('sociallogin');
        $this->getRateLimiter($storeId)->attemptOrThrow();

        /** @var Mage_Customer_Service_Customer $customers */
        $customers = Mage::getService('customer/customer');
        $customers->assertAccountsEnabled($storeId);

        $providerModel = $this->getProvider($provider);
        if (!$providerModel->isEnabled($storeId) || $token === '') {
            Mage::throwException($helper->__('This sign-in method is not available.'));
        }
        if ($expectedNonce === '' && $providerModel->requiresNonce()) {
            Mage::throwException($helper->__('Your sign-in attempt has expired. Please try again.'));
        }

        try {
            $claims = $providerModel->verify($token, $storeId, $expectedNonce);
        } catch (InvalidArgumentException $e) {
            Mage::log("Social auth token rejected ({$provider}): {$e->getMessage()}", Mage::LOG_WARNING, self::LOG_FILE);
            Mage::throwException($helper->__('Invalid authentication token.'));
        } catch (RuntimeException $e) {
            Mage::log("Social auth provider error ({$provider}): {$e->getMessage()}", Mage::LOG_ERROR, self::LOG_FILE);
            throw new Maho_SocialLogin_Model_ProviderUnavailableException(
                $helper->__('Sign-in verification is temporarily unavailable. Please try again later.'),
            );
        }

        $store = Mage::app()->getStore($storeId);
        $websiteId = (int) $store->getWebsiteId();
        $isWebsiteScope = Mage::getSingleton('customer/config_share')->isWebsiteScope();

        $identity = Mage::getModel('sociallogin/identity')->loadByProviderIdentity(
            $provider,
            $claims['sub'],
            $isWebsiteScope ? $websiteId : null,
        );
        if ($identity->getId()) {
            $customer = $customers->findById($identity->getCustomerId());
            if (!$customer) {
                Mage::throwException($helper->__('The linked customer account no longer exists.'));
            }
            $customers->assertActive($customer);
            if ($identity->getProviderEmail() !== $claims['email']) {
                $identity->setProviderEmail($claims['email'])->save();
            }
            return $this->finish($customer, $provider, false, $twofaCode);
        }

        $customer = $this->loadCustomerByEmail($claims['email'], $isWebsiteScope ? $websiteId : null);
        if ($customer->getId()) {
            $customers->assertActive($customer);
            // The provider proved ownership of this address, which is exactly what a
            // pending email confirmation would prove; clear it so login can proceed.
            $this->getAuthentication()->confirmVerified($customer);
            $this->createLink($customer, $websiteId, $provider, $claims);
            Mage::log(
                "Auto-linked {$provider} identity to customer {$customer->getId()} by verified email",
                Mage::LOG_INFO,
                self::LOG_FILE,
            );
            return $this->finish($customer, $provider, false, $twofaCode);
        }

        if (!$helper->isRegistrationAllowedViaSocial($storeId) || !Mage::helper('customer')->isRegistrationAllowed()) {
            Mage::throwException($helper->__('Creating new accounts with social sign-in is not allowed. Please register first.'));
        }

        $customer = $this->createCustomer($claims, $nameHints, $storeId);
        $this->createLink($customer, $websiteId, $provider, $claims);
        return $this->finish($customer, $provider, true, $twofaCode);
    }

    public function getProvider(string $code): Maho_SocialLogin_Model_Provider_ProviderInterface
    {
        if (!in_array($code, self::SUPPORTED_PROVIDERS, true)) {
            Mage::throwException(Mage::helper('sociallogin')->__('Unknown sign-in provider.'));
        }
        $provider = Mage::getModel('sociallogin/provider_' . $code);
        if (!$provider instanceof Maho_SocialLogin_Model_Provider_ProviderInterface) {
            Mage::throwException(Mage::helper('sociallogin')->__('Unknown sign-in provider.'));
        }
        return $provider;
    }

    public function unlink(int $customerId, int $identityId): bool
    {
        $identity = Mage::getModel('sociallogin/identity')->load($identityId);
        if (!$identity->getId() || $identity->getCustomerId() !== $customerId) {
            return false;
        }
        $identity->delete();
        return true;
    }

    /**
     * Provider emails arrive lowercased, while stored customer emails keep their original case.
     */
    protected function loadCustomerByEmail(#[\SensitiveParameter] string $email, ?int $websiteId): Mage_Customer_Model_Customer
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $select = $adapter->select()
            ->from($resource->getTableName('customer/entity'), ['entity_id'])
            ->where((string) $adapter->getCaseInsensitiveEqualsSql('email', '?'), $email)
            ->order($adapter->getCheckSql($adapter->quoteInto('email = ?', $email), '0', '1'))
            ->limit(1);
        if ($websiteId !== null) {
            $select->where('website_id = ?', $websiteId);
        }
        $customerId = (int) $adapter->fetchOne($select);

        $customer = Mage::getModel('customer/customer');
        if ($customerId) {
            $customer->load($customerId);
        }
        return $customer;
    }

    /**
     * @param array{sub: string, email: string, given_name: ?string, family_name: ?string, name: ?string} $claims
     */
    protected function createLink(Mage_Customer_Model_Customer $customer, int $websiteId, string $provider, array $claims): void
    {
        Mage::getModel('sociallogin/identity')
            ->setCustomerId((int) $customer->getId())
            ->setWebsiteId($websiteId)
            ->setProvider($provider)
            ->setProviderId($claims['sub'])
            ->setProviderEmail($claims['email'])
            ->save();
    }

    /**
     * @param array{sub: string, email: string, given_name: ?string, family_name: ?string, name: ?string} $claims
     * @param array{firstname?: string, lastname?: string} $nameHints
     */
    protected function createCustomer(array $claims, array $nameHints, int $storeId): Mage_Customer_Model_Customer
    {
        $firstname = $claims['given_name'] ?? trim((string) ($nameHints['firstname'] ?? ''));
        $lastname = $claims['family_name'] ?? trim((string) ($nameHints['lastname'] ?? ''));

        $customer = Mage::getModel('customer/customer')
            ->setEmail($claims['email'])
            ->setFirstname($firstname !== '' ? $firstname : 'Customer')
            ->setLastname($lastname !== '' ? $lastname : '.')
            // The provider verified the email, so the account needs no confirmation
            ->setForceConfirmed()
            // The customer fills the required profile fields after the sign-up, see finish()
            ->setDeferRequiredProfile();
        $customer->setPassword(Mage::helper('core')->getRandomString(32));

        return Mage::getService('customer/customer')->registerFromProvider($customer, $storeId);
    }

    /** The token endpoint has its own counter. */
    protected function getRateLimiter(int $storeId): \Maho\Security\RateLimiter
    {
        return Mage::helper('core')->rateLimiter(
            'social_auth',
            (int) Mage::getStoreConfig(self::XML_PATH_RATE_LIMIT, $storeId),
            self::RATE_LIMIT_WINDOW,
            \Maho\Security\RateLimitScope::Ip,
        );
    }

    /**
     * Each sign-in ends here. The second factor rule of Mage_Customer_Service_Authentication::authenticateVerified()
     * applies after the event, so that the event also tells of a sign-in that still waits for the 2FA code.
     * The registration form was skipped, so a new account can miss a field that the admin requires.
     *
     * @return array{customer: Mage_Customer_Model_Customer, is_new: bool, profile_incomplete: bool}
     * @throws Mage_Core_Exception_Unauthorized
     */
    protected function finish(
        Mage_Customer_Model_Customer $customer,
        string $provider,
        bool $isNew,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): array {
        Mage::dispatchEvent('sociallogin_authenticated', [
            'customer' => $customer,
            'provider' => $provider,
            'is_new' => $isNew,
        ]);
        return [
            'customer' => $this->getAuthentication()->authenticateVerified($customer, $twofaCode),
            'is_new' => $isNew,
            'profile_incomplete' => $isNew && Mage::helper('sociallogin')->hasIncompleteRequiredProfile($customer),
        ];
    }

    #[\Deprecated(message: 'use Mage_Customer_Service_Customer::assertActive()', since: '26.11')]
    protected function assertUsable(Mage_Customer_Model_Customer $customer): void
    {
        Mage::getService('customer/customer')->assertActive($customer);
    }

    private function getAuthentication(): Mage_Customer_Service_Authentication
    {
        return Mage::getService('customer/authentication');
    }
}
