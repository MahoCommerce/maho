<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\CmsBlock;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Block State Provider
 *
 * @implements ProviderInterface<CmsBlock>
 */
final class CmsBlockProvider implements ProviderInterface
{
    /**
     * @return CmsBlock|CmsBlock[]|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsBlock|array|null
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

        return $this->mapToDto($block);
    }

    /**
     * @return CmsBlock[]
     */
    private function getCollection(array $context): array
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

        $blocks = [];
        foreach ($collection as $block) {
            $blocks[] = $this->mapToDto($block);
        }

        return $blocks;
    }

    private function mapToDto(\Mage_Cms_Model_Block $block): CmsBlock
    {
        $dto = new CmsBlock();
        $dto->id = (int) $block->getId();
        $dto->identifier = $block->getIdentifier() ?? '';
        $dto->title = $block->getTitle() ?? '';

        // Process directives for API output
        $dto->content = $this->processContentForApi($block->getContent() ?? '');

        $dto->status = $block->getIsActive() ? 'enabled' : 'disabled';
        $dto->createdAt = $block->getCreationTime();
        $dto->updatedAt = $block->getUpdateTime();

        return $dto;
    }

    /**
     * Process CMS content for API output
     *
     * Handles basic directives (media, config, store) but strips widgets
     * since they require full page context that's not available in API mode.
     */
    private function processContentForApi(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $storeId = StoreContext::getStoreId();
        $store = \Mage::app()->getStore($storeId);

        // Process {{media url="..."}} directive
        $content = preg_replace_callback(
            '/\{\{media\s+url=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($store) {
                $url = $matches[1];
                return $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA) . $url;
            },
            $content,
        );

        // Process {{config path="..."}} directive
        $content = preg_replace_callback(
            '/\{\{config\s+path=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($storeId) {
                return \Mage::getStoreConfig($matches[1], $storeId) ?? '';
            },
            $content,
        );

        // Process {{store url="..."}} directive
        $content = preg_replace_callback(
            '/\{\{store\s+url=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($store) {
                return $store->getUrl($matches[1]);
            },
            $content,
        );

        // Process {{skin url="..."}} directive
        $content = preg_replace_callback(
            '/\{\{skin\s+url=["\']?([^"\'}\s]+)["\']?\s*\}\}/i',
            function ($matches) use ($store) {
                return $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_SKIN) . $matches[1];
            },
            $content,
        );

        // Strip {{widget ...}} directives - they require full page context
        $content = preg_replace(
            '/\{\{widget[^}]*\}\}/i',
            '<!-- widget removed for API -->',
            $content,
        );

        return $content;
    }
}
