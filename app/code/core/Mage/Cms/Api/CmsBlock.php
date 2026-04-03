<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Cms\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
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
