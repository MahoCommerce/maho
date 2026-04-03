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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
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
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a blog category by ID'),
        new QueryCollection(name: 'collection_query', description: 'Get blog categories'),
        new QueryCollection(
            name: 'blogCategories',
            args: ['urlKey' => ['type' => 'String']],
            description: 'Get blog categories, optionally filter by URL key',
        ),
    ],
)]
class BlogCategory extends CrudResource
{
    public const MODEL = 'blog/category';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public string $name = '';
    public string $urlKey = '';
    public ?int $parentId = null;
    public ?string $path = null;
    public int $level = 0;
    public int $position = 0;

    #[ApiProperty(extraProperties: ['modelField' => 'is_active'])]
    public bool $isActive = true;

    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
}
