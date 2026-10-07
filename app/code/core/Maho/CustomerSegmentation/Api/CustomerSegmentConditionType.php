<?php

/**
 * A condition type that the conditions tree of a customer segment accepts, with its attributes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use Maho\ApiPlatform\Metadata\McpToolResourceMetadataCollectionFactory;

// The customer-segments/read grant of CustomerSegment gives access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
    shortName: 'CustomerSegmentConditionType',
    description: 'Condition types of customer segment trees, with labels in the admin locale of the caller',
    provider: CustomerSegmentConditionTypeProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/customer-segments/condition-types',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
            description: 'List the condition types of segment trees, each with the code and label of its attributes. Get one type for its operators and values',
        ),
        new Get(
            uriTemplate: '/customer-segments/condition-types/{code}',
            requirements: ['code' => '[a-z_]+'],
            uriVariables: ['code' => new Link(fromClass: self::class, identifiers: ['code'])],
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
            description: 'Get one condition type of segment trees: each attribute with its input type, its operators and its value options',
        ),
    ],
    graphQlOperations: [],
    extraProperties: [McpToolResourceMetadataCollectionFactory::RESOURCE_SECTION => 'Customers'],
)]
class CustomerSegmentConditionType extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = CustomerSegment::ADMIN_RESOURCE;

    /** The type of a tree node is this prefix and the code. */
    public const TYPE_PREFIX = 'customersegmentation/segment_condition_';

    #[ApiProperty(identifier: true, writable: false, description: 'Short code of the type, for example customer_clv', example: 'customer_clv')]
    public string $code = '';

    #[ApiProperty(writable: false, description: 'The type of a tree node, for example customersegmentation/segment_condition_customer_clv', example: 'customersegmentation/segment_condition_customer_clv')]
    public string $type = '';

    #[ApiProperty(writable: false, description: 'combine (a node with child conditions) or leaf', example: 'leaf')]
    public string $kind = '';

    #[ApiProperty(writable: false, description: 'The group of the type in the condition list of the admin, such as Order History', example: 'Customer Lifetime Value')]
    public ?string $label = null;

    /** @var list<array<string, mixed>> */
    #[ApiProperty(writable: false, description: 'The attributes of the type. A list gives code and label. One type also gives inputType, operators and options')]
    public array $attributes = [];

    /** @var array<string, mixed>|null */
    #[ApiProperty(writable: false, description: 'A combine only: its aggregator and value options. One type only')]
    public ?array $combine = null;
}
