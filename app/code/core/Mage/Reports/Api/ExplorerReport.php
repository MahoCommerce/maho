<?php

/**
 * Run a report of the report explorer.
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
use ApiPlatform\Metadata\Query as HttpQuery;
use Maho\ApiPlatform\Metadata\ListFilter;

// The reports/read grant of ReportsPermission gives access, so this uses the plain API Platform attribute
#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'ExplorerReport',
    description: 'Reports of the report explorer, read live from the order, item, cart, customer and stock tables',
    provider: ExplorerReportProvider::class,
    extraProperties: ['maho_mcp_section' => 'explorer'],
    operations: [
        new GetCollection(
            uriTemplate: '/reports/explore',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Run a report: one dataset, its metrics, grouped by up to 3 dimensions, for a period, optionally compared with an earlier period. '
                . 'List the datasets, metrics, dimensions, filter fields and presets first with the datasets tool. '
                . 'Either name a preset, or give a dataset and its metrics. Amounts are in the global currency. Dates are local days of the default time zone. '
                . 'The answer has columns, rows (dimensions with raw values, labels, values, and compare and change when compared), totals and totalRows. '
                . 'For this_month and this_year, forecasts gives the total of each metric that adds up at the end of the month or year, at the pace so far, with the target of the whole period when one exists.',
            parameters: [
                'preset' => new ListFilter('The code of a ready-made report, such as sales_overview or sales_by_payment_method. The other parameters change it'),
                'dataset' => new ListFilter('The code of the dataset, such as sales_orders, sales_items, carts or catalog_stock'),
                'metrics' => new ListFilter('Comma-separated metric codes of the dataset, such as orders,net_sales,aov'),
                'dimensions' => new ListFilter('Comma-separated dimension codes, at most 3. The period takes a granularity after a colon: period:day, period:week, period:month, period:year, period:hour, period:hour_of_day or period:day_of_week'),
                'filters' => new ListFilter('A JSON list of filters, each {"field": code, "operator": eq|neq|in|nin|gt|gte|lt|lte|contains|empty|not_empty, "value": value or list}'),
                'scope' => new ListFilter('The orders that count: placed (not new or pending payment, the default), not_canceled or all', enum: ['placed', 'not_canceled', 'all']),
                'dateField' => new ListFilter('The date field of the period, such as created_at or updated_at'),
                'range' => new ListFilter('A named period', enum: ['today', 'yesterday', 'last_7_days', 'last_30_days', 'last_90_days', 'this_month', 'last_month', 'this_year', 'last_year', 'last_12_months']),
                'from' => new ListFilter('The first local day, YYYY-MM-DD, with to and without range'),
                'to' => new ListFilter('The last local day, YYYY-MM-DD, with from and without range'),
                'compare' => new ListFilter('Compare with the period before or with the same period one year before', enum: ['none', 'previous_period', 'previous_year']),
                'storeIds' => new ListFilter('Comma-separated store view IDs. Without it, every store that the caller may read'),
                'sort' => new ListFilter('A metric or dimension code of the request, with a minus sign before it for descending, such as -net_sales'),
                'limit' => new ListFilter('Rows per page, 100 by default', 'integer'),
                'offset' => new ListFilter('The first row of the page, 0 by default', 'integer'),
            ],
        ),
        new HttpQuery(
            uriTemplate: '/reports/explore',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Run a report with the request in the body: the same keys as the GET operation, with lists as JSON arrays and filters as objects.',
        ),
    ],
    graphQlOperations: [],
)]
class ExplorerReport extends \Maho\ApiPlatform\Resource
{
    public const string ADMIN_RESOURCE = 'report/explorer';

    #[ApiProperty(identifier: true, description: 'Dataset code')]
    public string $dataset = '';
}
