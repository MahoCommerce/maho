<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

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
    mahoLabel: 'Customer Groups',
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'CustomerGroup',
    description: 'Customer Group resource',
    provider: CustomerGroupProvider::class,
    processor: CustomerGroupProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/customer-groups/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/read')",
        ),
        new GetCollection(
            uriTemplate: '/customer-groups',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/read')",
        ),
        new Post(
            uriTemplate: '/customer-groups',
            processor: CustomerGroupProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/write')",
            description: 'Creates a new customer group',
        ),
        new Put(
            uriTemplate: '/customer-groups/{id}',
            processor: CustomerGroupProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/write')",
            description: 'Updates a customer group',
        ),
        new Delete(
            uriTemplate: '/customer-groups/{id}',
            processor: CustomerGroupProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/delete')",
            description: 'Deletes a customer group',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a customer group by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get customer groups',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/read')",
        ),
        new Query(
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/read')",
            name: 'customerGroup',
        ),
        new QueryCollection(
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-groups/read')",
            name: 'customerGroups',
        ),
    ],
)]
class CustomerGroup extends CrudResource
{
    public const MODEL = 'customer/group';

    public const PRIMARY_KEY = 'customer_group_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Customer_GroupController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Customer_GroupController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(extraProperties: ['modelField' => 'customer_group_code'])]
    public string $code = '';

    public ?int $taxClassId = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $taxClassName = null;

    /**
     * Enrich DTO with the resolved tax class name after model data is mapped.
     */
    public static function afterLoad(self $dto, object $model): void
    {
        if ($dto->taxClassId !== null) {
            $taxClass = \Mage::getModel('tax/class')->load($dto->taxClassId);
            $dto->taxClassName = $taxClass->getId() ? $taxClass->getClassName() : null;
        }
    }
}
