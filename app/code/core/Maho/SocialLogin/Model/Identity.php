<?php

/**
 * Link between a customer account and a social provider identity.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_Identity extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('sociallogin/identity');
    }

    /**
     * @param int|null $websiteId Null looks the identity up across all websites
     *                            (customer accounts shared globally)
     */
    public function loadByProviderIdentity(string $provider, string $providerId, ?int $websiteId): self
    {
        /** @var Maho_SocialLogin_Model_Resource_Identity $resource */
        $resource = $this->getResource();
        $resource->loadByProviderIdentity($this, $provider, $providerId, $websiteId);
        return $this;
    }

    public function getCustomerId(): int
    {
        return (int) $this->_getData('customer_id');
    }

    public function setCustomerId(int $value): self
    {
        return $this->setData('customer_id', $value);
    }

    public function getWebsiteId(): int
    {
        return (int) $this->_getData('website_id');
    }

    public function setWebsiteId(int $value): self
    {
        return $this->setData('website_id', $value);
    }

    public function getProvider(): string
    {
        return (string) $this->_getData('provider');
    }

    public function setProvider(string $value): self
    {
        return $this->setData('provider', $value);
    }

    public function getProviderId(): string
    {
        return (string) $this->_getData('provider_id');
    }

    public function setProviderId(string $value): self
    {
        return $this->setData('provider_id', $value);
    }

    public function getProviderEmail(): ?string
    {
        $value = $this->_getData('provider_email');
        return $value === null ? null : (string) $value;
    }

    public function setProviderEmail(?string $value): self
    {
        return $this->setData('provider_email', $value);
    }

    public function getCreatedAt(): string
    {
        return (string) $this->_getData('created_at');
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        if (!$this->getId() && !$this->_getData('created_at')) {
            $this->setData('created_at', Mage_Core_Model_Locale::nowUtc());
        }
        parent::_beforeSave();
        return $this;
    }
}
