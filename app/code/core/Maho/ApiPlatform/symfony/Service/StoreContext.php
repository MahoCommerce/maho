<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Store Context Service.
 *
 * Manages store context for API requests. Ensures a valid store is set
 * for multi-store configurations and provides store switching capability.
 *
 * Implements ResetInterface so the Symfony DI container clears static
 * state between requests when the kernel runs in a long-lived process
 * (tests, CLI commands, async workers).
 */
final class StoreContext implements ResetInterface
{
    private static ?int $currentStoreId = null;
    private static ?int $explicitStoreId = null;
    private static ?string $requestedCurrencyCode = null;

    /**
     * The display currency X-Currency-Code asked for, validated against the
     * store resolved here. Null when the request named none.
     *
     * A resource serving a different store than this one cannot honour it, and
     * has to say so rather than answer in a currency nobody asked for.
     */
    public static function getRequestedCurrencyCode(): ?string
    {
        return self::$requestedCurrencyCode;
    }

    public static function setRequestedCurrencyCode(?string $code): void
    {
        self::$requestedCurrencyCode = $code;
    }

    /**
     * Re-apply the request's display currency to a store other than the
     * context store, or refuse. A resource served by its own store (a cart
     * belongs to the store it was created in) wins over the API context, so a
     * currency the header validated against the context store would otherwise
     * be silently dropped.
     *
     * Adapting rather than refusing is what the storefront does with the same
     * disagreement: Mage_Checkout_Model_Session::getQuote() recollects the cart
     * against the currency asked for instead of ignoring the switch. Refused
     * only when that store genuinely cannot serve the currency.
     */
    public static function applyRequestedCurrencyTo(\Mage_Core_Model_Store $store): void
    {
        $code = self::$requestedCurrencyCode;
        if ($code === null || (int) $store->getId() === self::getStoreId()) {
            return;
        }

        if (!isset($store->getServeableCurrencyRates()[$code])) {
            throw new BadRequestHttpException("Currency not available for this cart's store: {$code}");
        }

        $store->setRequestedCurrencyCode($code);
    }

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

        // On admin store (0), switch to the default store unless the caller
        // explicitly requested the admin scope (?store=admin, backend only).
        if ($currentStoreId === 0 && self::$explicitStoreId !== 0) {
            $currentStoreId = self::getDefaultStoreId();
            self::setStore($currentStoreId);
        }

        self::$currentStoreId = $currentStoreId;
        return $currentStoreId;
    }

    /**
     * Set the current store as an explicit caller request (?store= / X-Store-Code),
     * as opposed to an ambient default. Write paths use this distinction to decide
     * between global-scope and store-scoped attribute writes.
     */
    public static function setExplicitStore(int $storeId): void
    {
        self::setStore($storeId);
        self::$explicitStoreId = $storeId;
    }

    /**
     * The store ID the caller explicitly requested for this request, or null
     * when the store context is just the ambient default. 0 means the caller
     * explicitly requested the admin (global) scope.
     */
    public static function getExplicitStoreId(): ?int
    {
        return self::$explicitStoreId;
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
     * Get the root category ID for the current store. In the admin (global)
     * scope the store has no root category, so fall back to the tree root,
     * which spans every store's subtree.
     */
    public static function getRootCategoryId(): int
    {
        $rootId = (int) self::getStore()->getRootCategoryId();
        return $rootId ?: \Mage_Catalog_Model_Category::TREE_ROOT_ID;
    }

    /**
     * Get default store ID (first active store)
     */
    public static function getDefaultStoreId(): int
    {
        $defaultStore = \Mage::app()->getDefaultStoreView();
        if ($defaultStore && $defaultStore->getId()) {
            return (int) $defaultStore->getId();
        }

        return 1;
    }

    /**
     * Reset static state between requests in long-lived processes.
     */
    #[\Override]
    public function reset(): void
    {
        // X-Currency-Code applies to one request, but it lands on the app's
        // shared store object, which outlives the request here. Left behind, a
        // later request with no header is served in the previous caller's
        // currency, and cached under a Vary saying no header was sent.
        if (self::$currentStoreId !== null) {
            try {
                \Mage::app()->getStore(self::$currentStoreId)->clearCurrentCurrency();
            } catch (\Mage_Core_Model_Store_Exception) {
                // Nothing resolved, so nothing to undo.
            }
        }

        self::$currentStoreId = null;
        self::$explicitStoreId = null;
        self::$requestedCurrencyCode = null;
    }

    /**
     * Check if an entity is available for a given store.
     *
     * @param array<int|string> $entityStoreIds Store IDs assigned to the entity
     */
    public static function isAvailableForStore(array $entityStoreIds, int $storeId): bool
    {
        return in_array(0, $entityStoreIds) || in_array($storeId, $entityStoreIds);
    }
}
