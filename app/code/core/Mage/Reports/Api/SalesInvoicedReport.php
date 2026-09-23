<?php

/**
 * The invoiced report: invoiced and captured amounts per period, from the aggregated invoice tables.
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
    shortName: 'SalesInvoicedReport',
    description: 'Invoiced report from the aggregated statistics',
    provider: SalesInvoicedReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/sales/invoiced',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the invoiced amounts of each period, from the aggregated statistics. '
                . ReportDocs::PERIOD_QUERY . ' dateBasis (order or invoice, default order: the date of the order or of the invoice). '
                . 'Values: ordersCount, ordersInvoiced, invoiced, invoicedCaptured, invoicedNotCaptured. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class SalesInvoicedReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/salesroot/invoiced';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'sales-invoiced';
}
