<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Page Provider, extends CrudProvider with page-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class only adds collection filters and identifier-based lookups; the
 * design fields gate themselves through their own `#[ApiProperty(security:)]`.
 */
final class CmsPageProvider extends CrudProvider
{
    protected array $defaultSort = ['title' => 'ASC'];

    protected bool $supportsScopeAll = true;
    protected ?string $backOfficeResource = 'cms-pages';

    /**
     * Override provide() to handle identifier-based collection filtering
     * that returns a single-item paginator.
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $this->resourceClass = $operation->getClass();
        if (is_subclass_of($this->resourceClass, \Maho\ApiPlatform\CrudResource::class)) {
            $this->modelAlias = $this->resourceClass::metadata()->model;
        }

        if ($operation instanceof CollectionOperationInterface) {
            $identifier = $context['args']['identifier'] ?? $context['filters']['identifier'] ?? null;
            if ($identifier) {
                return $this->singleItemPaginator($this->getPageByIdentifier($identifier));
            }
        }

        return parent::provide($operation, $uriVariables, $context);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        if (!$this->isScopeAll($filters)) {
            $collection->addFieldToFilter('is_active', 1);
        }

        if (!empty($filters['identifier'])) {
            $collection->addFieldToFilter('identifier', $filters['identifier']);
        }

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search && mb_strlen($search) >= 3) {
            $collection->addFieldToFilter(
                ['title', 'content', 'identifier'],
                [
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                ],
            );
        }
    }

    /**
     * Disabled pages must not be readable through the public GET /cms-pages/{id}
     * route. The base provider only store-scopes; enforce is_active here so the
     * numeric-id path matches the identifier and collection paths. Back-office
     * readers bypass both checks so drafts and foreign-store pages stay readable.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?CmsPage
    {
        $page = \Mage::getModel('cms/page')->load($id);
        if (!$page->getId()) {
            return null;
        }

        if ($this->isBackOfficeReader()) {
            $resource = $page->getResource();
            if (method_exists($resource, 'lookupStoreIds')) {
                $this->assertReadableStores($resource->lookupStoreIds($page->getId()), 'page');
            }

            /** @var CmsPage */
            return $this->toDto($page);
        }

        if (!$page->getIsActive()) {
            return null;
        }

        $resource = $page->getResource();
        if (method_exists($resource, 'lookupStoreIds')) {
            $storeIds = $resource->lookupStoreIds($page->getId());
            if (!StoreContext::isAvailableForStore($storeIds, StoreContext::getStoreId())) {
                return null;
            }
        }

        /** @var CmsPage */
        return $this->toDto($page);
    }

    private function getPageByIdentifier(string $identifier): ?CmsPage
    {
        if ($this->isBackOfficeReader()) {
            return $this->getPageByIdentifierBackOffice($identifier);
        }

        $storeId = StoreContext::getStoreId();
        $page = \Mage::getModel('cms/page');

        $pageId = $page->checkIdentifier($identifier, $storeId);

        if (!$pageId) {
            return null;
        }

        $page->load($pageId);

        if (!$page->getId() || !$page->getIsActive()) {
            return null;
        }

        /** @var CmsPage */
        return $this->toDto($page);
    }

    /**
     * checkIdentifier() only matches active, current-store pages; back-office
     * readers resolve across every store and status. Current-store matches win
     * over other stores when the identifier is reused.
     */
    private function getPageByIdentifierBackOffice(string $identifier): ?CmsPage
    {
        $collection = \Mage::getModel('cms/page')->getCollection();
        $collection->addFieldToFilter('identifier', $identifier);

        $allowed = $this->allowedStoreIds();
        if ($allowed !== null) {
            $collection->addStoreFilter($allowed, false);
        }

        $collection->setOrder('page_id', 'ASC');

        $currentStoreId = StoreContext::getStoreId();
        $match = null;
        foreach ($collection as $page) {
            $resource = $page->getResource();
            if (method_exists($resource, 'lookupStoreIds')
                && StoreContext::isAvailableForStore($resource->lookupStoreIds($page->getId()), $currentStoreId)
            ) {
                $match = $page;
                break;
            }
            $match ??= $page;
        }

        if ($match === null) {
            return null;
        }

        /** @var CmsPage */
        return $this->toDto(\Mage::getModel('cms/page')->load($match->getId()));
    }
}
