<?php

/**
 * The shipping report: the shipping amount of each shipping method per period, from the aggregated shipping tables.
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
    shortName: 'SalesShippingReport',
    description: 'Shipping report from the aggregated statistics',
    provider: SalesShippingReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/sales/shipping',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the order count and the shipping amount of each shipping method in each period, from the aggregated statistics. '
                . ReportDocs::PERIOD_QUERY . ' orderStatuses (comma-separated status codes), dateBasis (order or shipment, default order: the date of the order or of the shipment). '
                . 'Each period has rows: shippingDescription, ordersCount, totalShipping, totalShippingActual, sorted by ordersCount. Totals: ordersCount, totalShipping, totalShippingActual. At most 5000 rows. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class SalesShippingReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/salesroot/shipping';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'sales-shipping';
}
