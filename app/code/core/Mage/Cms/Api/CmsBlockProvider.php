<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Block Provider, extends CrudProvider with block-specific filters.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class only adds collection filters and the exact-identifier lookup.
 */
final class CmsBlockProvider extends CrudProvider
{
    protected array $defaultSort = ['title' => 'ASC'];

    protected bool $supportsScopeAll = true;
    protected ?string $backendResource = 'cms-blocks';

    /**
     * Disabled blocks must not be readable through the public GET /cms-blocks/{id}
     * route. The base provider only store-scopes; enforce is_active here so the
     * numeric-id path matches the identifier and collection paths. Backend
     * readers bypass both checks so drafts and foreign-store blocks stay readable.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?CmsBlock
    {
        $block = \Mage::getModel('cms/block')->load($id);
        if (!$block->getId()) {
            return null;
        }

        if ($this->isBackendReader()) {
            $resource = $block->getResource();
            if (method_exists($resource, 'lookupStoreIds')) {
                $this->assertReadableStores($resource->lookupStoreIds($block->getId()), 'block');
            }

            /** @var CmsBlock */
            return $this->toDto($block);
        }

        if (!$block->getIsActive()) {
            return null;
        }

        $resource = $block->getResource();
        if (method_exists($resource, 'lookupStoreIds')) {
            $storeIds = $resource->lookupStoreIds($block->getId());
            if (!StoreContext::isAvailableForStore($storeIds, StoreContext::getStoreId())) {
                return null;
            }
        }

        /** @var CmsBlock */
        return $this->toDto($block);
    }

    /**
     * cmsBlocks(identifier:) is an exact-match lookup returning 0 or 1 block.
     *
     * @return TraversablePaginator<CmsBlock>
     */
    #[\Override]
    protected function provideCollection(array $context): TraversablePaginator
    {
        $identifier = $context['args']['identifier'] ?? null;
        if ($identifier) {
            return $this->singleItemPaginator($this->getByIdentifier((string) $identifier));
        }

        /** @var TraversablePaginator<CmsBlock> $paginator */
        $paginator = parent::provideCollection($context);
        return $paginator;
    }

    private function getByIdentifier(string $identifier): ?CmsBlock
    {
        if ($this->isBackendReader()) {
            return $this->getByIdentifierBackend($identifier);
        }

        $collection = \Mage::getModel('cms/block')->getCollection();
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addStoreFilter(StoreContext::getStoreId());
        $collection->addFieldToFilter('is_active', 1);
        $collection->setPageSize(1);

        $block = $collection->getFirstItem();

        /** @var CmsBlock|null */
        return $block->getId() ? $this->toDto($block) : null;
    }

    /**
     * Backend readers resolve across every store and status. Current-store
     * matches win over other stores when the identifier is reused (mirrors
     * CmsPageProvider::getPageByIdentifierBackend()).
     */
    private function getByIdentifierBackend(string $identifier): ?CmsBlock
    {
        $collection = \Mage::getModel('cms/block')->getCollection();
        $collection->addFieldToFilter('identifier', $identifier);

        $allowed = $this->allowedStoreIds();
        if ($allowed !== null) {
            $collection->addStoreFilter($allowed, false);
        }

        $collection->setOrder('block_id', 'ASC');

        $currentStoreId = StoreContext::getStoreId();
        $match = null;
        foreach ($collection as $block) {
            $resource = $block->getResource();
            if (method_exists($resource, 'lookupStoreIds')
                && StoreContext::isAvailableForStore($resource->lookupStoreIds($block->getId()), $currentStoreId)
            ) {
                $match = $block;
                break;
            }
            $match ??= $block;
        }

        if ($match === null) {
            return null;
        }

        /** @var CmsBlock */
        return $this->toDto(\Mage::getModel('cms/block')->load($match->getId()));
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        if (!$this->isScopeAll($filters)) {
            $collection->addFieldToFilter('is_active', 1);
        }

        if (!empty($filters['identifier'])) {
            $collection->addFieldToFilter('identifier', ['like' => '%' . $filters['identifier'] . '%']);
        }

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search) {
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
}
