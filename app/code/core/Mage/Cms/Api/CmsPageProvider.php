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
use Maho\ApiPlatform\Resource;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Page Provider, extends CrudProvider with page-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class only adds collection filters, identifier-based lookups and the
 * caller-aware gating of the design fields.
 */
final class CmsPageProvider extends CrudProvider
{
    protected array $defaultSort = ['title' => 'ASC'];

    private ?bool $backOfficeReader = null;

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

    /**
     * Design and layout internals are back-office data: the layout update is
     * executable markup and the theme assignment leaks the storefront's internals.
     * Page reads are public, so only admin and API tokens see them. Every read path
     * (item by id, item by identifier, collection, GraphQL) builds its DTO here.
     */
    #[\Override]
    public function toDto(object $model): Resource
    {
        $dto = parent::toDto($model);

        if ($dto instanceof CmsPage && !$this->isBackOfficeReader()) {
            $dto->layoutUpdateXml = null;
            $dto->customLayoutUpdateXml = null;
            $dto->customTheme = null;
            $dto->customRootTemplate = null;
            $dto->customThemeFrom = null;
            $dto->customThemeTo = null;
        }

        return $dto;
    }

    private function isBackOfficeReader(): bool
    {
        return $this->backOfficeReader ??= $this->isAdmin() || $this->isApiUser();
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $collection->addFieldToFilter('is_active', 1);

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
     * numeric-id path matches the identifier and collection paths.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?CmsPage
    {
        $page = \Mage::getModel('cms/page')->load($id);
        if (!$page->getId() || !$page->getIsActive()) {
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
}
