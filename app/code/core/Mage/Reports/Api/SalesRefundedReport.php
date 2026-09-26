<?php

/**
 * The refunded report: refunded amounts per period, from the aggregated credit memo tables.
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
    shortName: 'SalesRefundedReport',
    description: 'Refunded report from the aggregated statistics',
    provider: SalesRefundedReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/sales/refunded',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the refunded amounts of each period, from the aggregated statistics. '
                . ReportDocs::PERIOD_QUERY . ' dateBasis (order or refund, default order: the date of the order or of the credit memo). '
                . 'Values: ordersCount, refunded, onlineRefunded, offlineRefunded. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class SalesRefundedReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/salesroot/refunded';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'sales-refunded';
}
