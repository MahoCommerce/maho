<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Block_Buttons extends Mage_Core_Block_Template
{
    /** @var array<int, array<string, string|bool>>|null */
    private ?array $providers = null;

    /**
     * @return array<int, array<string, string|bool>>
     */
    public function getProviders(): array
    {
        return $this->providers ??= Mage::helper('sociallogin')->getEnabledProviders();
    }

    public function hasProvider(string $code): bool
    {
        return array_any($this->getProviders(), fn($provider) => $provider['code'] === $code);
    }

    public function getConfigJson(): string
    {
        return Mage::helper('core')->jsonEncode([
            'nonceUrl' => Mage::getUrl('sociallogin/auth/nonce'),
            'loginUrl' => Mage::getUrl('sociallogin/auth/login'),
            'providers' => $this->getProviders(),
            'strings' => [
                'errorGeneric' => $this->__('Sign-in failed. Please try again later.'),
            ],
        ]);
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')
            || Mage::getSingleton('customer/session')->isLoggedIn()
            || $this->getProviders() === []
        ) {
            return '';
        }
        return parent::_toHtml();
    }
}
