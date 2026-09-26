<?php

/**
 * The dashboard: lifetime sales, the totals and the chart of a period, and the short lists of the admin dashboard.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoId: 'dashboard',
    mahoLabel: 'Dashboard',
    mahoSection: 'Reports',
    mahoOperations: ['read' => 'View'],
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('dashboard/read')",
    shortName: 'Dashboard',
    description: 'Data of the admin dashboard',
    provider: DashboardProvider::class,
    operations: [
        new Get(
            uriTemplate: '/dashboard',
            security: "is_granted('ROLE_ADMIN') or is_granted('dashboard/read')",
            description: 'Get the data of the admin dashboard. Query: period (24h, 7d, 1m, 3m, 6m, 1y or 2y, default 24h), '
                . 'storeId or websiteId (not both), sections (comma-separated, default all: lifetime, totals, chart, lastOrders, '
                . 'lastSearchTerms, topSearchTerms, bestsellers, mostViewed, newCustomers, topCustomers). '
                . 'All amounts are in the global base currency (currency), also with a scope. source is live or aggregated, '
                . 'from the configuration sales/dashboard/use_aggregated_data. The chart has zero-filled buckets: '
                . 'hours for 24h, days for 7d and 1m, months for 3m to 2y. Orders count only the orders that are not in the states new and pending_payment.',
        ),
    ],
    graphQlOperations: [],
)]
class Dashboard extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_DashboardController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Period of the totals and of the chart')]
    public string $period = '24h';
}
