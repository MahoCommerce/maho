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
use Maho\ApiPlatform\ApiResource\BlogPost;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Blog Post State Provider
 *
 * @implements ProviderInterface<BlogPost>
 */
final class BlogPostProvider implements ProviderInterface
{
    /**
     * @return BlogPost|ArrayPaginator<BlogPost>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BlogPost|ArrayPaginator|null
    {
        StoreContext::ensureStore();

        if ($operation instanceof CollectionOperationInterface) {
            // Handle urlKey filter for GraphQL blogPosts(urlKey: "...") query
            $urlKey = $context['args']['urlKey'] ?? $context['filters']['urlKey'] ?? null;
            if ($urlKey) {
                $post = $this->getPostByUrlKey($urlKey);
                $items = $post ? [$post] : [];
                return new ArrayPaginator(items: $items, currentPage: 1, itemsPerPage: 1, totalItems: count($items));
            }
            return $this->getCollection($context);
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    private function getItem(int $id): ?BlogPost
    {
        $post = \Mage::getModel('blog/post')->load($id);

        if (!$post->getId() || !$post->getIsActive()) {
            return null;
        }

        // Check store availability
        $storeId = StoreContext::getStoreId();
        $stores = $post->getStores();
        if (!in_array(0, $stores) && !in_array($storeId, $stores)) {
            return null;
        }

        return $this->mapToDto($post);
    }

    private function getPostByUrlKey(string $urlKey): ?BlogPost
    {
        $storeId = StoreContext::getStoreId();
        $post = \Mage::getModel('blog/post');
        $postId = $post->getPostIdByUrlKey($urlKey, $storeId);

        if (!$postId) {
            return null;
        }

        $post->load($postId);

        if (!$post->getId() || !$post->getIsActive()) {
            return null;
        }

        return $this->mapToDto($post);
    }

    /**
     * @return ArrayPaginator<BlogPost>
     */
    private function getCollection(array $context): ArrayPaginator
    {
        $storeId = StoreContext::getStoreId();
        $filters = $context['filters'] ?? [];

        $collection = \Mage::getResourceModel('blog/post_collection');
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('is_active', 1);

        // Only show published posts (publish_date <= now)
        $collection->addFieldToFilter('publish_date', [
            'or' => [
                ['null' => true],
                ['lteq' => \Mage_Core_Model_Locale::now()],
            ],
        ]);

        // Apply pagination
        $page = (int) ($filters['page'] ?? 1);
        $pageSize = min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 10), 50);
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        // Sort by publish date descending (newest first)
        $collection->setOrder('publish_date', 'DESC');

        $posts = [];
        foreach ($collection as $post) {
            $posts[] = $this->mapToDto($post);
        }

        $total = (int) $collection->getSize();

        return new ArrayPaginator(
            items: $posts,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: $total,
        );
    }

    private function mapToDto(\Maho_Blog_Model_Post $post): BlogPost
    {
        $dto = new BlogPost();
        $dto->id = (int) $post->getId();
        $dto->title = $post->getTitle() ?? '';
        $dto->urlKey = $post->getUrlKey() ?? '';
        $dto->content = $post->getContent();
        $dto->imageUrl = $post->getImageUrl();
        $dto->publishDate = $post->getPublishDate();
        $dto->metaTitle = $post->getMetaTitle();
        $dto->metaDescription = $post->getMetaDescription();
        $dto->metaKeywords = $post->getMetaKeywords();
        $dto->status = $post->getIsActive() ? 'enabled' : 'disabled';
        $dto->createdAt = $post->getCreatedAt();
        $dto->updatedAt = $post->getUpdatedAt();

        // Create excerpt from content (first 200 chars, strip HTML)
        if ($post->getContent()) {
            $text = strip_tags($post->getContent());
            $dto->excerpt = mb_strlen($text) > 200
                ? mb_substr($text, 0, 200) . '...'
                : $text;
        }

        return $dto;
    }
}
