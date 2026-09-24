<?php

/**
 * The statistics of the aggregated reports: the time of the last refresh of each report, and the refresh.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;

// The grants of ReportsPermission give access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'ReportStatistic',
    description: 'Statistics of the aggregated reports',
    provider: ReportStatisticsProvider::class,
    processor: ReportStatisticsProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/reports/statistics',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the aggregated reports with the time of the last refresh of their statistics (updatedAt, null when they were never refreshed). Response: totalItems and member',
        ),
        new Post(
            uriTemplate: '/reports/statistics/refresh',
            name: 'report_statistics_refresh',
            status: 200,
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/refresh')",
            description: 'Refresh the statistics of the aggregated reports. Body: mode (recent or lifetime, default recent), reports (list of codes, empty or absent for all). '
                . 'recent aggregates the orders of the last 25 hours again and responds 200 when it is done. '
                . 'lifetime empties the tables and aggregates all orders again. It puts one message per report in the queue and responds 202, '
                . 'or runs at once and responds 200 when the queue is disabled. A report that is already in the queue is not added again. '
                . 'Response: mode, queued, reports, alreadyQueued, totalItems and member (the statistics after the request). '
                . 'A token with a store restriction cannot refresh statistics.',
            openapi: new OpenApiOperation(
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'mode' => ['type' => 'string', 'enum' => ['recent', 'lifetime'], 'default' => 'recent'],
                                    'reports' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string', 'enum' => ['sales', 'tax', 'shipping', 'invoiced', 'refunded', 'coupons', 'bestsellers', 'viewed']],
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
            extraProperties: ['maho_mcp' => false],
        ),
    ],
    graphQlOperations: [],
)]
class ReportStatistic extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Report_StatisticsController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Report code: sales, tax, shipping, invoiced, refunded, coupons, bestsellers or viewed')]
    public string $code = '';

    #[ApiProperty(description: 'Name of the report')]
    public string $label = '';

    #[ApiProperty(description: 'Description of the report')]
    public string $description = '';

    #[ApiProperty(description: 'Time of the last refresh in UTC, ISO 8601, or null')]
    public ?string $updatedAt = null;
}
