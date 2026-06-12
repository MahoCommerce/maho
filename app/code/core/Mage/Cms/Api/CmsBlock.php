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
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;

#[ApiResource(
    mahoLabel: 'CMS Blocks',
    mahoSection: 'Content',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'CmsBlock',
    description: 'CMS Block resource',
    provider: CmsBlockProvider::class,
    processor: CmsBlockProcessor::class,
    operations: [
        new Get(uriTemplate: '/cms-blocks/{id}', security: 'true'),
        new GetCollection(uriTemplate: '/cms-blocks', security: 'true'),
        new Post(
            uriTemplate: '/cms-blocks',
            processor: CmsBlockProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Creates a new CMS block',
        ),
        new Put(
            uriTemplate: '/cms-blocks/{id}',
            processor: CmsBlockProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Updates a CMS block',
        ),
        new Delete(
            uriTemplate: '/cms-blocks/{id}',
            processor: CmsBlockProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Deletes a CMS block',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a CMS block by ID', security: 'true'),
        new QueryCollection(name: 'collection_query', description: 'Get CMS blocks', security: 'true'),
        new Query(name: 'cmsBlock'),
        new QueryCollection(name: 'cmsBlocks'),
        new Query(
            name: 'cmsBlockByIdentifier',
            args: ['identifier' => ['type' => 'String!']],
            resolver: CustomQueryResolver::class,
        ),
    ],
)]
class CmsBlock extends CrudResource
{
    public const MODEL = 'cms/block';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Cms_BlockController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Cms_BlockController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public string $identifier = '';
    public string $title = '';
    public ?string $content = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = 'enabled';

    public bool $isActive = true;

    /** @var int[] */
    public array $stores = [0];

    #[ApiProperty(writable: false, extraProperties: ['modelField' => 'creation_time'])]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false, extraProperties: ['modelField' => 'update_time'])]
    public ?string $updatedAt = null;

    /**
     * Enrich DTO with computed fields after model data is mapped.
     */
    public static function afterLoad(self $dto, object $model): void
    {
        $dto->content = self::filterContent($dto->content ?? '');
        $dto->status = $dto->isActive ? 'enabled' : 'disabled';

        if (method_exists($model->getResource(), 'lookupStoreIds')) {
            $dto->stores = array_map('intval', $model->getResource()->lookupStoreIds($model->getId()));
        }
    }
}
