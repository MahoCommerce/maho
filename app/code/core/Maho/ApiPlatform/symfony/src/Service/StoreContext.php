<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

/**
 * Store Context Service
 *
 * Manages store context for API requests. Ensures a valid store is set
 * for multi-store configurations and provides store switching capability.
 */
final class StoreContext
{
    private static ?int $currentStoreId = null;

    /**
     * Ensure a valid store context is set
     *
     * @param int|null $storeId Specific store ID, or null for default
     * @return int The active store ID
     */
    public static function ensureStore(?int $storeId = null): int
    {
        if ($storeId !== null) {
            self::setStore($storeId);
            return $storeId;
        }

        // Get current store
        $currentStoreId = (int) \Mage::app()->getStore()->getId();

        // If we're on admin store (0), switch to default store
        if ($currentStoreId === 0) {
            $currentStoreId = self::getDefaultStoreId();
            self::setStore($currentStoreId);
        }

        self::$currentStoreId = $currentStoreId;
        return $currentStoreId;
    }

    /**
     * Set the current store
     */
    public static function setStore(int $storeId): void
    {
        \Mage::app()->setCurrentStore($storeId);
        self::$currentStoreId = $storeId;
    }

    /**
     * Get the current store ID
     */
    public static function getStoreId(): int
    {
        if (self::$currentStoreId === null) {
            return self::ensureStore();
        }
        return self::$currentStoreId;
    }

    /**
     * Get the current store object
     */
    public static function getStore(): \Mage_Core_Model_Store
    {
        return \Mage::app()->getStore(self::getStoreId());
    }

    /**
     * Get the current store code
     */
    public static function getStoreCode(): string
    {
        return self::getStore()->getCode();
    }

    /**
     * Get the root category ID for the current store
     */
    public static function getRootCategoryId(): int
    {
        return (int) self::getStore()->getRootCategoryId();
    }

    /**
     * Get the website ID for the current store
     */
    public static function getWebsiteId(): int
    {
        return (int) self::getStore()->getWebsiteId();
    }

    /**
     * Get default store ID (first active store)
     */
    public static function getDefaultStoreId(): int
    {
        // Try to get from the default website's default store group's default store
        try {
            $website = \Mage::app()->getWebsite(1);
            $storeGroup = $website->getDefaultGroup();
            if ($storeGroup) {
                $store = $storeGroup->getDefaultStore();
                if ($store && $store->getId()) {
                    return (int) $store->getId();
                }
            }
        } catch (\Exception $e) {
            // Fall through to fallback
        }

        // Fallback: find first active store
        $stores = \Mage::app()->getStores(true);
        foreach ($stores as $store) {
            if ($store->getIsActive() && $store->getId() > 0) {
                return (int) $store->getId();
            }
        }

        // Last resort - store 1
        return 1;
    }

    /**
     * Get all available stores
     *
     * @return array<int, array{id: int, code: string, name: string, website_id: int}>
     */
    public static function getAvailableStores(): array
    {
        $stores = [];
        foreach (\Mage::app()->getStores(true) as $store) {
            if ($store->getIsActive()) {
                $stores[] = [
                    'id' => (int) $store->getId(),
                    'code' => $store->getCode(),
                    'name' => $store->getName(),
                    'website_id' => (int) $store->getWebsiteId(),
                ];
            }
        }
        return $stores;
    }
}
