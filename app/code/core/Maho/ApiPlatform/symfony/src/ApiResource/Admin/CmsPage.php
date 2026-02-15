<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\ApiResource\Admin;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\State\Admin\CmsPageProcessor;
use Maho\ApiPlatform\State\Admin\CmsPageProvider;

#[ApiResource(
    uriTemplate: '/admin/cms-pages',
    shortName: 'AdminCmsPage',
    operations: [
        new Post(
            processor: CmsPageProcessor::class,
            description: 'Creates a new CMS page with content sanitization',
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
#[ApiResource(
    uriTemplate: '/admin/cms-pages/{id}',
    shortName: 'AdminCmsPage',
    provider: CmsPageProvider::class,
    operations: [
        new Put(
            processor: CmsPageProcessor::class,
            description: 'Updates an existing CMS page with content sanitization',
        ),
        new Delete(
            processor: CmsPageProcessor::class,
            description: 'Deletes a CMS page',
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
class CmsPage
{
    public ?int $id = null;
    public string $identifier = '';
    public string $title = '';
    public ?string $contentHeading = null;
    public string $content = '';
    public ?string $metaKeywords = null;
    public ?string $metaDescription = null;
    public bool $isActive = true;
    /** @var string[] */
    public array $stores = ['all'];
    /**
     * Page layout template: one_column, two_columns_left, two_columns_right, three_columns, empty
     */
    public ?string $rootTemplate = null;
}
