<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Maho\ApiPlatform\Metadata\McpToolResourceMetadataCollectionFactory;

// The customer-segments/read grant of CustomerSegment gives access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
    shortName: 'CustomerSegmentConditionValueOption',
    description: 'Value options of customer segment conditions',
    provider: CustomerSegmentConditionValueOptionProvider::class,
    operations: [
        new Get(
            uriTemplate: '/customer-segments/condition-value-options',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
            description: 'Get a page of the value options of an attribute of a condition type in the condition metadata. Query: type and attribute (required), search, page, itemsPerPage (at most 100), values (labels of these comma-separated values), parentId (the child categories of a category, for the category chooser)',
        ),
    ],
    graphQlOperations: [],
    extraProperties: [McpToolResourceMetadataCollectionFactory::RESOURCE_SECTION => 'Customers'],
)]
class CustomerSegmentConditionValueOption extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = CustomerSegment::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Condition type')]
    public string $type = '';

    #[ApiProperty(description: 'Attribute code')]
    public string $attribute = '';

    /** @var list<array<string, mixed>> */
    #[ApiProperty(description: 'Options: value, label, and group for an option of a group. Categories also have path and hasChildren')]
    public array $items = [];

    public int $totalItems = 0;

    public int $page = 1;

    public int $itemsPerPage = 20;
}
