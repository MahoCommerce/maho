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

namespace Mage\Cms\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Mage\Cms\Api\Resource\CmsPage;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\ContentDirectiveProcessor;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Page State Provider
 *
 * @implements ProviderInterface<CmsPage>
 */
final class CmsPageProvider implements ProviderInterface
{
    /**
     * @return CmsPage|ArrayPaginator<CmsPage>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsPage|ArrayPaginator|null
    {
        StoreContext::ensureStore();

        if ($operation instanceof CollectionOperationInterface) {
            // Handle identifier filter for GraphQL cmsPages(identifier: "...") query
            $identifier = $context['args']['identifier'] ?? $context['filters']['identifier'] ?? null;
            if ($identifier) {
                $page = $this->getPageByIdentifier($identifier);
                $items = $page ? [$page] : [];
                return new ArrayPaginator(items: $items, currentPage: 1, itemsPerPage: 1, totalItems: count($items));
            }
            return $this->getCollection($context);
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    private function getItem(int $id): ?CmsPage
    {
        $page = \Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            return null;
        }

        // Check if page is available for current store
        $storeId = StoreContext::getStoreId();
        $storeIds = $page->getResource()->lookupStoreIds($page->getId());

        if (!in_array(0, $storeIds) && !in_array($storeId, $storeIds)) {
            return null;
        }

        return $this->mapToDto($page);
    }

    private function getPageByIdentifier(string $identifier): ?CmsPage
    {
        $storeId = StoreContext::getStoreId();
        $page = \Mage::getModel('cms/page');

        // Check if page exists for this identifier and store
        $pageId = $page->checkIdentifier($identifier, $storeId);

        if (!$pageId) {
            return null;
        }

        $page->load($pageId);

        if (!$page->getId() || !$page->getIsActive()) {
            return null;
        }

        return $this->mapToDto($page);
    }

    /**
     * @return ArrayPaginator<CmsPage>
     */
    private function getCollection(array $context): ArrayPaginator
    {
        $storeId = StoreContext::getStoreId();
        $filters = $context['filters'] ?? [];
        $search = $filters['search'] ?? $filters['q'] ?? null;

        $collection = \Mage::getModel('cms/page')->getCollection();
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('is_active', 1);

        // Apply identifier filter if provided
        if (!empty($filters['identifier'])) {
            $collection->addFieldToFilter('identifier', $filters['identifier']);
        }

        // Apply search filter on title and content (min 3 chars to avoid slow full-table LIKE scans)
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

        $collection->setOrder('title', 'ASC');

        // Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 100), 100));

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $pages = [];
        foreach ($collection as $cmsPage) {
            $pages[] = $this->mapToDto($cmsPage);
        }

        return new ArrayPaginator(
            items: $pages,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: $total,
        );
    }

    private function mapToDto(\Mage_Cms_Model_Page $page): CmsPage
    {
        $dto = new CmsPage();
        $dto->id = (int) $page->getId();
        $dto->identifier = $page->getIdentifier() ?? '';
        $dto->title = $page->getTitle() ?? '';
        $dto->contentHeading = $page->getContentHeading();

        // Process directives for API output
        $dto->content = ContentDirectiveProcessor::process($page->getContent() ?? '');

        $dto->metaKeywords = $page->getMetaKeywords();
        $dto->metaDescription = $page->getMetaDescription();
        $dto->pageLayout = $page->getRootTemplate() ?? 'one_column';
        $dto->status = $page->getIsActive() ? 'enabled' : 'disabled';
        $dto->isActive = (bool) $page->getIsActive();

        // Map store IDs for admin consumers
        $storeIds = $page->getResource()->lookupStoreIds($page->getId());
        if (in_array(0, $storeIds)) {
            $dto->stores = ['all'];
        } else {
            $dto->stores = array_map(function ($id) {
                try {
                    return \Mage::app()->getStore($id)->getCode();
                } catch (\Exception $e) {
                    return (string) $id;
                }
            }, $storeIds);
        }

        $dto->createdAt = $page->getCreationTime();
        $dto->updatedAt = $page->getUpdateTime();

        \Mage::dispatchEvent('api_cms_page_dto_build', ['page' => $page, 'dto' => $dto]);

        return $dto;
    }
}
