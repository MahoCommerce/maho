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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use ApiPlatform\Metadata\ApiProperty;

#[ApiResource(
    shortName: 'CmsBlock',
    description: 'CMS Block resource',
    provider: CmsBlockReader::class,
    operations: [
        new Get(uriTemplate: '/cms-blocks/{id}', security: 'true'),
        new GetCollection(uriTemplate: '/cms-blocks', security: 'true'),
        new Post(
            uriTemplate: '/cms-blocks',
            processor: CmsBlockWriter::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Creates a new CMS block',
        ),
        new Put(
            uriTemplate: '/cms-blocks/{id}',
            processor: CmsBlockWriter::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Updates a CMS block',
        ),
        new Delete(
            uriTemplate: '/cms-blocks/{id}',
            processor: CmsBlockWriter::class,
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
class CmsBlock
{
    public ?int $id = null;
    public string $identifier = '';
    public string $title = '';
    public ?string $content = null;
    public string $status = 'enabled';
    public bool $isActive = true;
    /** @var string[] */
    public array $stores = ['all'];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Module-provided extension data.
     * Populated via api_{resource}_dto_build event. Modules can append
     * arbitrary keyed data here without modifying core API resources.
     * @var array<string, mixed>
     */
    #[ApiProperty(description: 'Module-provided extension data')]
    public array $extensions = [];
}
