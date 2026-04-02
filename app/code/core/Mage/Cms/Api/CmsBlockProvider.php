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

use Maho\ApiPlatform\Service\ContentDirectiveProcessor;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Block State Provider
 */
final class CmsBlockProvider extends \Maho\ApiPlatform\Provider
{
    protected ?string $modelAlias = 'cms/block';
    protected int $defaultPageSize = 100;
    protected int $maxPageSize = 100;
    protected array $defaultSort = ['title' => 'ASC'];

    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'cmsBlockByIdentifier') {
            $identifier = $context['args']['identifier'] ?? null;
            return $identifier ? $this->getBlockByIdentifier($identifier) : null;
        }
        return null;
    }

    #[\Override]
    protected function provideItem(int|string $id): ?CmsBlock
    {
        $block = \Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            return null;
        }

        $storeIds = $block->getResource()->lookupStoreIds($block->getId());
        if (!StoreContext::isAvailableForStore($storeIds, StoreContext::getStoreId())) {
            return null;
        }

        return $this->toDto($block);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        $collection->addStoreFilter(StoreContext::getStoreId());
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

    #[\Override]
    protected function toDto(object $block): CmsBlock
    {
        $dto = new CmsBlock();
        $dto->id = (int) $block->getId();
        $dto->identifier = $block->getIdentifier() ?? '';
        $dto->title = $block->getTitle() ?? '';
        $dto->content = ContentDirectiveProcessor::process($block->getContent() ?? '');
        $dto->status = $block->getIsActive() ? 'enabled' : 'disabled';
        $dto->isActive = (bool) $block->getIsActive();

        $storeIds = $block->getResource()->lookupStoreIds($block->getId());
        $dto->stores = StoreContext::storeIdsToStoreCodes($storeIds);
        $dto->createdAt = $block->getCreationTime();
        $dto->updatedAt = $block->getUpdateTime();

        \Mage::dispatchEvent('api_cms_block_dto_build', ['block' => $block, 'dto' => $dto]);

        return $dto;
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
        return $this->toDto($block);
    }
}
