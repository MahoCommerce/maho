<?php

/**
 * A customer segment: the customers that match its conditions, such as the customers who spent the most.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use Maho\Config\ApiResource;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
    mahoLabel: 'Customer Segments',
    mahoSection: 'Customers',
    mahoOperations: ['read' => 'View', 'write' => 'Create, Update & Refresh', 'delete' => 'Delete'],
    shortName: 'CustomerSegment',
    description: 'Customer segments: groups of customers by behavior, such as the customers who spent the most, who did not order for a long time, or who left a cart',
    provider: CustomerSegmentProvider::class,
    processor: CustomerSegmentProcessor::class,
    denormalizationContext: ['allow_extra_attributes' => false, 'collect_denormalization_errors' => true],
    operations: [
        new GetCollection(
            uriTemplate: '/customer-segments',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
            description: 'List the customer segments by priority and name. A group of customers by behavior, such as "VIP", "loyal" or "inactive" customers, is a segment, not a customer group: look here first',
        ),
        new Get(
            uriTemplate: '/customer-segments/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
            description: 'Get a customer segment with its conditions tree. matchedCustomersCount is the count of the last refresh',
        ),
        new Post(
            uriTemplate: '/customer-segments',
            // The provider gives the new segment with its default values, and the body fills it
            read: true,
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/write')",
            description: 'Create a customer segment. Required: name and websiteIds. Build the conditions tree from the condition metadata. '
                . 'Saving a segment does not find its customers: call POST /customer-segments/{id}/refresh afterwards',
        ),
        // Before Put: the MCP update tool comes from the first update operation, and an assistant changes one field at a time
        new Patch(
            uriTemplate: '/customer-segments/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/write')",
            description: 'Change some fields of a customer segment (JSON merge patch): only the fields in the body change. A conditions tree in the body replaces the stored tree, and null resets it to an empty tree. '
                . 'Call POST /customer-segments/{id}/refresh afterwards to find the customers again',
        ),
        new Put(
            uriTemplate: '/customer-segments/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/write')",
            description: 'Replace a customer segment: a field that the body leaves out gets its default value, and a missing conditions tree becomes an empty tree. Use PATCH to change only some fields',
        ),
        new Delete(
            uriTemplate: '/customer-segments/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/delete')",
            description: 'Delete a customer segment. Price rules and email sequences that use it stop matching its customers',
        ),
        new Post(
            uriTemplate: '/customer-segments/{id}/refresh',
            name: 'refresh_customer_segment',
            requirements: ['id' => '\d+'],
            status: 200,
            deserialize: false,
            openapi: new OpenApiOperation(summary: 'Refreshes the CustomerSegment resource.'),
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/write')",
            description: 'Find the customers of a segment again from its conditions, as the Refresh button of the admin does, and return the segment with the new matchedCustomersCount. '
                . 'An inactive segment gets no customers. It takes no body. List the customers with the segment customers tool',
        ),
    ],
    graphQlOperations: [],
)]
#[Map(target: \Maho_CustomerSegmentation_Model_Segment::class)]
class CustomerSegment extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'customer/customersegmentation';

    /** The ACL resources that the segment admin pages check for each action. */
    public const ACL_MANAGE = 'customer/customersegmentation/manage';
    public const ACL_SAVE = 'customer/customersegmentation/save';
    public const ACL_DELETE = 'customer/customersegmentation/delete';
    public const ACL_REFRESH = 'customer/customersegmentation/refresh';

    public const ROOT_CONDITIONS = 'customersegmentation/segment_condition_combine';

    private const TREE_SCHEMA = [
        'type' => 'object',
        'description' => 'Condition tree. A node has the keys type, attribute, operator, value, aggregator and conditions (a list of child nodes), and the read-only key label. '
            . 'The root has the type customersegmentation/segment_condition_combine, the aggregator all or any, and the value true (the conditions are true) or false (they are false). '
            . 'A leaf has a type and an attribute, for example the type customersegmentation/segment_condition_customer_clv with the attribute lifetime_sales, number_of_orders or average_order_value, '
            . 'or the type customersegmentation/segment_condition_customer_timebased with days_since_last_order. The condition metadata lists every type with its attributes, operators and values. '
            . 'Operators are ==, !=, >=, <=, >, <, {} (contains), !{} (does not contain), () (is one of) and !() (is not one of).',
        'properties' => [
            'type' => ['type' => 'string'],
            'aggregator' => ['type' => 'string', 'enum' => ['all', 'any']],
            'attribute' => ['type' => 'string'],
            'operator' => ['type' => 'string'],
            'value' => ['description' => 'Boolean for a combine, a string, or a list of strings for list operators'],
            'conditions' => ['type' => 'array', 'items' => ['type' => 'object', 'description' => 'A child node with the same keys']],
            'label' => ['type' => 'string', 'readOnly' => true],
        ],
    ];

    private const TREE_EXAMPLE = [
        'type' => self::ROOT_CONDITIONS,
        'aggregator' => 'all',
        'value' => true,
        'conditions' => [[
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'lifetime_sales',
            'operator' => '>=',
            'value' => '1000',
        ]],
    ];

    #[ApiProperty(identifier: true, writable: false, example: 4)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $id = null;

    #[ApiProperty(description: 'Name, at most 255 characters', example: 'Top spenders')]
    #[Map]
    public ?string $name = null;

    #[ApiProperty(example: 'Customers who spent more than 1000 in total')]
    #[Map]
    public ?string $description = null;

    #[ApiProperty(description: 'Only an active segment has customers, and only an active segment applies in price rules and email sequences', example: true)]
    #[Map]
    public bool $isActive = true;

    /** @var list<int> */
    #[ApiProperty(description: 'The websites whose customers the segment can hold', example: [1])]
    #[Map]
    public array $websiteIds = [];

    /** @var list<int> */
    #[ApiProperty(description: 'Only customers of these groups. Empty: every customer group', example: [1, 2])]
    #[Map]
    public array $customerGroupIds = [];

    #[ApiProperty(description: 'auto: a daily job finds the customers again. manual: only a refresh does', example: 'auto')]
    #[Map]
    public string $refreshMode = \Maho_CustomerSegmentation_Model_Segment::MODE_AUTO;

    #[ApiProperty(description: 'Segments with a higher priority come first, 0 or more', example: 10)]
    #[Map]
    public int $priority = 0;

    #[ApiProperty(description: 'Whether the email sequences of the segment run when a customer enters or leaves it. The segment needs at least one email sequence, which the admin manages', example: false)]
    #[Map]
    public bool $autoEmailActive = false;

    #[ApiProperty(description: 'Whether a customer can be in more than one email sequence of the segment at the same time', example: false)]
    #[Map]
    public bool $allowOverlappingSequences = false;

    #[ApiProperty(writable: false, description: 'Count of the customers that the last refresh found', example: 42)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $matchedCustomersCount = null;

    #[ApiProperty(writable: false, description: 'State of the last refresh: pending (never refreshed), processing, completed or error', example: 'completed')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $refreshStatus = null;

    #[ApiProperty(writable: false, description: 'Time of the last refresh, in UTC', example: '2026-10-07 05:00:12')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $lastRefreshAt = null;

    /** @var array<string, mixed>|null */
    #[ApiProperty(description: 'Conditions tree: the customers that belong to the segment', openapiContext: self::TREE_SCHEMA, example: self::TREE_EXAMPLE)]
    #[Map(transform: CustomerSegmentConditionsTransform::class)]
    public ?array $conditions = null;
}
