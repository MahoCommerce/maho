<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
<<<<<<< HEAD
=======
use Maho\ApiPlatform\Trait\DateRangeFilterTrait;
use Maho\ApiPlatform\Trait\StoreAccessTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
>>>>>>> 46dc60e (Added missing REST/GraphQL API fields, operations, and store-scoped reads/writes across all resources (#1210))

/**
 * Convention-based provider for CrudResource subclasses.
 *
 * Leverages the parent Provider's provideCollection() and pagination, no duplication.
 * Only overrides toDto() for auto-mapping and applyCollectionFilters() for store/EAV handling.
 *
 * Hooks for subclasses:
 *   - applyCollectionFilters($collection, $filters), add WHERE clauses (call parent first)
 *   - afterMap($dto, $model), enrich DTO with computed/related data
 */
class CrudProvider extends Provider
{
<<<<<<< HEAD
=======
    use DateRangeFilterTrait;
    use StoreAccessTrait;

>>>>>>> 46dc60e (Added missing REST/GraphQL API fields, operations, and store-scoped reads/writes across all resources (#1210))
    /** @var class-string<CrudResource>|null */
    protected ?string $resourceClass = null;

    /** Whether this resource supports the back-office `scope=all` collection filter. */
    protected bool $supportsScopeAll = false;

    private ?bool $backOfficeReader = null;

    /**
     * Permission resource id (e.g. 'cms-pages') whose read or write grant makes
     * an API-user token a back-office reader: drafts/disabled rows, cross-store
     * item access and ?scope=all listings. Write counts so an integration can
     * read back the draft it just created. Null keeps those reads admin-only,
     * so a provider that never sets it cannot accidentally open them to every
     * service token regardless of what that token was actually granted.
     */
    protected ?string $backOfficeResource = null;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $this->resourceClass = $operation->getClass();

        // Derive modelAlias from CrudResource metadata
        if (is_subclass_of($this->resourceClass, CrudResource::class)) {
            $this->modelAlias = $this->resourceClass::metadata()->model;
        }

        // Delegate to parent, handles named operations, collection, single item
        return parent::provide($operation, $uriVariables, $context);
    }

    protected function isBackOfficeReader(): bool
    {
        return $this->backOfficeReader ??= $this->isAdmin()
            || ($this->isApiUser()
                && $this->backOfficeResource !== null
                && ($this->getAuthorizedUser()->hasPermission($this->backOfficeResource . '/read')
                    || $this->getAuthorizedUser()->hasPermission($this->backOfficeResource . '/write')));
    }

    /**
     * Whether the caller requested a cross-store, unfiltered listing (?scope=all)
     * on a resource that supports it. Back-office only: guests and customers must
     * never see draft or foreign-store content, so anyone else gets a 403.
     */
    protected function isScopeAll(array $filters): bool
    {
        if (!$this->supportsScopeAll || ($filters['scope'] ?? null) !== 'all') {
            return false;
        }
        if (!$this->isBackOfficeReader()) {
            throw new AccessDeniedHttpException('scope=all requires a back-office token');
        }
        return true;
    }

    /**
     * Allowed store ids of a store-restricted token, or null for unrestricted callers.
     *
     * @return array<int>|null
     */
    protected function allowedStoreIds(): ?array
    {
        $user = $this->security?->getUser();
        return $user instanceof ApiUser ? $user->getAllowedStoreIds() : null;
    }

    /**
     * Back-office item reads bypass the current-store availability check, but a
     * store-restricted token must stay inside its allowlist (all-stores content
     * included, which such tokens may not see).
     *
     * @param array<int|string> $entityStoreIds
     */
    protected function assertReadableStores(array $entityStoreIds, string $entityLabel): void
    {
        if ($this->allowedStoreIds() !== null) {
            $this->validateEntityStoreAccess($entityStoreIds, $this->getAuthorizedUser(), $entityLabel);
        }
    }

    /**
     * GraphQL passes arguments in context['args'], not context['filters'];
     * surface only `scope` so isScopeAll() sees it on both protocols.
     *
     * @return TraversablePaginator<Resource>
     */
    #[\Override]
    protected function provideCollection(array $context): TraversablePaginator
    {
        if (isset($context['args']['scope']) && !isset($context['filters']['scope'])) {
            $context['filters']['scope'] = $context['args']['scope'];
        }

        return parent::provideCollection($context);
    }

    /**
     * Single item with store availability check.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?Resource
    {
        $model = $this->loadById($this->modelAlias, $id);
        if (!$model->getId()) {
            return null;
        }

        // Store scoping: check if model is available for current store
        if (method_exists($model->getResource(), 'lookupStoreIds')) {
            $storeIds = $model->getResource()->lookupStoreIds($model->getId());
            if (!StoreContext::isAvailableForStore($storeIds, StoreContext::getStoreId())) {
                return null;
            }
        }

        return $this->toDto($model);
    }

    /**
     * Auto-map model to DTO via CrudResource convention + dispatch extension event.
     * Parent's provideCollection() calls this for each item, no need to override provideCollection().
     */
    #[\Override]
    public function toDto(object $model): Resource
    {
        $dto = $this->resourceClass::fromModel($model);
        $this->afterMap($dto, $model);

        // Dispatch extension event: api_article_dto_build, api_cms_block_dto_build, etc.
        $shortName = (new \ReflectionClass($this->resourceClass))->getShortName();
        $eventName = 'api_' . strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($shortName))) . '_dto_build';
        \Mage::dispatchEvent($eventName, ['model' => $model, 'dto' => $dto]);

        return $dto;
    }

    /**
     * Auto-apply store filtering and EAV attribute loading.
     * Subclasses should call parent::applyCollectionFilters() first, then add their own filters.
     */
    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        if ($this->isScopeAll($filters)) {
            // Cross-store listing: no current-store filter. A store-restricted
            // token is still pinned to its allowlist, without the admin (0) rows.
            $allowed = $this->allowedStoreIds();
            if ($allowed !== null) {
                if (!method_exists($collection, 'addStoreFilter')) {
                    // Failing open would leak cross-store data to a restricted token.
                    throw new \LogicException(static::class . ': supportsScopeAll requires an addStoreFilter() collection');
                }
                $collection->addStoreFilter($allowed, false);
            }
        } else {
            $storeId = StoreContext::getStoreId();

            // Store filtering, auto-detect the collection's method
            if (method_exists($collection, 'addStoreFilter')) {
                $collection->addStoreFilter($storeId);
            } elseif (method_exists($collection, 'setStoreId')) {
                $collection->setStoreId($storeId);
            }
        }

        // EAV collections need explicit attribute selection, only load what the DTO needs
        if ($collection instanceof \Mage_Eav_Model_Entity_Collection_Abstract
            && $this->resourceClass
            && is_subclass_of($this->resourceClass, CrudResource::class)
        ) {
            foreach ($this->resourceClass::metadata()->fields as $field) {
                if (!$field->isIdentifier) {
                    try {
                        $collection->addAttributeToSelect($field->modelField);
                    } catch (\Throwable) {
                        // Not every DTO field is an EAV attribute, skip silently
                    }
                }
            }
        }
    }

    /**
     * Hook: enrich the DTO after auto-mapping.
     * Use this for computed fields, related data, content processing, etc.
     */
    protected function afterMap(Resource $dto, object $model): void {}
}
