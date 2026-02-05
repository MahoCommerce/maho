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
use Maho\ApiPlatform\ApiResource\CmsPage;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Page State Provider
 *
 * @implements ProviderInterface<CmsPage>
 */
final class CmsPageProvider implements ProviderInterface
{
    /**
     * @return CmsPage|CmsPage[]|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsPage|array|null
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName();

        // Handle GraphQL query by identifier
        if ($operationName === 'cmsPageByIdentifier') {
            $identifier = $context['args']['identifier'] ?? null;
            return $identifier ? $this->getPageByIdentifier($identifier) : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
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
     * @return CmsPage[]
     */
    private function getCollection(array $context): array
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

        $pages = [];
        foreach ($collection as $page) {
            $pages[] = $this->mapToDto($page);
        }

        return $pages;
    }

    private function mapToDto(\Mage_Cms_Model_Page $page): CmsPage
    {
        $dto = new CmsPage();
        $dto->id = (int) $page->getId();
        $dto->identifier = $page->getIdentifier() ?? '';
        $dto->title = $page->getTitle() ?? '';
        $dto->contentHeading = $page->getContentHeading();

        // Process directives for API output
        $dto->content = $this->processContentForApi($page->getContent() ?? '');

        $dto->metaKeywords = $page->getMetaKeywords();
        $dto->metaDescription = $page->getMetaDescription();
        $dto->status = $page->getIsActive() ? 'enabled' : 'disabled';
        $dto->createdAt = $page->getCreationTime();
        $dto->updatedAt = $page->getUpdateTime();

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
        // Return empty string or a placeholder comment
        $content = preg_replace(
            '/\{\{widget[^}]*\}\}/i',
            '<!-- widget removed for API -->',
            $content,
        );

        return $content;
    }
}
