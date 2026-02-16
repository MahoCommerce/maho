<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Maho\ApiPlatform\ApiResource\Admin\BlogPost;
use Maho_Blog_Model_Post;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<BlogPost>
 */
final class BlogPostProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BlogPost|array|null
    {
        if ($operation instanceof GetCollection) {
            return $this->getCollection();
        }

        if (!isset($uriVariables['id'])) {
            return null;
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    /**
     * @return BlogPost[]
     */
    private function getCollection(): array
    {
        $collection = Mage::getResourceModel('blog/post_collection')
            ->setOrder('publish_date', 'DESC');

        $posts = [];
        foreach ($collection as $post) {
            $posts[] = $this->mapPostToDto($post);
        }

        return $posts;
    }

    private function getItem(int $id): BlogPost
    {
        $post = Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            throw new NotFoundHttpException('Blog post not found');
        }

        return $this->mapPostToDto($post);
    }

    private function mapPostToDto(Maho_Blog_Model_Post $post): BlogPost
    {
        $dto = new BlogPost();
        $dto->id = (int) $post->getId();
        $dto->identifier = $post->getData('url_key') ?? '';
        $dto->title = $post->getTitle() ?? '';
        $dto->shortContent = $post->getData('short_content') ?? null;
        $dto->content = $post->getContent() ?? '';
        $dto->author = $post->getData('author') ?? null;
        $dto->isActive = (bool) $post->getIsActive();
        $dto->publishedAt = $post->getData('publish_date');
        $dto->metaTitle = $post->getData('meta_title');
        $dto->metaKeywords = $post->getData('meta_keywords');
        $dto->metaDescription = $post->getData('meta_description');
        $dto->image = $post->getData('image');

        // Map store IDs back to codes
        $storeIds = $post->getStoreIds() ?? [];
        if (empty($storeIds) || in_array(0, $storeIds, true)) {
            $dto->stores = ['all'];
        } else {
            $dto->stores = [];
            foreach ($storeIds as $storeId) {
                $store = Mage::app()->getStore($storeId);
                $dto->stores[] = $store->getCode();
            }
        }

        return $dto;
    }
}
