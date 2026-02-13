<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Store Controller
 * Provides endpoints for store configuration and multi-store management
 */
class StoreController extends AbstractController
{
    /**
     * Get all available stores/store views
     */
    #[Route('/api/stores', name: 'api_stores_list', methods: ['GET'])]
    public function listStores(): JsonResponse
    {
        $stores = [];
        $websites = [];

        // Build website list
        foreach (\Mage::app()->getWebsites() as $website) {
            $websites[$website->getId()] = [
                'id' => (int) $website->getId(),
                'code' => $website->getCode(),
                'name' => $website->getName(),
                'is_default' => (bool) $website->getIsDefault(),
            ];
        }

        // Build store list with full details (exclude admin store)
        foreach (\Mage::app()->getStores(false) as $store) {
            if (!$store->getIsActive()) {
                continue;
            }

            $storeGroup = \Mage::app()->getGroup($store->getGroupId());

            $stores[] = [
                'id' => (int) $store->getId(),
                'code' => $store->getCode(),
                'name' => $store->getName(),
                'website_id' => (int) $store->getWebsiteId(),
                'group_id' => (int) $store->getGroupId(),
                'group_name' => $storeGroup ? $storeGroup->getName() : null,
                'root_category_id' => (int) $store->getRootCategoryId(),
                'is_active' => (bool) $store->getIsActive(),
                'base_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB),
                'base_link_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_LINK),
                'base_media_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA),
                'locale' => \Mage::getStoreConfig('general/locale/code', $store),
                'currency' => [
                    'base' => $store->getBaseCurrencyCode(),
                    'default' => $store->getDefaultCurrencyCode(),
                ],
            ];
        }

        return new JsonResponse([
            'websites' => array_values($websites),
            'stores' => $stores,
            'current_store' => StoreContext::getStoreId(),
        ]);
    }

    /**
     * Get store configuration
     */
    #[Route('/api/stores/config', name: 'api_store_config', methods: ['GET'])]
    #[Route('/api/{storeCode}/config', name: 'api_store_config_by_code', methods: ['GET'], requirements: ['storeCode' => '[a-z_]+'], priority: -10)]
    public function getStoreConfig(Request $request, ?string $storeCode = null): JsonResponse
    {
        // Set store context if store code provided
        if ($storeCode) {
            $store = $this->getStoreByCode($storeCode);
            if (!$store) {
                return new JsonResponse([
                    'error' => 'store_not_found',
                    'message' => "Store with code '$storeCode' not found",
                ], 404);
            }
            StoreContext::setStore((int) $store->getId());
        } else {
            StoreContext::ensureStore();
        }

        $store = StoreContext::getStore();

        // Build store config response (similar to M2 storeConfig query)
        $config = [
            'store_code' => $store->getCode(),
            'store_name' => $store->getName(),
            'store_id' => (int) $store->getId(),
            'base_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB),
            'base_link_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_LINK),
            'base_media_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA),
            'secure_base_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB, true),
            'secure_base_link_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_LINK, true),
            'secure_base_media_url' => $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA, true),
            'locale' => \Mage::getStoreConfig('general/locale/code', $store),
            'timezone' => \Mage::getStoreConfig('general/locale/timezone', $store),
            'weight_unit' => \Mage::getStoreConfig('general/locale/weight_unit', $store),
            'base_currency_code' => $store->getBaseCurrencyCode(),
            'default_display_currency_code' => $store->getDefaultCurrencyCode(),
            'root_category_id' => (int) $store->getRootCategoryId(),
            'catalog' => [
                'products_per_page' => (int) \Mage::getStoreConfig('catalog/frontend/list_per_page', $store),
                'products_per_page_values' => \Mage::getStoreConfig('catalog/frontend/list_per_page_values', $store),
                'grid_per_page' => (int) \Mage::getStoreConfig('catalog/frontend/grid_per_page', $store),
                'grid_per_page_values' => \Mage::getStoreConfig('catalog/frontend/grid_per_page_values', $store),
                'list_mode' => \Mage::getStoreConfig('catalog/frontend/list_mode', $store),
                'default_sort_by' => \Mage::getStoreConfig('catalog/frontend/default_sort_by', $store),
            ],
            'customer' => [
                'create_account_auto_group_assign' => (bool) \Mage::getStoreConfig('customer/create_account/auto_group_assign', $store),
                'password_minimum_length' => (int) \Mage::getStoreConfig('customer/password/minimum_password_length', $store),
                'required_character_classes_number' => (int) \Mage::getStoreConfig('customer/password/required_character_classes_number', $store),
            ],
            'checkout' => [
                'guest_checkout' => (bool) \Mage::getStoreConfig('checkout/options/guest_checkout', $store),
                'enable_agreements' => (bool) \Mage::getStoreConfig('checkout/options/enable_agreements', $store),
            ],
            'contact' => [
                'enabled' => (bool) \Mage::getStoreConfig('contacts/contacts/enabled', $store),
            ],
        ];

