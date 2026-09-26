<?php

/**
 * The new accounts report: the number of new customer accounts per period, from the live customer table.
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
    shortName: 'CustomersNewAccountsReport',
    description: 'New accounts report from the live customers',
    provider: CustomersNewAccountsReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/customers/new-accounts',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get the number of new customer accounts in each period, from the live customer table. '
                . ReportDocs::PERIOD_QUERY . ' A scope filters by the store view where the account was created. '
                . 'Values: accounts. There is no statistics key, because the data is live. '
                . ReportDocs::ENVELOPE,
        ),
    ],
    graphQlOperations: [],
)]
class CustomersNewAccountsReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/customers/accounts';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'customers-new-accounts';
}
