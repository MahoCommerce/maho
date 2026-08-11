<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[ApiResource(
    mahoSection: 'Content',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'BlogPost',
    description: 'Blog post resource',
    provider: BlogPostProvider::class,
    operations: [
        new Get(
            uriTemplate: '/blog-posts/{id}',
            security: 'true',
            description: 'Get a blog post by ID',
        ),
        new GetCollection(
            uriTemplate: '/blog-posts',
            security: 'true',
            description: 'Get blog post collection',
        ),
        new Post(
            uriTemplate: '/blog-posts',
            processor: CrudProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-posts/write')",
            description: 'Creates a new blog post',
        ),
        new Put(
            uriTemplate: '/blog-posts/{id}',
            processor: CrudProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-posts/write')",
            description: 'Updates a blog post',
        ),
        new Delete(
            uriTemplate: '/blog-posts/{id}',
            processor: CrudProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-posts/delete')",
            description: 'Deletes a blog post',
        ),
    ],
    graphQlOperations: [
        new Query(
            security: 'true',
            name: 'item_query',
            description: 'Get a blog post by ID',
        ),
        new QueryCollection(
            security: 'true',
            name: 'collection_query',
            // extraArgs (not args) so the auto-generated cursor pagination
            // args (first/last/before/after) survive alongside the lookup arg.
            extraArgs: [
                'urlKey' => ['type' => 'String', 'description' => 'Exact URL-key lookup (returns 0 or 1 post)'],
                'search' => ['type' => 'String', 'description' => 'Partial match on the post title or body'],
                'categoryId' => ['type' => 'Int', 'description' => 'Only posts in this blog category'],
                'createdFrom' => ['type' => 'String', 'description' => 'Created at or after this UTC date or datetime; a bare date means from 00:00:00'],
                'createdTo' => ['type' => 'String', 'description' => 'Created at or before this UTC date or datetime; a bare date includes the whole day'],
                'updatedSince' => ['type' => 'String', 'description' => 'Updated at or after this UTC date or datetime'],
            ],
            description: 'Get blog posts, optionally filter by URL key',
        ),
    ],
)]
class BlogPost extends CrudResource
{
    public const MODEL = 'blog/post';

    /** Admin ACL gate. Mirrors backend Maho_Blog_Adminhtml_Blog_PostController. */
    public const ADMIN_RESOURCE = \Maho_Blog_Adminhtml_Blog_PostController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public string $title = '';
    public string $urlKey = '';
    public ?string $content = null;
    public ?string $shortContent = null;
    public ?string $image = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $imageUrl = null;

    public ?string $publishDate = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
    public ?string $metaRobots = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = 'enabled';

    public ?bool $isActive = null;

    /** @var int[]|null */
    public ?array $stores = null;

    /**
     * Reads always contain plain integer ids. Writes accept either shape,
     * mixed freely: a bare id, or an {id, position} object to also set the
     * blog_post_category.position of that assignment.
     * @var array<int|array{id: int, position?: int}>|null
     */
    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?array $categoryIds = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $excerpt = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    #[\Override]
    public function applyToModel(object $model): void
    {
        // Handled below: the generic mapping would write the raw (possibly
        // {id, position}-shaped) input to the model's category_ids field.
        $categoryIds = $this->categoryIds;
        $this->categoryIds = null;
        try {
            parent::applyToModel($model);
        } finally {
            $this->categoryIds = $categoryIds;
        }

        if ($categoryIds !== null) {
            [$ids, $positions] = self::normalizeCategoryInput($categoryIds);
            self::assertCategoriesExist($ids);
            $model->setData('categories', $ids);
            $model->setData('category_positions', $positions);
        }

        // On create, apply sensible defaults for fields omitted from the request.
        // On partial update these stay untouched (parent::applyToModel skips nulls),
        // so an enabled/store-restricted post is not silently reset.
        if (!$model->getId()) {
            if ($this->isActive === null) {
                $model->setData('is_active', 1);
            }
            if ($this->stores === null) {
                $model->setData('stores', [0]);
            }
        }
    }

    /**
     * @param array<int|array{id: int, position?: int}> $entries
     * @return array{list<int>, array<int, int>}
     */
    private static function normalizeCategoryInput(array $entries): array
    {
        $ids = [];
        $positions = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $id = (int) ($entry['id'] ?? 0);
                if (array_key_exists('position', $entry)) {
                    $positions[$id] = (int) $entry['position'];
                }
            } else {
                $id = (int) $entry;
            }
            if ($id <= 0) {
                throw new BadRequestHttpException(
                    'Each categoryIds entry must be a positive integer id or an {id, position} object',
                );
            }
            $ids[] = $id;
        }

        return [array_values(array_unique($ids)), $positions];
    }

    /** @param list<int> $ids */
    private static function assertCategoriesExist(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $existing = array_map('intval', \Mage::getResourceModel('blog/category_collection')
            ->addFieldToFilter('entity_id', ['in' => $ids])
            ->getAllIds());
        $missing = array_diff($ids, $existing);
        if ($missing !== []) {
            throw new BadRequestHttpException('Unknown blog category id(s): ' . implode(', ', $missing));
        }
    }

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->content = self::filterContent($dto->content ?? '');
        if ($dto->shortContent !== null && $dto->shortContent !== '') {
            $dto->shortContent = self::filterContent($dto->shortContent);
        }
        $dto->status = $dto->isActive ? 'enabled' : 'disabled';
        $dto->imageUrl = $model->getImageUrl();

        $dto->stores = array_map('intval', $model->getStores());
        $dto->categoryIds = array_map('intval', $model->getCategories());

        if ($model->getContent()) {
            $text = strip_tags($model->getContent());
            $dto->excerpt = mb_strlen($text) > 200
                ? mb_substr($text, 0, 200) . '...'
                : $text;
        }
    }
}
