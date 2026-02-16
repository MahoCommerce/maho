<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\ApiResource\Admin;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\State\Admin\CmsBlockProcessor;
use Maho\ApiPlatform\State\Admin\CmsBlockProvider;

#[ApiResource(
    uriTemplate: '/admin/cms-blocks',
    shortName: 'AdminCmsBlock',
    operations: [
        new GetCollection(
            provider: CmsBlockProvider::class,
            description: 'Lists all CMS blocks',
        ),
        new Post(
            processor: CmsBlockProcessor::class,
            description: 'Creates a new static block with content sanitization',
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
#[ApiResource(
    uriTemplate: '/admin/cms-blocks/{id}',
    shortName: 'AdminCmsBlock',
    provider: CmsBlockProvider::class,
    operations: [
        new Get(
            description: 'Gets a single CMS block',
        ),
        new Put(
            processor: CmsBlockProcessor::class,
            description: 'Updates an existing static block with content sanitization',
        ),
        new Delete(
            processor: CmsBlockProcessor::class,
            description: 'Deletes a static block',
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
class CmsBlock
{
    public ?int $id = null;
    public string $identifier = '';
    public string $title = '';
    public string $content = '';
    public bool $isActive = true;
    /** @var string[] */
    public array $stores = ['all'];
}
