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
        $dto->storeName = $store->getName();
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
        return $dto;
    }
}
