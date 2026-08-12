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
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoSection: 'Content',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'BlogCategory',
    description: 'Blog category resource',
    provider: BlogCategoryProvider::class,
    operations: [
        new Get(
            uriTemplate: '/blog-categories/{id}',
            security: 'true',
            description: 'Get a blog category by ID',
        ),
        new GetCollection(
            uriTemplate: '/blog-categories',
            security: 'true',
            description: 'Get blog category collection',
        ),
        new Post(
            uriTemplate: '/blog-categories',
            processor: BlogCategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-categories/write')",
            description: 'Creates a new blog category',
        ),
        new Put(
            uriTemplate: '/blog-categories/{id}',
            processor: BlogCategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-categories/write')",
            description: 'Updates a blog category',
        ),
        new Delete(
            uriTemplate: '/blog-categories/{id}',
            processor: BlogCategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-categories/delete')",
            description: 'Deletes a blog category and its descendants',
        ),
    ],
    graphQlOperations: [
        new Query(
            security: 'true',
            name: 'item_query',
            description: 'Get a blog category by ID',
        ),
        new QueryCollection(
            security: 'true',
            name: 'collection_query',
            extraArgs: ['urlKey' => ['type' => 'String']],
            description: 'Get blog categories, optionally filter by URL key',
        ),
    ],
)]
class BlogCategory extends CrudResource
{
    public const MODEL = 'blog/category';

    /** Admin ACL gate. Mirrors backend Maho_Blog_Adminhtml_Blog_CategoryController. */
    public const ADMIN_RESOURCE = \Maho_Blog_Adminhtml_Blog_CategoryController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?string $name = null;
    public ?string $urlKey = null;
    public ?int $parentId = null;

    #[ApiProperty(writable: false)]
    public ?string $path = null;

    #[ApiProperty(writable: false)]
    public int $level = 0;

    public ?int $position = null;
    public ?bool $isActive = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
    public ?string $metaRobots = null;

    /** @var int[]|null */
    public ?array $stores = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    #[\Override]
    public function applyToModel(object $model): void
    {
        parent::applyToModel($model);

        // On create, apply sensible defaults for fields omitted from the request.
        // On partial update these stay untouched (parent::applyToModel skips nulls),
        // so an enabled/store-restricted category is not silently reset.
        if (!$model->getId()) {
            if ($this->isActive === null) {
                $model->setData('is_active', 1);
            }
            if ($this->stores === null) {
                $model->setData('stores', [0]);
            }
        }
    }

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->stores = array_map(intval(...), $model->getStores());
    }
}
