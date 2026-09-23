<?php

/**
 * The low stock report: the products whose quantity is below their notify quantity.
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
    shortName: 'ProductsLowStockReport',
    description: 'Low stock report',
    provider: ProductsLowStockReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/products/low-stock',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the products with managed stock whose quantity is below the notify quantity of their stock item, or of the configuration. Lowest quantity first. Query: '
                . ReportDocs::SCOPE_QUERY . ' (products of the website), page, pageSize (at most 100, default 20). '
                . 'Response: report, scope, totalItems, page, pageSize and member: productId, sku, name, type, qty, notifyStockQty.',
        ),
    ],
    graphQlOperations: [],
)]
class ProductsLowStockReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/products/lowstock';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'products-low-stock';
}
