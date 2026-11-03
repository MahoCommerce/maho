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

use Maho_SocialLogin_Model_Channel as Channel;

class Maho_SocialLogin_Service_Identity
{
    public const SUPPORTED_PROVIDERS = ['google', 'apple', 'facebook'];

    public const LOG_FILE = 'social_login.log';

    public const XML_PATH_API_RATE_LIMIT = 'system/rate_limit/auth_token_ip';

    /** The window of the rate limit of each channel, in seconds */
    public const RATE_LIMIT_WINDOWS = [Channel::Web->value => 3600, Channel::Api->value => 60];

    /** Whether the client has no sign-in attempt left. This reads the counter and changes nothing. */
    public function isRateLimited(Channel $channel, int $storeId): bool
    {
        return $this->getRateLimiter($channel, $storeId)->tooManyAttempts();
    }

    /**
     * The web channel counts only the failed attempts and requires $expectedNonce, which the session
     * issued, for a provider with a nonce. The API counts every attempt, and a null nonce skips the check.
     *
     * @param array{firstname?: string, lastname?: string} $nameHints Names forwarded by the caller
     *        (Apple sends them only in the first authorization response); used only when a new
     *        account is created, never for existing accounts
     * @return array{customer: Mage_Customer_Model_Customer, is_new: bool, profile_incomplete: bool}
     * @throws Mage_Core_Exception_TooManyRequests when the client is over its limit
     * @throws Maho_SocialLogin_Model_ProviderUnavailableException when the provider does not answer
     * @throws Mage_Core_Exception With a user-facing message on any failure
     */
    public function authenticate(
        string $provider,
        #[\SensitiveParameter]
        string $token,
        int $storeId,
        Channel $channel,
        ?string $expectedNonce = null,
        array $nameHints = [],
    ): array {
        $helper = Mage::helper('sociallogin');
        $limiter = $this->getRateLimiter($channel, $storeId);
        if ($channel === Channel::Web ? $limiter->tooManyAttempts() : !$limiter->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests(match ($channel) {
                Channel::Web => $helper->__('Too many attempts. Please try again later.'),
                Channel::Api => $helper->__('Too many requests. Please try again later.'),
            }, self::RATE_LIMIT_WINDOWS[$channel->value]);
        }

        try {
            $providerModel = $this->getProvider($provider);
            if (!$providerModel->isEnabled($storeId) || $token === '') {
                Mage::throwException($helper->__('This sign-in method is not available.'));
            }
            if ($channel === Channel::Web && $expectedNonce === null && $providerModel->requiresNonce()) {
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
                $customer = Mage::getService('customer/customer')->getById($identity->getCustomerId());
                if (!$customer) {
                    Mage::throwException($helper->__('The linked customer account no longer exists.'));
                }
                $this->assertUsable($customer);
                if ($identity->getProviderEmail() !== $claims['email']) {
                    $identity->setProviderEmail($claims['email'])->save();
                }
                return $this->finish($customer, $provider, false);
            }

            $customer = $this->loadCustomerByEmail($claims['email'], $isWebsiteScope ? $websiteId : null);
            if ($customer->getId()) {
                $this->assertUsable($customer);
                // The provider proved ownership of this address, which is exactly what a
                // pending email confirmation would prove; clear it so login can proceed.
                if ($customer->getConfirmation()) {
                    Mage::getService('customer/customer')->save($customer->setConfirmation(null));
                }
                $this->createLink($customer, $websiteId, $provider, $claims);
                Mage::log(
                    "Auto-linked {$provider} identity to customer {$customer->getId()} by verified email",
                    Mage::LOG_INFO,
                    self::LOG_FILE,
                );
                return $this->finish($customer, $provider, false);
            }

            if (!$helper->isRegistrationAllowedViaSocial($storeId) || !Mage::helper('customer')->isRegistrationAllowed()) {
                Mage::throwException($helper->__('Creating new accounts with social sign-in is not allowed. Please register first.'));
            }

            $customer = $this->createCustomer($claims, $nameHints, $storeId);
            $this->createLink($customer, $websiteId, $provider, $claims);
            return $this->finish($customer, $provider, true);
        } catch (Throwable $e) {
            if ($channel === Channel::Web) {
                $limiter->hit();
            }
            throw $e;
        }
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
        $store = Mage::app()->getStore($storeId);
        $firstname = $claims['given_name'] ?? trim((string) ($nameHints['firstname'] ?? ''));
        $lastname = $claims['family_name'] ?? trim((string) ($nameHints['lastname'] ?? ''));

        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId((int) $store->getWebsiteId());
        $customer->setStore($store);
        $customer->setEmail($claims['email']);
        $customer->setFirstname($firstname !== '' ? $firstname : 'Customer');
        $customer->setLastname($lastname !== '' ? $lastname : '.');
        $customer->getGroupId();
        $customer->setPassword(Mage::helper('core')->getRandomString(32));
        // The provider verified the email; without this the resource model still
        // generates a confirmation key and the new account cannot log in
        $customer->setForceConfirmed();
        Mage::getService('customer/customer')->save($customer);

        try {
            $customer->sendNewAccountEmail('registered', '', $storeId);
        } catch (Throwable $e) {
            Mage::log("Welcome email failed for customer {$customer->getId()}: {$e->getMessage()}", Mage::LOG_WARNING, self::LOG_FILE);
        }

        return $customer;
    }

    protected function assertUsable(Mage_Customer_Model_Customer $customer): void
    {
        if (!$customer->getIsActive()) {
            Mage::throwException(Mage::helper('sociallogin')->__('This account is not active.'));
        }
    }

    /** The API reads the limit of the token endpoint, but keeps its own counter. */
    protected function getRateLimiter(Channel $channel, int $storeId): \Maho\Security\RateLimiter
    {
        [$namespace, $configPath] = match ($channel) {
            Channel::Web => ['sociallogin_auth', Maho_SocialLogin_Helper_Data::XML_PATH_IP_RATE_LIMIT],
            Channel::Api => ['social_auth', self::XML_PATH_API_RATE_LIMIT],
        };
        return Mage::helper('core')->rateLimiter(
            $namespace,
            (int) Mage::getStoreConfig($configPath, $storeId),
            self::RATE_LIMIT_WINDOWS[$channel->value],
            \Maho\Security\RateLimitScope::Ip,
        );
    }

    /**
     * The registration form was skipped, so a new account can miss a field that the admin requires.
     *
     * @return array{customer: Mage_Customer_Model_Customer, is_new: bool, profile_incomplete: bool}
     */
    protected function finish(Mage_Customer_Model_Customer $customer, string $provider, bool $isNew): array
    {
        Mage::dispatchEvent('sociallogin_authenticated', [
            'customer' => $customer,
            'provider' => $provider,
            'is_new' => $isNew,
        ]);
        return [
            'customer' => $customer,
            'is_new' => $isNew,
            'profile_incomplete' => $isNew && Mage::helper('sociallogin')->hasIncompleteRequiredProfile($customer),
        ];
    }
}
