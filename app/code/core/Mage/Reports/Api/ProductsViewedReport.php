<?php

/**
 * The most viewed products report: the products with the most views in each period.
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
    shortName: 'ProductsViewedReport',
    description: 'Most viewed products report from the aggregated statistics',
    provider: ProductsViewedReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/products/viewed',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the 5 products with the most views in each period, from the aggregated statistics. '
                . ReportDocs::PERIOD_QUERY . ' '
                . 'Each period has rows: rank, productId, sku (null when the product is deleted), name, price (the catalog price of the product), views. Totals: views of the listed rows. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class ProductsViewedReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/products/viewed';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'products-viewed';
}
