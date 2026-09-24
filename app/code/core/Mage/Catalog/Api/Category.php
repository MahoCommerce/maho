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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
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
            description: 'Get a category by ID. Admin and API tokens with category access also get inactive categories',
            normalizationContext: ['groups' => ['category:read', 'category:detail']],
        ),
        new GetCollection(
            uriTemplate: '/categories',
            security: 'true',
            description: 'Get category tree. Admin and API tokens with category access also get inactive categories',
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
        new Post(
            uriTemplate: '/categories/{id}/image',
            name: 'category_image_upload',
            requirements: ['id' => '\d+'],
            status: 200,
            read: false,
            deserialize: false,
            processor: CategoryImageProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('categories/write')",
            description: 'Uploads the image of a category and replaces the current image. Body: base64 (a JPEG, PNG, GIF, WEBP or AVIF image, at most 5 MB after decoding), filename (for example photo.jpg). With ?store=<store view code>, the image is set for that store view only. Response: the category',
            normalizationContext: ['groups' => ['category:read', 'category:detail']],
            // The processor reads the raw body, so no writable property of the DTO describes it
            openapi: new OpenApiOperation(
                responses: ['200' => new OpenApiResponse(description: 'The category with its new image')],
                summary: 'Upload the image of a category',
                description: 'Saves the image under media/catalog/category as the admin does and sets it as the image of the category. Without ?store=, the global image changes. With ?store=<store view code>, only the image of that store view changes. The old file is deleted when no category uses it. Returns the category, as GET /categories/{id} does.',
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'base64' => ['type' => 'string', 'format' => 'byte', 'description' => 'The image file, base64 encoded. At most 5 MB after decoding'],
                                    'filename' => ['type' => 'string', 'description' => 'The file name, for example photo.jpg. The file is saved under media/catalog/category with this name, or with a number added when the name is in use'],
                                ],
                                'required' => ['base64', 'filename'],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Delete(
            uriTemplate: '/categories/{id}/image',
            name: 'category_image_delete',
            requirements: ['id' => '\d+'],
            read: false,
            processor: CategoryImageProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('categories/write')",
            description: 'Removes the image of a category. With ?store=<store view code>, the image is removed for that store view only',
            openapi: new OpenApiOperation(
                responses: ['204' => new OpenApiResponse(description: 'The image is removed')],
                summary: 'Remove the image of a category',
                description: 'Removes the image of a category, as PUT /categories/{id} with an empty image does. Without ?store=, the global image is removed. With ?store=<store view code>, only that store view shows no image. The file is deleted when no category uses it.',
            ),
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
                'search' => ['type' => 'String', 'description' => 'Partial match on the category name or URL key'],
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
    #[ApiProperty(description: 'Read: full image URL. Write: the name of a file under media/catalog/category, with no path, no ".." and no scheme ("" clears). POST /categories/{id}/image uploads a new file', extraProperties: ['computed' => true])]
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

    /** @var string[]|null Attribute codes with their own value in the ?store= store view; null without a store view context */
    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Attribute codes that have their own value in the ?store= store view, in the format that useDefault accepts; the other attributes inherit the default value. Set only on single-category reads and write responses with ?store=<store view code>, otherwise null; only visible to admin and API tokens', writable: false, security: "has_backend_access('categories')", extraProperties: ['computed' => true])]
    public ?array $storeOverrides = null;

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[Groups(['category:read'])]
    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    /** @var array<string, mixed> */
    #[Groups(['category:read'])]
    #[ApiProperty(description: 'Module-provided extension data')]
    #[\Override]
    public array $extensions = [];
}
