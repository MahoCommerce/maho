<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use Maho\Config\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'Category',
    description: 'Product category resource',
    provider: CategoryProvider::class,
    normalizationContext: ['groups' => ['category:read']],
    operations: [
        new Get(
            uriTemplate: '/categories/{id}',
            security: 'true',
            description: 'Get a category by ID',
            normalizationContext: ['groups' => ['category:read', 'category:detail']],
        ),
        new GetCollection(
            uriTemplate: '/categories',
            security: 'true',
            description: 'Get category tree',
        ),
        new Post(
            uriTemplate: '/categories',
            processor: CategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('categories/write')",
            description: 'Creates a new category',
            normalizationContext: ['groups' => ['category:read', 'category:detail']],
        ),
        new Put(
            uriTemplate: '/categories/{id}',
            processor: CategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('categories/write')",
            description: 'Updates a category',
            normalizationContext: ['groups' => ['category:read', 'category:detail']],
        ),
        new Delete(
            uriTemplate: '/categories/{id}',
            processor: CategoryProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('categories/delete')",
            description: 'Deletes a category',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a category by ID',
            security: 'true',
            normalizationContext: ['groups' => ['category:read', 'category:detail']],
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get category tree',
            security: 'true',
            // extraArgs (not args) so the auto-generated cursor pagination
            // args (first/last/before/after) survive alongside the filters.
            extraArgs: [
                'parentId' => ['type' => 'Int', 'description' => 'Filter by parent category ID'],
                'includeInMenu' => ['type' => 'Boolean', 'description' => 'Only include categories in menu'],
                'urlKey' => ['type' => 'String', 'description' => 'Exact URL-key lookup (returns 0 or 1 category)'],
            ],
        ),
    ],
)]
class Category extends CrudResource
{
    public const MODEL = 'catalog/category';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Catalog_CategoryController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_CategoryController::ADMIN_RESOURCE;

    #[Groups(['category:read'])]
    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[Groups(['category:read'])]
    public ?int $parentId = null;

    #[Groups(['category:read'])]
    public string $name = '';

    #[Groups(['category:read'])]
    public ?string $description = null;

    #[Groups(['category:read'])]
    public ?string $urlKey = null;

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false)]
    public ?string $urlPath = null;

    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Read: full image URL. Write: media filename stored under media/catalog/category ("" clears)', extraProperties: ['computed' => true])]
    public ?string $image = null;

    #[Groups(['category:read'])]
    public int $level = 0;

    #[Groups(['category:read'])]
    public ?int $position = null;

    #[Groups(['category:read'])]
    public ?bool $isActive = null;

    #[Groups(['category:read'])]
    public ?bool $includeInMenu = null;

    #[Groups(['category:read'])]
    public ?bool $isAnchor = null;

    /** @var string[]|null */
    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Available product listing sort-by codes (empty = use config)', extraProperties: ['computed' => true])]
    public ?array $availableSortBy = null;

    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Default product listing sort-by code ("" clears, falls back to config)')]
    public ?string $defaultSortBy = null;

    #[Groups(['category:read'])]
    #[ApiProperty(description: 'CMS static block ID shown on the category page (0 clears)', extraProperties: ['modelField' => 'landing_page'])]
    public ?int $landingPageId = null;

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?int $childrenCount = null;

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public int $productCount = 0;

    /** @var Category[] */
    #[Groups(['category:detail'])]
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $children = [];

    /** @var int[] */
    #[Groups(['category:read'])]
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $childrenIds = [];

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false)]
    public ?string $path = null;

    #[Groups(['category:read'])]
    public ?string $displayMode = null;

    #[Groups(['category:detail'])]
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $cmsBlock = null;

    #[Groups(['category:read'])]
    public ?string $metaTitle = null;

    #[Groups(['category:read'])]
    public ?string $metaKeywords = null;

    #[Groups(['category:read'])]
    public ?string $metaDescription = null;

    #[Groups(['category:read'])]
    public ?string $pageLayout = null;

    #[Groups(['category:read'])]
    public ?string $metaRobots = null;

    #[Groups(['category:read'])]
    public ?string $customDesign = null;

    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Custom design active-from date (Y-m-d, "" clears)')]
    public ?string $customDesignFrom = null;

    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Custom design active-to date (Y-m-d, "" clears)')]
    public ?string $customDesignTo = null;

    #[Groups(['category:read'])]
    public ?string $customLayoutUpdate = null;

    #[Groups(['category:read'])]
    public ?bool $customUseParentSettings = null;

    #[Groups(['category:read'])]
    public ?bool $customApplyToProducts = null;

    #[Groups(['category:read'])]
    public ?float $filterPriceRange = null;

    /** @var array<string, mixed>|null Arbitrary EAV attributes to set: {"attribute_code": value} (write only) */
    #[ApiProperty(description: 'Arbitrary EAV attributes to set: {"attribute_code": value}', readable: false)]
    public ?array $customAttributesWrite = null;

    /** @var array<int|string, int>|null Map of productId => position (write only, existing assignments) */
    #[ApiProperty(description: 'Positions to set on already-assigned products: {"productId": position}', readable: false)]
    public ?array $productPositions = null;

    /** @var string[]|null Attribute codes whose store override reverts to the default value; only valid with an explicit ?store= scope */
    #[ApiProperty(description: 'Attribute codes to revert to their default (non-store) values; requires ?store=', readable: false)]
    public ?array $useDefault = null;

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    /** @var array<string, mixed> */
    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Module-provided extension data')]
    public array $extensions = [];
<<<<<<< HEAD

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
=======
>>>>>>> 46dc60e (Added missing REST/GraphQL API fields, operations, and store-scoped reads/writes across all resources (#1210))
}
