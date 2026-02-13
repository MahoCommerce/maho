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
use Maho\ApiPlatform\State\Provider\CmsBlockProvider;

#[ApiResource(
    shortName: 'CmsBlock',
    description: 'CMS Block resource',
    provider: CmsBlockProvider::class,
    operations: [
        new Get(
            uriTemplate: '/cms-blocks/{id}',
            description: 'Get a CMS block by ID',
        ),
        new GetCollection(
            uriTemplate: '/cms-blocks',
            description: 'Get CMS block collection',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'cmsBlock', description: 'Get a CMS block by ID'),
        new QueryCollection(name: 'cmsBlocks', description: 'Get CMS blocks'),
        new Query(
            name: 'cmsBlockByIdentifier',
            args: ['identifier' => ['type' => 'String!']],
            description: 'Get a CMS block by identifier',
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
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
