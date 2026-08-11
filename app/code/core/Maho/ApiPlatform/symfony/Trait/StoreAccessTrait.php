<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Store access helpers shared by content processors (CMS pages, blocks, blog posts).
 *
 * Provides store-ID resolution from input codes and store-level access
 * validation against an ApiUser's allowed stores.
 */
trait StoreAccessTrait
{
    /**
     * EAV attribute write scope for this request: the explicitly requested store
     * view (?store= / X-Store-Code) for a store-override write, or the admin
     * scope (0) for a global write when no store was requested. The global scope
     * reaches every store, so store-restricted tokens must name a store on their
     * allowlist instead.
     */
    protected function resolveWriteScope(ApiUser $user): int
    {
        $storeId = \Maho\ApiPlatform\Service\StoreContext::getExplicitStoreId()
            ?? \Mage_Core_Model_App::ADMIN_STORE_ID;

        if ($user->getAllowedStoreIds() !== null && !$user->canAccessStore($storeId)) {
            throw new AccessDeniedHttpException(
                $storeId === \Mage_Core_Model_App::ADMIN_STORE_ID
                    ? 'Global-scope writes require an unrestricted token; pass ?store= to write values for a specific store.'
                    : "Access denied for store: {$storeId}",
            );
        }

        return $storeId;
    }

    /**
     * Convert store codes from API input to store IDs, enforcing the user's allowed stores.
     *
     * @param array<string> $stores  Store codes or ['all']
     * @return array<int>
     * @throws AccessDeniedHttpException If the user cannot access a requested store
     */
    protected function resolveStoreIds(array $stores, ApiUser $user): array
    {
        if (in_array('all', $stores, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores === null) {
                return [0]; // Admin store = all stores
            }
            return $allowedStores;
        }

        $storeIds = [];
        foreach ($stores as $storeCode) {
            try {
                /** @var \Mage_Core_Model_Store $store */
                $store = \Mage::app()->getStore($storeCode);
            } catch (\Mage_Core_Model_Store_Exception) {
                throw new BadRequestHttpException("Unknown store: {$storeCode}");
            }
            $storeId = (int) $store->getId();

            if (!$user->canAccessStore($storeId)) {
                throw new AccessDeniedHttpException("Access denied for store: {$storeCode}");
            }

            $storeIds[] = $storeId;
        }

        return $storeIds;
    }

    /**
     * Validate that a user may access an entity based on its store assignments.
     *
     * @param array<int|string> $entityStoreIds Store IDs assigned to the entity
     * @param string            $entityLabel    Human-readable label for error messages (e.g. "page", "block")
     * @throws AccessDeniedHttpException If the user cannot access any of the entity's stores
     */
    protected function validateEntityStoreAccess(array $entityStoreIds, ApiUser $user, string $entityLabel): void
    {
        if (in_array(0, $entityStoreIds, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores !== null) {
                throw new AccessDeniedHttpException('Access denied for all-stores content');
            }
            return;
        }

        foreach ($entityStoreIds as $storeId) {
            if ($user->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException("Access denied for this {$entityLabel}'s stores");
    }
}
