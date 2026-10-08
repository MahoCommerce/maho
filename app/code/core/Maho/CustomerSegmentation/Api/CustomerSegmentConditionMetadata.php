<?php

/**
 * The condition types, operators and options that the conditions tree of a customer segment accepts.
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
use Maho\ApiPlatform\Metadata\McpToolResourceMetadataCollectionFactory;

// The customer-segments/read grant of CustomerSegment gives access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
    shortName: 'CustomerSegmentConditionMetadata',
    description: 'Condition types, operators and value options of customer segment trees',
    provider: CustomerSegmentConditionMetadataProvider::class,
    operations: [
        new Get(
            uriTemplate: '/customer-segments/condition-metadata',
            security: "is_granted('ROLE_ADMIN') or is_granted('customer-segments/read')",
            description: 'Get the document that describes the conditions tree of customer segments, with labels in the admin locale of the caller. Query: knownVersion (when it is equal to the current version, the response has only version, unchanged and scope)',
        ),
    ],
    graphQlOperations: [],
    extraProperties: [McpToolResourceMetadataCollectionFactory::RESOURCE_SECTION => 'Customers'],
)]
class CustomerSegmentConditionMetadata extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = CustomerSegment::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Hash of the document without the scope part')]
    public string $version = '';

    #[ApiProperty(description: 'Locale of the labels')]
    public string $locale = '';

    #[ApiProperty(description: 'True when the version is equal to the knownVersion query parameter. The response then has no other document parts than scope.')]
    public ?bool $unchanged = null;

    /** @var array<string, string> */
    #[ApiProperty(description: 'Root condition type of each tree: conditions')]
    public array $roots = [];

    /** @var array<string, array<string, mixed>> */
    #[ApiProperty(description: 'Description of each condition type: kind (combine or leaf), label, sentence, aggregator, value, attributes with their operators and options, children, valueless')]
    public array $types = [];

    /** @var array<string, list<array<string, mixed>>> */
    #[ApiProperty(description: 'Websites and stores that the caller can use, and all customer groups')]
    public array $scope = [];
}
