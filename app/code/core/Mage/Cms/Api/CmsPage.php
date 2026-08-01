<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

namespace Mage\Cms\Api;

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
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    mahoLabel: 'CMS Pages',
    mahoSection: 'Content',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'CmsPage',
    description: 'CMS Page resource',
    provider: CmsPageProvider::class,
    processor: CmsPageProcessor::class,
    // Page reads are public, so the design group is served only on the write
    // operations, which are gated on ROLE_ADMIN or cms-pages/write.
    normalizationContext: ['groups' => ['cmsPage:read']],
    operations: [
        new Get(uriTemplate: '/cms-pages/{id}', security: 'true'),
        new GetCollection(uriTemplate: '/cms-pages', security: 'true'),
        new Post(
            uriTemplate: '/cms-pages',
            processor: CmsPageProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('cms-pages/write')",
            description: 'Creates a new CMS page',
            normalizationContext: ['groups' => ['cmsPage:read', 'cmsPage:design']],
        ),
        new Put(
            uriTemplate: '/cms-pages/{id}',
            processor: CmsPageProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('cms-pages/write')",
            description: 'Updates a CMS page',
            normalizationContext: ['groups' => ['cmsPage:read', 'cmsPage:design']],
        ),
        new Delete(
            uriTemplate: '/cms-pages/{id}',
            processor: CmsPageProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('cms-pages/delete')",
            description: 'Deletes a CMS page',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a CMS page by ID', security: 'true'),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get CMS pages',
            security: 'true',
            extraArgs: [
                'createdFrom' => ['type' => 'String', 'description' => 'Created at or after this UTC date or datetime; a bare date means from 00:00:00'],
                'createdTo' => ['type' => 'String', 'description' => 'Created at or before this UTC date or datetime; a bare date includes the whole day'],
                'updatedSince' => ['type' => 'String', 'description' => 'Updated at or after this UTC date or datetime'],
                'identifier' => ['type' => 'String', 'description' => 'Exact identifier lookup (returns 0 or 1 page)'],
                'search' => ['type' => 'String', 'description' => 'Partial match on the page title or identifier, minimum 3 characters'],
            ],
        ),
    ],
)]
class CmsPage extends CrudResource
{
    public const MODEL = 'cms/page';

    /** Admin ACL gate. Backend PageController has no ADMIN_RESOURCE; declare directly. */
    public const ADMIN_RESOURCE = 'cms/page';

    #[Groups(['cmsPage:read'])]
    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    // Nullable so an omitted field on a partial update stays omitted: a non-null
    // default would be written back over the stored value (see CrudResource::applyToModel()).
    #[Groups(['cmsPage:read'])]
    public ?string $identifier = null;

    #[Groups(['cmsPage:read'])]
    public ?string $title = null;

    #[Groups(['cmsPage:read'])]
    public ?string $contentHeading = null;

    #[Groups(['cmsPage:read'])]
    public ?string $content = null;

    #[Groups(['cmsPage:read'])]
    public ?string $metaKeywords = null;

    #[Groups(['cmsPage:read'])]
    public ?string $metaDescription = null;

    #[Groups(['cmsPage:read'])]
    #[ApiProperty(extraProperties: ['modelField' => 'root_template'])]
    public ?string $pageLayout = null;

    #[Groups(['cmsPage:read'])]
    public ?int $sortOrder = null;

    #[Groups(['cmsPage:design'])]
    public ?string $layoutUpdateXml = null;

    #[Groups(['cmsPage:design'])]
    public ?string $customTheme = null;

    #[Groups(['cmsPage:design'])]
    public ?string $customRootTemplate = null;

    #[Groups(['cmsPage:design'])]
    public ?string $customLayoutUpdateXml = null;

    /** Date string (Y-m-d); empty string clears */
    #[Groups(['cmsPage:design'])]
    public ?string $customThemeFrom = null;

    /** Date string (Y-m-d); empty string clears */
    #[Groups(['cmsPage:design'])]
    public ?string $customThemeTo = null;

    /** One of INDEX,FOLLOW / NOINDEX,FOLLOW / INDEX,NOFOLLOW / NOINDEX,NOFOLLOW; empty string clears */
    #[Groups(['cmsPage:read'])]
    public ?string $metaRobots = null;

    #[Groups(['cmsPage:read'])]
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = 'enabled';

    #[Groups(['cmsPage:read'])]
    public ?bool $isActive = null;

    /** @var int[]|null */
    #[Groups(['cmsPage:read'])]
    public ?array $stores = null;

    #[Groups(['cmsPage:read'])]
    #[ApiProperty(writable: false, extraProperties: ['modelField' => 'creation_time'])]
    public ?string $createdAt = null;

    #[Groups(['cmsPage:read'])]
    #[ApiProperty(writable: false, extraProperties: ['modelField' => 'update_time'])]
    public ?string $updatedAt = null;

    /**
     * Enrich DTO with computed fields after model data is mapped.
     */
    public static function afterLoad(self $dto, object $model): void
    {
        $dto->content = self::filterContent($dto->content ?? '');
        $dto->status = ($dto->isActive ?? false) ? 'enabled' : 'disabled';

        // The resource model re-formats these with a time part before saving, so
        // MySQL/Postgres date columns and SQLite text columns read back differently.
        $dto->customThemeFrom = $dto->customThemeFrom ? substr($dto->customThemeFrom, 0, 10) : null;
        $dto->customThemeTo = $dto->customThemeTo ? substr($dto->customThemeTo, 0, 10) : null;

        if (method_exists($model->getResource(), 'lookupStoreIds')) {
            $dto->stores = array_map('intval', $model->getResource()->lookupStoreIds($model->getId()));
        }
    }
}
