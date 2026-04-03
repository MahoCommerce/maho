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
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Service\StoreContext;

#[ApiResource(
    shortName: 'CmsPage',
    description: 'CMS Page resource',
    provider: CmsPageProvider::class,
    processor: CmsPageProcessor::class,
    operations: [
        new Get(uriTemplate: '/cms-pages/{id}', security: 'true'),
        new GetCollection(uriTemplate: '/cms-pages', security: 'true'),
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
        new Query(name: 'cmsPage'),
        new QueryCollection(name: 'cmsPages'),
        new QueryCollection(
            name: 'cmsPagesByIdentifier',
            args: ['identifier' => ['type' => 'String!']],
        ),
    ],
)]
class CmsPage extends CrudResource
{
    public const MODEL = 'cms/page';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public string $identifier = '';
    public string $title = '';
    public ?string $contentHeading = null;
    public ?string $content = null;
    public ?string $metaKeywords = null;
    public ?string $metaDescription = null;

    #[ApiProperty(extraProperties: ['modelField' => 'root_template'])]
    public ?string $pageLayout = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = 'enabled';

    public bool $isActive = true;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    /** @var string[] */
    public array $stores = ['all'];

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
            $storeIds = $model->getResource()->lookupStoreIds($model->getId());
            $dto->stores = StoreContext::storeIdsToStoreCodes($storeIds);
        }
    }
}
