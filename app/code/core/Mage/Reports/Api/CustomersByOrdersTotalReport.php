<?php

/**
 * The customers by orders total report: the customers with the largest order amounts in a date range.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

// The reports/read grant of ReportsPermission gives access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'CustomersByOrdersTotalReport',
    description: 'Customers by orders total report from the live orders',
    provider: CustomersByOrdersTotalReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/customers/by-orders-total',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the registered customers with the largest total of their orders in the date range, from the live orders (canceled orders are left out). Query: from and to (YYYY-MM-DD, required, inclusive, in the time zone of the default scope), '
                . ReportDocs::SCOPE_QUERY . ', limit (1 to 100, default 20). The totals are subtotals without refunds, canceled amounts and discounts, in the global base currency. '
                . 'Response: report, currency, timezone, from, to, limit, scope, totalItems and member: rank, customerId, name, ordersCount, averageOrder, totalOrders.',
        ),
    ],
    graphQlOperations: [],
)]
class CustomersByOrdersTotalReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/customers/totals';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'customers-by-orders-total';
}
