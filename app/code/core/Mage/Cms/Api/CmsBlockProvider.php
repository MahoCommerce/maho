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

    /**
     * Disabled blocks must not be readable through the public GET /cms-blocks/{id}
     * route. The base provider only store-scopes; enforce is_active here so the
     * numeric-id path matches the identifier and collection paths.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?CmsBlock
    {
        $block = \Mage::getModel('cms/block')->load($id);
        if (!$block->getId() || !$block->getIsActive()) {
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
        $collection = \Mage::getModel('cms/block')->getCollection();
        $collection->addStoreFilter(StoreContext::getStoreId());
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setPageSize(1);

        $block = $collection->getFirstItem();

        /** @var CmsBlock|null */
        return $block->getId() ? $this->toDto($block) : null;
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $collection->addFieldToFilter('is_active', 1);

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
