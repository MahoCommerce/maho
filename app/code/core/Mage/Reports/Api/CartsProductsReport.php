<?php

/**
 * The products in carts report: the products in the active carts, with the number of carts and of orders.
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
    shortName: 'CartsProductsReport',
    description: 'Products in carts report',
    provider: CartsProductsReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/carts/products',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the products in the active carts, the product in the most carts first. Query: '
                . ReportDocs::SCOPE_QUERY . ' (the store of the cart), page, pageSize (at most 100, default 20). '
                . 'Response: report, currency, scope, totalItems, page, pageSize and member: productId, sku, name, price (the catalog price in the global base currency), carts (active carts with the product), orders (order items of the product in the stores of the scope, all time).',
        ),
    ],
    graphQlOperations: [],
)]
class CartsProductsReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/shopcart/product';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'carts-products';
}
