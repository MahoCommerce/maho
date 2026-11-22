<?php

/**
 * Explain the change of a metric of the report explorer between two periods.
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
use Maho\ApiPlatform\Metadata\ListFilter;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'ExplorerExplanation',
    description: 'Which values of each dimension caused the change of a metric between two periods',
    provider: ExplorerExplanationProvider::class,
    extraProperties: ['maho_mcp_section' => 'explorer'],
    operations: [
        new GetCollection(
            uriTemplate: '/reports/explore/explain',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Explain why a metric went up or down: for each dimension of the dataset, the values with the biggest change between the period and the earlier period, '
                . 'with their share of the total change when the values add up to the total. Use it for questions such as "why did net sales drop last month". '
                . 'Without compare, the earlier period is the period before.',
            parameters: [
                'metric' => new ListFilter('The metric code to explain, such as net_sales'),
                'preset' => new ListFilter('The code of a ready-made report. The other parameters change it'),
                'dataset' => new ListFilter('The code of the dataset, such as sales_orders'),
                'filters' => new ListFilter('A JSON list of filters, each {"field": code, "operator": eq|neq|in|nin|gt|gte|lt|lte|contains|empty|not_empty, "value": value or list}'),
                'scope' => new ListFilter('The orders that count: placed (the default), not_canceled or all', enum: ['placed', 'not_canceled', 'all']),
                'dateField' => new ListFilter('The date field of the period, such as created_at or updated_at'),
                'range' => new ListFilter('A named period', enum: ['today', 'yesterday', 'last_7_days', 'last_30_days', 'last_90_days', 'this_month', 'last_month', 'this_year', 'last_year', 'last_12_months']),
                'from' => new ListFilter('The first local day, YYYY-MM-DD, with to and without range'),
                'to' => new ListFilter('The last local day, YYYY-MM-DD, with from and without range'),
                'storeIds' => new ListFilter('Comma-separated store view IDs'),
                'compare' => new ListFilter('The earlier period: previous_period (the default) or previous_year', enum: ['previous_period', 'previous_year']),
                'explainDimensions' => new ListFilter('Comma-separated dimension codes to read. Without it, the dimensions that explain well'),
                'top' => new ListFilter('Values for each dimension, 5 by default, at most 20', 'integer'),
            ],
        ),
    ],
    graphQlOperations: [],
)]
class ExplorerExplanation extends \Maho\ApiPlatform\Resource
{
    public const string ADMIN_RESOURCE = 'report/explorer';

    #[ApiProperty(identifier: true, description: 'Dataset code')]
    public string $dataset = '';
}
