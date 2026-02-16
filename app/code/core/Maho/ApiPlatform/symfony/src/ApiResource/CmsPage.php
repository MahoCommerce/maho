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
use Maho\ApiPlatform\State\Processor\CmsPageProcessor;
use Maho\ApiPlatform\State\Provider\CmsPageProvider;

#[ApiResource(
    shortName: 'CmsPage',
    description: 'CMS Page resource',
    provider: CmsPageProvider::class,
    operations: [
        new Get(uriTemplate: '/cms-pages/{id}'),
        new GetCollection(uriTemplate: '/cms-pages'),
        new Post(
            uriTemplate: '/cms-pages',
            processor: CmsPageProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Creates a new CMS page',
        ),
        new Put(
            uriTemplate: '/cms-pages/{id}',
            processor: CmsPageProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Updates a CMS page',
        ),
        new Delete(
            uriTemplate: '/cms-pages/{id}',
            processor: CmsPageProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Deletes a CMS page',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query'),
        new QueryCollection(name: 'collection_query'),
        new QueryCollection(
            name: 'cmsPages',
            args: ['identifier' => ['type' => 'String'], 'isFooterLink' => ['type' => 'Boolean']],
        ),
    ],
)]
class CmsPage
{
    public ?int $id = null;
    public string $identifier = '';
    public string $title = '';
    public ?string $contentHeading = null;
    public ?string $content = null;
    public ?string $metaKeywords = null;
    public ?string $metaDescription = null;
    public ?string $rootTemplate = null;
    public string $status = 'enabled';
    public bool $isActive = true;
    /** @var string[] */
    public array $stores = ['all'];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
