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

/**
 * Enforces an API token's store allowlist at the data layer.
 *
 * Store-scoped entities (orders, invoices, shipments, credit memos) are matched
 * by their store_id; website-scoped entities (customers, sales rules, gift
 * cards) by the websites the allowed stores belong to. Every helper is a no-op
 * for unrestricted tokens (getAllowedStoreIds() === null).
 */
trait StoreRestrictionTrait
{
    /**
     * Restrict a collection to the user's allowed stores. The collection must
     * expose getSelect(); pass a qualified column when the query joins other
     * tables.
     */
    protected function applyAllowedStoreFilter(object $collection, ApiUser $user, string $field = 'main_table.store_id'): void
    {
        $allowedStoreIds = $user->getAllowedStoreIds();
        if ($allowedStoreIds === null) {
            return;
        }

        $collection->getSelect()->where("{$field} IN (?)", $allowedStoreIds === [] ? [-1] : $allowedStoreIds);
    }

    /**
     * Refuse a store that the client names in the URL, the body or the variables, when the token cannot use it.
     * The listeners of the request check ?store= and X-Store-Code only. A request with no token passes.
     *
     * @throws \Mage_Core_Exception_Forbidden
     */
    protected function assertRequestedStoreAllowed(int $storeId, ?ApiUser $user): void
    {
        if ($user !== null && !$user->canAccessStore($storeId)) {
            throw new \Mage_Core_Exception_Forbidden("Token is not authorized for store: {$storeId}");
        }
    }

    /**
     * Refuse a website that the client names in the URL, the body or the variables, when the token cannot use it.
     *
     * @throws \Mage_Core_Exception_Forbidden
     */
    protected function assertRequestedWebsiteAllowed(int $websiteId, ?ApiUser $user): void
    {
        $websiteIds = $user === null ? null : $this->allowedWebsiteIds($user);
        if ($websiteIds !== null && !in_array($websiteId, $websiteIds, true)) {
            throw new \Mage_Core_Exception_Forbidden("Token is not authorized for website: {$websiteId}");
        }
    }

    /**
     * Refuse an action that changes all stores, when the token has a store restriction.
     *
     * @throws \Mage_Core_Exception_Forbidden
     */
    protected function assertUnrestricted(ApiUser $user): void
    {
        if ($user->getAllowedStoreIds() !== null) {
            throw new \Mage_Core_Exception_Forbidden('This action changes all stores. A token with a store restriction cannot do it.');
        }
    }

    /**
     * Deny access when the entity's store is outside the user's allowlist. An
     * entity without a store (null/0) is denied for restricted tokens: it
     * cannot be attributed to any allowed store.
     */
    protected function assertStoreAllowed(int|string|null $storeId, ApiUser $user, string $entityLabel): void
    {
        if ($user->getAllowedStoreIds() === null) {
            return;
        }

        if (!$storeId || !$user->canAccessStore((int) $storeId)) {
            throw new AccessDeniedHttpException("Access denied for this {$entityLabel}'s store");
        }
    }

    /**
     * Deny access when the entity's website is outside the websites the user's
     * allowed stores map to. A missing website (null/0) is denied for
     * restricted tokens.
     */
    protected function assertWebsiteAllowed(int|string|null $websiteId, ApiUser $user, string $entityLabel): void
    {
        $websiteIds = $this->allowedWebsiteIds($user);
        if ($websiteIds === null) {
            return;
        }

        if (!$websiteId || !in_array((int) $websiteId, $websiteIds, true)) {
            throw new AccessDeniedHttpException("Access denied for this {$entityLabel}'s website");
        }
    }

    /**
     * Read gate for an entity associated with a set of websites: deny when none
     * of them is within the user's scope. An empty set is denied, matching the
     * single-website rule.
     *
     * @param int[] $entityWebsiteIds
     */
    protected function assertAnyWebsiteAllowed(array $entityWebsiteIds, ApiUser $user, string $entityLabel): void
    {
        $websiteIds = $this->allowedWebsiteIds($user);
        if ($websiteIds === null) {
            return;
        }

        if (array_intersect(array_map(intval(...), $entityWebsiteIds), $websiteIds) === []) {
            throw new AccessDeniedHttpException("Access denied for this {$entityLabel}'s website");
        }
    }

    /**
     * Write gate for an entity associated with a set of websites: deny unless
     * every one of them is within the user's scope, so a restricted token
     * cannot edit a record that also reaches a website it cannot see.
     *
     * @param int[] $entityWebsiteIds
     */
    protected function assertAllWebsitesAllowed(array $entityWebsiteIds, ApiUser $user, string $entityLabel): void
    {
        $websiteIds = $this->allowedWebsiteIds($user);
        if ($websiteIds === null) {
            return;
        }

        $entityWebsiteIds = array_map(intval(...), $entityWebsiteIds);
        if ($entityWebsiteIds === [] || array_diff($entityWebsiteIds, $websiteIds) !== []) {
            throw new AccessDeniedHttpException("Access denied for this {$entityLabel}'s website");
        }
    }

    /**
     * Return the websites of the store views of a store-restricted user, or null for an unrestricted user.
     *
     * @return list<int>|null
     */
    protected function allowedWebsiteIds(ApiUser $user): ?array
    {
        /** @var \Mage_Catalog_Service_Product $service */
        $service = \Mage::getService('catalog/product');
        return $service->getWebsiteIds($user->getAllowedStoreIds());
    }
}
