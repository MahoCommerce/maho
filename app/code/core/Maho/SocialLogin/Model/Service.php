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

class Maho_SocialLogin_Model_Service
{
    public const SUPPORTED_PROVIDERS = ['google', 'apple', 'facebook'];

    public const LOG_FILE = 'social_login.log';

    /**
     * @param array{firstname?: string, lastname?: string} $nameHints Names forwarded by the caller
     *        (Apple sends them only in the first authorization response); used only when a new
     *        account is created, never for existing accounts
     * @return array{customer: Mage_Customer_Model_Customer, is_new: bool}
     * @throws Mage_Core_Exception With a user-facing message on any failure
     */
    public function authenticate(
        string $provider,
        #[\SensitiveParameter]
        string $token,
        int $storeId,
        ?string $expectedNonce = null,
        array $nameHints = [],
    ): array {
        $helper = Mage::helper('sociallogin');
        $providerModel = $this->getProvider($provider);
        if (!$providerModel->isEnabled($storeId) || $token === '') {
            Mage::throwException($helper->__('This sign-in method is not available.'));
        }

        try {
            $claims = $providerModel->verify($token, $storeId, $expectedNonce);
        } catch (InvalidArgumentException $e) {
            Mage::log("Social auth token rejected ({$provider}): {$e->getMessage()}", Mage::LOG_WARNING, self::LOG_FILE);
            Mage::throwException($helper->__('Invalid authentication token.'));
        } catch (RuntimeException $e) {
            Mage::log("Social auth provider error ({$provider}): {$e->getMessage()}", Mage::LOG_ERROR, self::LOG_FILE);
            Mage::throwException($helper->__('Sign-in verification is temporarily unavailable. Please try again later.'));
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
            $customer = Mage::getModel('customer/customer')->load($identity->getCustomerId());
            if (!$customer->getId()) {
                Mage::throwException($helper->__('The linked customer account no longer exists.'));
            }
            $this->assertUsable($customer);
            if ($identity->getProviderEmail() !== $claims['email']) {
                $identity->setProviderEmail($claims['email'])->save();
            }
            return $this->finish($customer, $provider, false);
        }

        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId);
        $customer->loadByEmail($claims['email']);
        if ($customer->getId()) {
            $this->assertUsable($customer);
            // The provider proved ownership of this address, which is exactly what a
            // pending email confirmation would prove; clear it so login can proceed.
            if ($customer->getConfirmation() && strtolower((string) $customer->getEmail()) === $claims['email']) {
                $customer->setConfirmation(null)->save();
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
        // The provider verified the email, so no confirmation round-trip is needed
        $customer->setConfirmation(null);
        $customer->save();

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

    /**
     * @return array{customer: Mage_Customer_Model_Customer, is_new: bool}
     */
    protected function finish(Mage_Customer_Model_Customer $customer, string $provider, bool $isNew): array
    {
        Mage::dispatchEvent('sociallogin_authenticated', [
            'customer' => $customer,
            'provider' => $provider,
            'is_new' => $isNew,
        ]);
        return ['customer' => $customer, 'is_new' => $isNew];
    }
}
