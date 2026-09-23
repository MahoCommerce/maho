<?php

/**
 * The coupons report: the use of each coupon code per period, from the aggregated coupon tables.
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
    shortName: 'SalesCouponsReport',
    description: 'Coupons report from the aggregated statistics',
    provider: SalesCouponsReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/sales/coupons',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the uses and the amounts of each coupon code in each period, from the aggregated statistics. '
                . ReportDocs::PERIOD_QUERY . ' orderStatuses (comma-separated status codes), dateBasis (created or updated, default created), ruleIds (comma-separated cart price rule IDs: only the coupons of these rules). '
                . 'Each period has rows: couponCode, ruleName, uses, subtotal, discount, total, subtotalActual, discountActual, totalActual, sorted by uses. Totals: the sums of the numbers. At most 5000 rows. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class SalesCouponsReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/salesroot/coupons';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'sales-coupons';
}
