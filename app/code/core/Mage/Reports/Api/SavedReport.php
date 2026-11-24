<?php

/**
 * A saved report of the report explorer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'SavedReport',
    description: 'Saved reports of the report explorer. An administrator sees the own reports and the shared reports of the others',
    provider: SavedReportProvider::class,
    processor: SavedReportProcessor::class,
    denormalizationContext: ['allow_extra_attributes' => false, 'collect_denormalization_errors' => true],
    extraProperties: ['maho_mcp_section' => 'explorer'],
    operations: [
        new GetCollection(
            uriTemplate: '/reports/saved',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the saved reports, newest change first. An API key sees the shared reports only. Run one with the report tool and its request',
        ),
        new Get(
            uriTemplate: '/reports/saved/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get a saved report with its request',
        ),
        new Post(
            uriTemplate: '/reports/saved',
            read: true,
            security: "is_granted('ROLE_ADMIN')",
            description: 'Save a report for the administrator of the token. Required: name and request, which takes the keys of the report tool',
        ),
        // Before Put: the MCP update tool comes from the first update operation
        new Patch(
            uriTemplate: '/reports/saved/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Change some fields of an own saved report (JSON merge patch). A request in the body replaces the stored request',
        ),
        new Put(
            uriTemplate: '/reports/saved/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Replace an own saved report: a field that the body leaves out gets its default value. Use PATCH to change only some fields',
        ),
        new Delete(
            uriTemplate: '/reports/saved/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Delete an own saved report',
        ),
        new Post(
            uriTemplate: '/reports/saved/{id}/pin',
            name: self::OPERATION_PIN,
            requirements: ['id' => '\d+'],
            status: 200,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN')",
            description: 'Put a saved report, own or shared, on the dashboard of the administrator of the token. It takes no body',
        ),
        new Delete(
            uriTemplate: '/reports/saved/{id}/pin',
            name: self::OPERATION_UNPIN,
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Take a saved report off the dashboard of the administrator of the token',
        ),
    ],
    graphQlOperations: [],
)]
#[Map(target: \Mage_Reports_Model_SavedReport::class)]
class SavedReport extends \Maho\ApiPlatform\Resource
{
    public const string ADMIN_RESOURCE = 'report/explorer';

    public const string OPERATION_PIN = 'saved_report_pin';
    public const string OPERATION_UNPIN = 'saved_report_unpin';

    #[ApiProperty(identifier: true, writable: false, example: 3)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $id = null;

    #[ApiProperty(description: 'Name, at most 255 characters', example: 'Net sales by country')]
    #[Map]
    public ?string $name = null;

    #[ApiProperty(description: 'Whether the other administrators and the API keys see the report. Only its owner changes it', example: false)]
    #[Map]
    public bool $isShared = false;

    /** @var array<string, mixed> */
    #[ApiProperty(
        description: 'The report request, with the keys of the report tool: dataset, metrics, dimensions, filters, scope, dateField, range, from, to, compare, storeIds, sort and limit',
        example: ['dataset' => 'sales_orders', 'metrics' => ['orders', 'net_sales'], 'dimensions' => ['country'], 'range' => 'last_month'],
    )]
    #[Map(target: 'requestData', if: new TargetClass(\Mage_Reports_Model_SavedReport::class))]
    #[Map(source: 'requestData', if: new TargetClass(self::class))]
    public array $request = [];

    #[ApiProperty(writable: false, description: 'Whether the report is on the dashboard of the administrator of the token', example: false)]
    #[Map(if: new TargetClass(self::class))]
    public ?bool $isPinned = null;

    #[ApiProperty(writable: false, description: 'The ID of the administrator who owns the report', example: 1)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $adminUserId = null;

    #[ApiProperty(writable: false, description: 'Time of creation, in UTC', example: '2026-11-02 09:14:00')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false, description: 'Time of the last change, in UTC', example: '2026-11-02 09:14:00')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $updatedAt = null;
}
