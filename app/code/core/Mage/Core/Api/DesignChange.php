<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Design Schedule',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'DesignChange',
    description: 'Scheduled design change: applies a theme ("package/theme") to one store view for a date range. A change with no dates is permanent. Two changes for the same store view must not overlap. Use the themes resource to find the valid design values.',
    provider: DesignChangeProvider::class,
    processor: DesignChangeProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/design-changes/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('design-changes/read')",
            description: 'Get a scheduled design change by id',
        ),
        new GetCollection(
            uriTemplate: '/design-changes',
            security: "is_granted('ROLE_ADMIN') or is_granted('design-changes/read')",
            description: 'List scheduled design changes for every store view',
        ),
        new Post(
            uriTemplate: '/design-changes',
            processor: DesignChangeProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('design-changes/write')",
            description: 'Schedule a design change. storeId and design are required. dateFrom and dateTo are Y-m-d dates; omit both for a permanent change.',
        ),
        new Put(
            uriTemplate: '/design-changes/{id}',
            requirements: ['id' => '\d+'],
            processor: DesignChangeProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('design-changes/write')",
            description: 'Update a scheduled design change. Only the fields in the request body change. Send dateFrom or dateTo as null to remove that bound.',
        ),
        new Delete(
            uriTemplate: '/design-changes/{id}',
            requirements: ['id' => '\d+'],
            processor: DesignChangeProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('design-changes/delete')",
            description: 'Delete a scheduled design change',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a scheduled design change by id',
            security: "is_granted('ROLE_ADMIN') or is_granted('design-changes/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List scheduled design changes',
            security: "is_granted('ROLE_ADMIN') or is_granted('design-changes/read')",
            extraArgs: [
                'storeId' => ['type' => 'Int', 'description' => 'Only changes for this store view id'],
                'design' => ['type' => 'String', 'description' => 'Only changes that apply this theme, as "package/theme"'],
            ],
        ),
    ],
)]
class DesignChange extends CrudResource
{
    public const MODEL = 'core/design';
    public const PRIMARY_KEY = 'design_change_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_System_DesignController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_System_DesignController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(description: 'Store view id the change applies to')]
    public ?int $storeId = null;

    #[ApiProperty(description: 'Theme to apply, as "package/theme" (for example "base/default")')]
    public ?string $design = null;

    #[ApiProperty(description: 'First day the theme applies, as Y-m-d. Null means no start bound.')]
    public ?string $dateFrom = null;

    #[ApiProperty(description: 'Last day the theme applies, as Y-m-d. Null means no end bound.')]
    public ?string $dateTo = null;

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->dateFrom = self::dateOnly($dto->dateFrom);
        $dto->dateTo = self::dateOnly($dto->dateTo);
    }

    private static function dateOnly(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }
}
