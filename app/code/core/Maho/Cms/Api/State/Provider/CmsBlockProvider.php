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

namespace Maho\Cms\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\Cms\Api\Resource\CmsBlock;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\ContentDirectiveProcessor;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Block State Provider
 *
 * @implements ProviderInterface<CmsBlock>
 */
final class CmsBlockProvider implements ProviderInterface
{
    /**
     * @return CmsBlock|ArrayPaginator<CmsBlock>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsBlock|ArrayPaginator|null
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

        if (!in_array(0, $storeIds) && !in_array($storeId, $storeIds)) {
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
     * @return ArrayPaginator<CmsBlock>
     */
    private function getCollection(array $context): ArrayPaginator
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

        // Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 100), 100));

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $blocks = [];
        foreach ($collection as $block) {
            $blocks[] = $this->mapToDto($block);
        }

        return new ArrayPaginator(
            items: $blocks,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: $total,
        );
    }

    private function mapToDto(\Mage_Cms_Model_Block $block): CmsBlock
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
        $dto->createdAt = $block->getCreationTime();
        $dto->updatedAt = $block->getUpdateTime();

        \Mage::dispatchEvent('api_cms_block_dto_build', ['block' => $block, 'dto' => $dto]);

        return $dto;
    }
}
