<?php

/**
 * The rows behind the numbers of a report of the report explorer.
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
    shortName: 'ExplorerRecords',
    description: 'The rows behind the numbers of a report, such as the orders that it counts',
    provider: ExplorerRecordsProvider::class,
    extraProperties: ['maho_mcp_section' => 'explorer'],
    operations: [
        new GetCollection(
            uriTemplate: '/reports/explore/records',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the rows behind the numbers of a report: the orders, order items, invoices, credit memos, customers, products in stock, reviews, search terms, carts or cart items, '
                . 'with the same dataset, period, scope, stores and filters as the report tool. Each row has the id of its record. '
                . 'The caller needs the read permission of the records, such as orders or customers, besides reports/read.',
            parameters: [
                'preset' => new ListFilter('The code of a ready-made report. The other parameters change it'),
                'dataset' => new ListFilter('The code of the dataset, such as sales_orders'),
                'filters' => new ListFilter('A JSON list of filters, each {"field": code, "operator": eq|neq|in|nin|gt|gte|lt|lte|contains|empty|not_empty, "value": value or list}'),
                'scope' => new ListFilter('The orders that count: placed (the default), not_canceled or all', enum: ['placed', 'not_canceled', 'all']),
                'dateField' => new ListFilter('The date field of the period, such as created_at or updated_at'),
                'range' => new ListFilter('A named period', enum: ['today', 'yesterday', 'last_7_days', 'last_30_days', 'last_90_days', 'this_month', 'last_month', 'this_year', 'last_year', 'last_12_months']),
                'from' => new ListFilter('The first local day, YYYY-MM-DD, with to and without range'),
                'to' => new ListFilter('The last local day, YYYY-MM-DD, with from and without range'),
                'storeIds' => new ListFilter('Comma-separated store view IDs'),
                'sort' => new ListFilter('A field of the rows, with a minus sign before it for descending. Without it, the newest rows come first'),
                'limit' => new ListFilter('Rows per page, 50 by default', 'integer'),
                'offset' => new ListFilter('The first row of the page, 0 by default', 'integer'),
            ],
        ),
    ],
    graphQlOperations: [],
)]
class ExplorerRecords extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/explorer';

    #[ApiProperty(identifier: true, description: 'Dataset code')]
    public string $dataset = '';
}
