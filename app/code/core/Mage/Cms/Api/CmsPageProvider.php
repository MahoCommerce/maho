<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Cms\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\ContentDirectiveProcessor;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Page State Provider
 */
final class CmsPageProvider extends \Maho\ApiPlatform\Provider
{
    protected ?string $modelAlias = 'cms/page';
    protected int $defaultPageSize = 100;
    protected int $maxPageSize = 100;
    protected array $defaultSort = ['title' => 'ASC'];

    /**
     * Override provide() because CmsPage has a special identifier-based collection filter
     * that returns a single-item paginator rather than using handleOperation().
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsPage|TraversablePaginator|null
    {
        StoreContext::ensureStore();

        if ($operation instanceof CollectionOperationInterface) {
            $identifier = $context['args']['identifier'] ?? $context['filters']['identifier'] ?? null;
            if ($identifier) {
                $page = $this->getPageByIdentifier($identifier);
                $items = $page ? [$page] : [];
                return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
            }
            return $this->provideCollection($context);
        }

        return $this->provideItem((int) $uriVariables['id']);
    }

    #[\Override]
    protected function provideItem(int|string $id): ?CmsPage
    {
        $page = \Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            return null;
        }

        $storeId = StoreContext::getStoreId();
        $storeIds = $page->getResource()->lookupStoreIds($page->getId());

        if (!StoreContext::isAvailableForStore($storeIds, $storeId)) {
            return null;
        }

        return $this->toDto($page);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        $collection->addStoreFilter(StoreContext::getStoreId());
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

    #[\Override]
    protected function toDto(object $page): CmsPage
    {
        $dto = new CmsPage();
        $dto->id = (int) $page->getId();
        $dto->identifier = $page->getIdentifier() ?? '';
        $dto->title = $page->getTitle() ?? '';
        $dto->contentHeading = $page->getContentHeading();
        $dto->content = ContentDirectiveProcessor::process($page->getContent() ?? '');
        $dto->metaKeywords = $page->getMetaKeywords();
        $dto->metaDescription = $page->getMetaDescription();
        $dto->pageLayout = $page->getRootTemplate() ?? 'one_column';
        $dto->status = $page->getIsActive() ? 'enabled' : 'disabled';
        $dto->isActive = (bool) $page->getIsActive();

        $storeIds = $page->getResource()->lookupStoreIds($page->getId());
        $dto->stores = StoreContext::storeIdsToStoreCodes($storeIds);

        $dto->createdAt = $page->getCreationTime();
        $dto->updatedAt = $page->getUpdateTime();

        \Mage::dispatchEvent('api_cms_page_dto_build', ['page' => $page, 'dto' => $dto]);

        return $dto;
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

        return $this->toDto($page);
    }
}
