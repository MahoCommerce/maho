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

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Store access helpers shared by content processors (CMS pages, blocks, blog posts).
 *
 * Provides store-ID resolution from input codes and store-level access
 * validation against an ApiUser's allowed stores.
 */
trait StoreAccessTrait
{
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
            /** @var \Mage_Core_Model_Store $store */
            $store = \Mage::app()->getStore($storeCode);
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
