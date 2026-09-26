<?php

/**
 * The tax report: the tax amount of each tax rate per period, from the aggregated tax tables.
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
    shortName: 'SalesTaxReport',
    description: 'Tax report from the aggregated statistics',
    provider: SalesTaxReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/sales/tax',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the order count and the tax amount of each tax rate in each period, from the aggregated statistics. '
                . ReportDocs::PERIOD_QUERY . ' orderStatuses (comma-separated status codes), dateBasis (created or updated, default created). '
                . 'Each period has rows: code, percent, ordersCount, taxAmount, sorted by taxAmount. Totals: ordersCount and taxAmount (an order with two rates counts two times, the same as in the admin). At most 5000 rows. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class SalesTaxReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/salesroot/tax';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'sales-tax';
}
