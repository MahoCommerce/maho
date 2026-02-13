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

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Provider\CategoryProvider;

#[ApiResource(
    shortName: 'Category',
    description: 'Product category resource',
    provider: CategoryProvider::class,
    operations: [
        new Get(
            uriTemplate: '/categories/{id}',
            description: 'Get a category by ID',
        ),
        new GetCollection(
            uriTemplate: '/categories',
            description: 'Get category tree',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'category', description: 'Get a category by ID'),
        new QueryCollection(
            name: 'categories',
            args: [
                'parentId' => ['type' => 'Int', 'description' => 'Filter by parent category ID'],
                'includeInMenu' => ['type' => 'Boolean', 'description' => 'Only include categories in menu'],
            ],
            description: 'Get category tree',
        ),
        new Query(
            name: 'categoryByUrlKey',
            args: ['urlKey' => ['type' => 'String!']],
            description: 'Get a category by URL key',
            resolver: CustomQueryResolver::class,
        ),
    ],
)]
class Category
{
    public ?int $id = null;
    public ?int $parentId = null;
    public string $name = '';
    public ?string $description = null;
    public ?string $urlKey = null;
    public ?string $urlPath = null;
    public ?string $image = null;
    public int $level = 0;
    public int $position = 0;
    public bool $isActive = true;
    public bool $includeInMenu = true;
    public int $productCount = 0;
    /** @var Category[] */
    public array $children = [];
    /** @var int[] */
    public array $childrenIds = [];
    public ?string $path = null;
    public ?string $displayMode = null;
    public ?string $cmsBlock = null;
    public ?string $metaTitle = null;
    public ?string $metaKeywords = null;
    public ?string $metaDescription = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
