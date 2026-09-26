<?php

/**
 * The customers by orders count report: the customers with the most orders in a date range.
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
    shortName: 'CustomersByOrdersCountReport',
    description: 'Customers by orders count report from the live orders',
    provider: CustomersByOrdersCountReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/customers/by-orders-count',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the registered customers with the most orders in the date range, from the live orders (canceled orders are left out). Query and response: the same as /reports/customers/by-orders-total.',
        ),
    ],
    graphQlOperations: [],
)]
class CustomersByOrdersCountReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/customers/orders';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'customers-by-orders-count';
}
