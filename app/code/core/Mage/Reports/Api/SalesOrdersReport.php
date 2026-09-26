<?php

/**
 * The orders report: order counts and amounts per period, from the aggregated order tables.
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
    shortName: 'SalesOrdersReport',
    description: 'Orders report from the aggregated statistics',
    provider: SalesOrdersReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/sales/orders',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the order counts and amounts of each period from the aggregated statistics. '
                . ReportDocs::PERIOD_QUERY . ' orderStatuses (comma-separated status codes), dateBasis (created or updated, default created). '
                . 'Values: ordersCount, qtyOrdered, qtyInvoiced, salesTotal, revenue, profit, invoiced, paid, refunded, tax, taxActual, shipping, shippingActual, discount, discountActual, canceled. '
                . 'The aggregation leaves out the orders in the states new and pending_payment. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class SalesOrdersReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/salesroot/sales';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'sales-orders';
}
