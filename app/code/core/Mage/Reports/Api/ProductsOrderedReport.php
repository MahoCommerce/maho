<?php

/**
 * The products ordered report: the products with the largest ordered quantity in a date range, from the live order items.
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
    shortName: 'ProductsOrderedReport',
    description: 'Products ordered report from the live orders',
    provider: ProductsOrderedReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/products/ordered',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the products with the largest ordered quantity in the date range, from the live order items (canceled orders are left out). Query: from and to (YYYY-MM-DD, required, inclusive, in the time zone of the default scope), '
                . ReportDocs::SCOPE_QUERY . ', limit (1 to 100, default 20). '
                . 'Response: report, currency, timezone, from, to, limit, scope, totalItems and member: rank, productId, sku (null when the product is deleted), name, qtyOrdered.',
        ),
    ],
    graphQlOperations: [],
)]
class ProductsOrderedReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/products/sold';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'products-ordered';
}
