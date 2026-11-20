<?php

/**
 * The monthly target of a metric of the report explorer.
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
use Maho\ApiPlatform\Metadata\ListFilter;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'ReportTarget',
    description: 'Monthly targets of the metrics of the report explorer, such as the net sales of each month. A report by the period with no other grouping and no filter shows its targets',
    provider: ReportTargetProvider::class,
    processor: ReportTargetProcessor::class,
    denormalizationContext: ['allow_extra_attributes' => false, 'collect_denormalization_errors' => true],
    extraProperties: ['maho_mcp_section' => 'explorer'],
    operations: [
        new GetCollection(
            uriTemplate: '/reports/targets',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the targets of a dataset, newest month first',
            parameters: [
                'dataset' => new ListFilter('The dataset code, such as sales_orders'),
                'metric' => new ListFilter('Only the targets of this metric code'),
            ],
        ),
        new Get(
            uriTemplate: '/reports/targets/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get a target',
        ),
        new Post(
            uriTemplate: '/reports/targets',
            read: true,
            security: "is_granted('ROLE_ADMIN')",
            description: 'Set the target of a metric for one month. Required: dataset, metric, month and value. A target for the same metric, store view and month replaces the old one. '
                . 'The metric must add up over the days, such as net_sales or orders, not an average',
        ),
        new Patch(
            uriTemplate: '/reports/targets/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Change a target (JSON merge patch)',
        ),
        new Put(
            uriTemplate: '/reports/targets/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Replace a target',
        ),
        new Delete(
            uriTemplate: '/reports/targets/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Delete a target',
        ),
    ],
    graphQlOperations: [],
)]
#[Map(target: \Mage_Reports_Model_Target::class)]
class ReportTarget extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/explorer';

    #[ApiProperty(identifier: true, writable: false, example: 2)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $id = null;

    #[ApiProperty(description: 'The dataset code', example: 'sales_orders')]
    #[Map]
    public ?string $dataset = null;

    #[ApiProperty(description: 'The metric code. It must add up over the days, such as a sum or a count', example: 'net_sales')]
    #[Map]
    public ?string $metric = null;

    #[ApiProperty(description: 'The month, YYYY-MM', example: '2026-12')]
    #[Map]
    public ?string $month = null;

    #[ApiProperty(description: 'One store view, or 0 for all the store views', example: 0)]
    #[Map]
    public int $storeId = 0;

    #[ApiProperty(description: 'The target value for the month, in the global currency for an amount', example: 30000)]
    #[Map]
    public ?float $value = null;
}
