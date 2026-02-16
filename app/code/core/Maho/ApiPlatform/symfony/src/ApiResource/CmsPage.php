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
use Maho\ApiPlatform\State\Provider\CmsPageProvider;

#[ApiResource(
    shortName: 'CmsPage',
    description: 'CMS Page resource',
    provider: CmsPageProvider::class,
    operations: [
        new Get(
            uriTemplate: '/cms-pages/{id}',
            description: 'Get a CMS page by ID',
        ),
        new GetCollection(
            uriTemplate: '/cms-pages',
            description: 'Get CMS page collection',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a CMS page by ID'),
        new QueryCollection(name: 'collection_query', description: 'Get CMS pages'),
        new QueryCollection(
            name: 'cmsPages',
            args: ['identifier' => ['type' => 'String'], 'isFooterLink' => ['type' => 'Boolean']],
            description: 'Get CMS pages, optionally filter by identifier',
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
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
