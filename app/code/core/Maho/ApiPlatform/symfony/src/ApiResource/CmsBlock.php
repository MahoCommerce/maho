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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\ApiPlatform\State\Processor\CmsBlockProcessor;
use Maho\ApiPlatform\State\Provider\CmsBlockProvider;

#[ApiResource(
    shortName: 'CmsBlock',
    description: 'CMS Block resource',
    provider: CmsBlockProvider::class,
    operations: [
        new Get(uriTemplate: '/cms-blocks/{id}'),
        new GetCollection(uriTemplate: '/cms-blocks'),
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
}
