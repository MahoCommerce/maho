<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\ContentDirectiveProcessor;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Blog Post State Provider
 */
final class BlogPostProvider extends \Maho\ApiPlatform\Provider
{
    protected ?string $modelAlias = 'blog/post';
    protected int $defaultPageSize = 10;
    protected int $maxPageSize = 50;
    protected array $defaultSort = ['publish_date' => 'DESC'];

    /**
     * Override provide() because BlogPost has a special urlKey-based collection filter
     * that returns a single-item paginator rather than using handleOperation().
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BlogPost|TraversablePaginator|null
    {
        StoreContext::ensureStore();

        if ($operation instanceof CollectionOperationInterface) {
            $urlKey = $context['args']['urlKey'] ?? $context['filters']['urlKey'] ?? null;
            if ($urlKey) {
                $post = $this->getPostByUrlKey($urlKey);
                $items = $post ? [$post] : [];
                return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
            }
            return $this->provideCollection($context);
        }

        return $this->provideItem((int) $uriVariables['id']);
    }

    #[\Override]
    protected function provideItem(int|string $id): ?BlogPost
    {
        $post = \Mage::getModel('blog/post')->load($id);

        if (!$post->getId() || !$post->getIsActive()) {
            return null;
        }

        $storeId = StoreContext::getStoreId();
        $stores = $post->getStores();
        if (!StoreContext::isAvailableForStore($stores, $storeId)) {
            return null;
        }

        return $this->toDto($post);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        $storeId = StoreContext::getStoreId();

        $collection->addAttributeToSelect('image');
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('is_active', 1);

        // Only show published posts (publish_date <= now)
        $collection->addFieldToFilter('publish_date', [
            'or' => [
                ['null' => true],
                ['lteq' => \Mage_Core_Model_Locale::now()],
            ],
        ]);
    }

    #[\Override]
    protected function toDto(object $post): BlogPost
    {
        $dto = new BlogPost();
        $dto->id = (int) $post->getId();
        $dto->title = $post->getTitle() ?? '';
        $dto->urlKey = $post->getUrlKey() ?? '';
        $dto->content = ContentDirectiveProcessor::process($post->getContent() ?? '');
        $dto->imageUrl = $post->getImageUrl();
        $dto->publishDate = $post->getPublishDate();
        $dto->metaTitle = $post->getMetaTitle();
        $dto->metaDescription = $post->getMetaDescription();
        $dto->metaKeywords = $post->getMetaKeywords();
        $dto->status = $post->getIsActive() ? 'enabled' : 'disabled';
        $dto->isActive = (bool) $post->getIsActive();

        // Map store IDs for admin consumers
        $postStores = $post->getStores();
        $dto->stores = StoreContext::storeIdsToStoreCodes($postStores);
        $dto->createdAt = $post->getCreatedAt();
        $dto->updatedAt = $post->getUpdatedAt();
        $dto->categoryIds = array_map('intval', $post->getCategories());

        // Create excerpt from content (first 200 chars, strip HTML)
        if ($post->getContent()) {
            $text = strip_tags($post->getContent());
            $dto->excerpt = mb_strlen($text) > 200
                ? mb_substr($text, 0, 200) . '...'
                : $text;
        }

        \Mage::dispatchEvent('api_blog_post_dto_build', ['post' => $post, 'dto' => $dto]);

        return $dto;
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

        return $this->toDto($post);
    }
}
