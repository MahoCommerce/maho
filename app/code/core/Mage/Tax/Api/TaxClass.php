<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoLabel: 'Tax Classes',
    mahoSection: 'Tax',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'TaxClass',
    description: 'Tax Class resource',
    provider: TaxClassProvider::class,
    processor: TaxClassProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/tax-classes/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/read')",
        ),
        new GetCollection(
            uriTemplate: '/tax-classes',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/read')",
        ),
        new Post(
            uriTemplate: '/tax-classes',
            processor: TaxClassProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/write')",
            description: 'Creates a new tax class',
        ),
        new Put(
            uriTemplate: '/tax-classes/{id}',
            processor: TaxClassProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/write')",
            description: 'Updates a tax class',
        ),
        new Delete(
            uriTemplate: '/tax-classes/{id}',
            processor: TaxClassProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/delete')",
            description: 'Deletes a tax class',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a tax class by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get tax classes',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/read')",
        ),
        new Query(
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/read')",
            name: 'taxClass',
        ),
        new QueryCollection(
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-classes/read')",
            name: 'taxClasses',
        ),
    ],
)]
class TaxClass extends CrudResource
{
    public const MODEL = 'tax/class';

    public const PRIMARY_KEY = 'class_id';

    /**
     * Admin ACL gate. Both Customer and Product tax class admin pages live under
     * sales/tax (Mage_Adminhtml_Tax_ClassController declares no ADMIN_RESOURCE of
     * its own), so gate the unified resource at the parent tax node.
     */
    public const ADMIN_RESOURCE = 'sales/tax';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(extraProperties: ['modelField' => 'class_name'])]
    public string $className = '';

    #[ApiProperty(extraProperties: ['modelField' => 'class_type'])]
    public string $classType = 'PRODUCT';
}
