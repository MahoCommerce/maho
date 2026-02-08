<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\StoreConfig;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * StoreConfig State Provider - Provides store configuration data
 *
 * @implements ProviderInterface<StoreConfig>
 */
final class StoreConfigProvider implements ProviderInterface
{
    /**
     * Provide store configuration
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StoreConfig
    {
        StoreContext::ensureStore();

        $storeId = StoreContext::getStoreId();
        $cacheKey = 'api_store_config_' . $storeId;

        // Try cache (1-hour TTL)
        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if ($data !== null) {
                return $this->arrayToDto($data);
            }
        }

        $store = \Mage::app()->getStore($storeId);

        $dto = new StoreConfig();
        $dto->id = $store->getCode();
        $dto->storeCode = $store->getCode();
        $dto->storeName = \Mage::getStoreConfig('general/store_information/name', $storeId)
            ?: $store->getGroup()->getName()
            ?: $store->getName();
        $dto->baseCurrencyCode = $store->getBaseCurrencyCode();
        $dto->defaultDisplayCurrencyCode = $store->getDefaultCurrencyCode();
        $dto->locale = \Mage::getStoreConfig('general/locale/code', $storeId) ?: 'en_AU';
        $dto->timezone = \Mage::getStoreConfig('general/locale/timezone', $storeId) ?: 'Australia/Melbourne';
        $dto->weightUnit = \Mage::getStoreConfig('general/locale/weight_unit', $storeId) ?: 'kgs';
        $dto->baseUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB);
        $dto->baseMediaUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA);

        $allowedCountries = \Mage::getStoreConfig('general/country/allow', $storeId) ?? '';
        $dto->allowedCountries = $allowedCountries ? explode(',', $allowedCountries) : [];

        $dto->isGuestCheckoutAllowed = (bool) \Mage::getStoreConfig('checkout/options/guest_checkout', $storeId);
        $dto->newsletterEnabled = (bool) \Mage::helper('core')->isModuleEnabled('Mage_Newsletter');
        $dto->wishlistEnabled = (bool) \Mage::getStoreConfigFlag('wishlist/general/active', $storeId);
        $dto->reviewsEnabled = (bool) \Mage::getStoreConfigFlag('catalog/review/active', $storeId);

        // Logo
        $logoSrc = \Mage::getStoreConfig('design/header/logo_src', $storeId) ?: 'images/logo.svg';
        $skinBaseUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_SKIN);
        $package = \Mage::getStoreConfig('design/package/name', $storeId) ?: 'default';
        $theme = \Mage::getStoreConfig('design/theme/default', $storeId) ?: 'default';
        $dto->logoUrl = $skinBaseUrl . 'frontend/' . $package . '/' . $theme . '/' . $logoSrc;
        $dto->logoAlt = \Mage::getStoreConfig('design/header/logo_alt', $storeId) ?: $dto->storeName;

        // SEO defaults
        $dto->defaultTitle = \Mage::getStoreConfig('design/head/default_title', $storeId) ?: null;
        $dto->defaultDescription = \Mage::getStoreConfig('design/head/default_description', $storeId) ?: null;

        // Cache for 1 hour
        \Mage::app()->getCache()->save(
            json_encode($this->dtoToArray($dto)),
            $cacheKey,
            ['API_STORE_CONFIG'],
            3600,
        );

        return $dto;
    }

    /**
     * Convert DTO to array for caching
     */
    private function dtoToArray(StoreConfig $dto): array
    {
        return [
            'id' => $dto->id,
            'storeCode' => $dto->storeCode,
            'storeName' => $dto->storeName,
            'baseCurrencyCode' => $dto->baseCurrencyCode,
            'defaultDisplayCurrencyCode' => $dto->defaultDisplayCurrencyCode,
            'locale' => $dto->locale,
            'timezone' => $dto->timezone,
            'weightUnit' => $dto->weightUnit,
            'baseUrl' => $dto->baseUrl,
            'baseMediaUrl' => $dto->baseMediaUrl,
            'allowedCountries' => $dto->allowedCountries,
            'isGuestCheckoutAllowed' => $dto->isGuestCheckoutAllowed,
            'newsletterEnabled' => $dto->newsletterEnabled,
            'wishlistEnabled' => $dto->wishlistEnabled,
            'reviewsEnabled' => $dto->reviewsEnabled,
            'logoUrl' => $dto->logoUrl,
            'logoAlt' => $dto->logoAlt,
            'defaultTitle' => $dto->defaultTitle,
            'defaultDescription' => $dto->defaultDescription,
        ];
    }

    /**
     * Reconstruct DTO from cached array
     */
    private function arrayToDto(array $data): StoreConfig
    {
        $dto = new StoreConfig();
        $dto->id = $data['id'] ?? 'default';
        $dto->storeCode = $data['storeCode'] ?? 'default';
        $dto->storeName = $data['storeName'] ?? '';
        $dto->baseCurrencyCode = $data['baseCurrencyCode'] ?? 'AUD';
        $dto->defaultDisplayCurrencyCode = $data['defaultDisplayCurrencyCode'] ?? 'AUD';
        $dto->locale = $data['locale'] ?? 'en_AU';
        $dto->timezone = $data['timezone'] ?? 'Australia/Melbourne';
        $dto->weightUnit = $data['weightUnit'] ?? 'kgs';
        $dto->baseUrl = $data['baseUrl'] ?? '';
        $dto->baseMediaUrl = $data['baseMediaUrl'] ?? '';
        $dto->allowedCountries = $data['allowedCountries'] ?? [];
        $dto->isGuestCheckoutAllowed = $data['isGuestCheckoutAllowed'] ?? true;
        $dto->newsletterEnabled = $data['newsletterEnabled'] ?? true;
        $dto->wishlistEnabled = $data['wishlistEnabled'] ?? true;
        $dto->reviewsEnabled = $data['reviewsEnabled'] ?? true;
        $dto->logoUrl = $data['logoUrl'] ?? null;
        $dto->logoAlt = $data['logoAlt'] ?? null;
        $dto->defaultTitle = $data['defaultTitle'] ?? null;
        $dto->defaultDescription = $data['defaultDescription'] ?? null;
        return $dto;
    }
}