        return new JsonResponse($config);
    }

    /**
     * Get countries for address forms
     */
    #[Route('/api/stores/countries', name: 'api_store_countries', methods: ['GET'])]
    public function getCountries(): JsonResponse
    {
        StoreContext::ensureStore();

        $countries = [];
        $countryCollection = \Mage::getResourceModel('directory/country_collection')
            ->loadByStore();

        foreach ($countryCollection as $country) {
            $regions = [];
            $regionCollection = $country->getRegions();

            if ($regionCollection) {
                foreach ($regionCollection as $region) {
                    $regions[] = [
                        'id' => (int) $region->getId(),
                        'code' => $region->getCode(),
                        'name' => $region->getName(),
                    ];
                }
            }

            $countries[] = [
                'id' => $country->getId(),
                'name' => $country->getName(),
                'iso2_code' => $country->getIso2Code(),
                'iso3_code' => $country->getIso3Code(),
                'available_regions' => $regions,
            ];
        }

        return new JsonResponse(['countries' => $countries]);
    }

    /**
     * Get available currencies
     */
    #[Route('/api/stores/currencies', name: 'api_store_currencies', methods: ['GET'])]
    public function getCurrencies(): JsonResponse
    {
        StoreContext::ensureStore();
        $store = StoreContext::getStore();

        $baseCurrency = $store->getBaseCurrency();
        $currencies = [];

        $allowedCurrencies = $store->getAvailableCurrencyCodes(true);
        $rates = $baseCurrency->getCurrencyRates($baseCurrency, $allowedCurrencies);

        foreach ($allowedCurrencies as $currencyCode) {
            $currency = \Mage::getModel('directory/currency')->load($currencyCode);
            $currencies[] = [
                'code' => $currencyCode,
                'symbol' => $currency->getCurrencySymbol(),
                'exchange_rate' => $rates[$currencyCode] ?? null,
            ];
        }

        return new JsonResponse([
            'base_currency' => $store->getBaseCurrencyCode(),
            'default_currency' => $store->getDefaultCurrencyCode(),
            'currencies' => $currencies,
        ]);
    }

    /**
     * Set current store by code (for store switching)
     */
    #[Route('/api/stores/switch/{storeCode}', name: 'api_store_switch', methods: ['POST'])]
    public function switchStore(string $storeCode): JsonResponse
    {
        $store = $this->getStoreByCode($storeCode);

        if (!$store) {
            return new JsonResponse([
                'error' => 'store_not_found',
                'message' => "Store with code '$storeCode' not found",
            ], 404);
        }

        StoreContext::setStore((int) $store->getId());

        return new JsonResponse([
            'success' => true,
            'store' => [
                'id' => (int) $store->getId(),
                'code' => $store->getCode(),
                'name' => $store->getName(),
            ],
        ]);
    }

    /**
     * Get store by code
     */
    private function getStoreByCode(string $code): ?\Mage_Core_Model_Store
    {
        try {
            $store = \Mage::app()->getStore($code);
            if ($store && $store->getId() && $store->getIsActive()) {
                return $store;
            }
        } catch (\Mage_Core_Model_Store_Exception $e) {
            // Store not found
        }

        return null;
    }
}
