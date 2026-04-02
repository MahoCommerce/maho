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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\ContentDirectiveProcessor;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Block State Provider
 */
final class CmsBlockProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return CmsBlock|TraversablePaginator<CmsBlock>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsBlock|TraversablePaginator|null
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName();

        // Handle GraphQL query by identifier
        if ($operationName === 'cmsBlockByIdentifier') {
            $identifier = $context['args']['identifier'] ?? null;
            return $identifier ? $this->getBlockByIdentifier($identifier) : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->getCollection($context);
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    private function getItem(int $id): ?CmsBlock
    {
        $block = \Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            return null;
        }

        // Check if block is available for current store
        $storeId = StoreContext::getStoreId();
        $storeIds = $block->getResource()->lookupStoreIds($block->getId());

        if (!StoreContext::isAvailableForStore($storeIds, $storeId)) {
            return null;
        }

        return $this->mapToDto($block);
    }

    private function getBlockByIdentifier(string $identifier): ?CmsBlock
    {
        $storeId = StoreContext::getStoreId();

        $collection = \Mage::getModel('cms/block')->getCollection();
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setPageSize(1);

        $block = $collection->getFirstItem();

        if (!$block->getId()) {
            return null;
        }

        /** @var \Mage_Cms_Model_Block $block */
        return $this->mapToDto($block);
    }

    /**
     * @return TraversablePaginator<CmsBlock>
     */
    private function getCollection(array $context): TraversablePaginator
    {
        $storeId = StoreContext::getStoreId();
        $filters = $context['filters'] ?? [];
        $search = $filters['search'] ?? $filters['q'] ?? null;

        $collection = \Mage::getModel('cms/block')->getCollection();
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('is_active', 1);

        // Apply identifier filter if provided
        if (!empty($filters['identifier'])) {
            $collection->addFieldToFilter('identifier', ['like' => '%' . $filters['identifier'] . '%']);
        }

        // Apply search filter on title and content
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

        $collection->setOrder('title', 'ASC');

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 100, 100);

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $blocks = [];
        foreach ($collection as $block) {
            $blocks[] = $this->mapToDto($block);
        }

        return new TraversablePaginator(new \ArrayIterator($blocks), $page, $pageSize, $total);
    }

    public function mapToDto(\Mage_Cms_Model_Block $block): CmsBlock
    {
        $dto = new CmsBlock();
        $dto->id = (int) $block->getId();
        $dto->identifier = $block->getIdentifier() ?? '';
        $dto->title = $block->getTitle() ?? '';

        // Process directives for API output
        $dto->content = ContentDirectiveProcessor::process($block->getContent() ?? '');

        $dto->status = $block->getIsActive() ? 'enabled' : 'disabled';
        $dto->isActive = (bool) $block->getIsActive();

        // Map store IDs for admin consumers
        $storeIds = $block->getResource()->lookupStoreIds($block->getId());
        $dto->stores = StoreContext::storeIdsToStoreCodes($storeIds);
        $dto->createdAt = $block->getCreationTime();
        $dto->updatedAt = $block->getUpdateTime();

        \Mage::dispatchEvent('api_cms_block_dto_build', ['block' => $block, 'dto' => $dto]);

        return $dto;
    }
}
