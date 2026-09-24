<?php

/**
 * The abandoned carts report: the active carts with items, the last changed first.
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
    shortName: 'CartsAbandonedReport',
    description: 'Abandoned carts report',
    provider: CartsAbandonedReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/carts/abandoned',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the active carts with items, the last changed first. Query: '
                . ReportDocs::SCOPE_QUERY . ' (the store of the cart), customersOnly (true: only the carts of customers with an account), page, pageSize (at most 100, default 20). '
                . 'Response: report, currency, customersOnly, scope, totalItems, page, pageSize and member: cartId, storeId, customerId, customerName, customerEmail, itemsCount, itemsQty, subtotal (after discounts, in the global base currency), couponCode, createdAt, updatedAt.',
        ),
    ],
    graphQlOperations: [],
)]
class CartsAbandonedReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/shopcart/abandoned';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'carts-abandoned';
}
