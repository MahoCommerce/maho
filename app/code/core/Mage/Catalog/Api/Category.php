<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;

#[ApiResource(
    shortName: 'Category',
    description: 'Product category resource',
    provider: CategoryProvider::class,
    operations: [
        new Get(
            uriTemplate: '/categories/{id}',
            security: 'true',
            description: 'Get a category by ID',
        ),
        new GetCollection(
            uriTemplate: '/categories',
            security: 'true',
            description: 'Get category tree',
        ),
        new Post(
            uriTemplate: '/categories',
            processor: CategoryProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Creates a new category',
        ),
        new Put(
            uriTemplate: '/categories/{id}',
            processor: CategoryProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Updates a category',
        ),
        new Delete(
            uriTemplate: '/categories/{id}',
            processor: CategoryProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Deletes a category',
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
class Category extends CrudResource
{
    public const MODEL = 'catalog/category';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public ?int $parentId = null;

    public string $name = '';
    public ?string $description = null;

    public ?string $urlKey = null;

    #[ApiProperty(writable: false)]
    public ?string $urlPath = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $image = null;

    public int $level = 0;
    public int $position = 0;

    public bool $isActive = true;

    public bool $includeInMenu = true;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public int $productCount = 0;

    /** @var Category[] */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $children = [];

    /** @var int[] */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $childrenIds = [];

    #[ApiProperty(writable: false)]
    public ?string $path = null;

    public ?string $displayMode = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $cmsBlock = null;

    public ?string $metaTitle = null;

    public ?string $metaKeywords = null;

    public ?string $metaDescription = null;

    public ?string $pageLayout = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    public static function afterLoad(self $dto, object $model): void
    {
        // Image URL
        if ($model->getImage()) {
            $dto->image = $model->getImageUrl();
        }

        // Product count
        $dto->productCount = (int) $model->getProductCount();

        // Children IDs
        $childrenIds = $model->getChildren();
        if ($childrenIds) {
            $dto->childrenIds = array_map('intval', explode(',', $childrenIds));
        }

        // Display mode
        $dto->displayMode = $model->getDisplayMode() ?: null;
        $dto->pageLayout = $model->getPageLayout() ?: null;

        // CMS block from landing_page
        $landingPage = $model->getLandingPage();
        if ($landingPage) {
            $dto->cmsBlock = self::renderCmsBlock((int) $landingPage);
        }
    }

    private static function renderCmsBlock(int $blockId): ?string
    {
        try {
            $cmsBlock = \Mage::getModel('cms/block')
                ->setStoreId(\Mage::app()->getStore()->getId())
                ->load($blockId);
            if (!$cmsBlock->getIsActive() || !$cmsBlock->getContent()) {
                return null;
            }
            return \Maho\ApiPlatform\CrudResource::filterContent($cmsBlock->getContent());
        } catch (\Throwable) {
            return null;
        }
    }
}
