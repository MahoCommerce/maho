<?php

/**
 * A customer that the last refresh of a segment found.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use Maho\ApiPlatform\Metadata\McpToolResourceMetadataCollectionFactory;

// The customer-segments/read grant of CustomerSegment gives access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
    shortName: 'CustomerSegmentCustomer',
    description: 'Customers of a customer segment',
    provider: CustomerSegmentCustomerProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/customer-segments/{segmentId}/customers',
            uriVariables: ['segmentId' => new Link(fromClass: CustomerSegment::class, identifiers: ['id'])],
            requirements: ['segmentId' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
            description: 'List the customers of a segment, as its last refresh found them, by customer ID. Refresh the segment first when its conditions changed or lastRefreshAt is old',
        ),
    ],
    graphQlOperations: [],
    extraProperties: [McpToolResourceMetadataCollectionFactory::RESOURCE_SECTION => 'Customers'],
)]
class CustomerSegmentCustomer extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = CustomerSegment::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false, description: 'Customer ID')]
    public int $id = 0;

    #[ApiProperty(writable: false)]
    public ?string $email = null;

    #[ApiProperty(writable: false)]
    public ?string $firstname = null;

    #[ApiProperty(writable: false)]
    public ?string $lastname = null;

    #[ApiProperty(writable: false)]
    public ?int $groupId = null;

    #[ApiProperty(writable: false, description: 'The website of the membership')]
    public ?int $websiteId = null;

    #[ApiProperty(writable: false, description: 'Time when a refresh added the customer to the segment, in UTC')]
    public ?string $addedAt = null;
}
