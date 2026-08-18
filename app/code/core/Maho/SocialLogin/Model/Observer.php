<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_Observer
{
    /**
     * Exposes the enabled providers and their public client IDs to headless
     * storefronts through the store-config API payload.
     */
    #[Maho\Config\Observer('api_store_config_dto_build')]
    public function addSocialLoginProviders(\Maho\Event\Observer $observer): void
    {
        /** @var \Mage\Core\Api\StoreConfig $dto */
        $dto = $observer->getEvent()->getData('dto');
        $storeId = (int) Mage::app()->getStore($dto->storeCode)->getId();
        $providers = Mage::helper('sociallogin')->getEnabledProviders($storeId);
        if ($providers !== []) {
            $dto->extensions['socialLoginProviders'] = $providers;
        }
    }
}
