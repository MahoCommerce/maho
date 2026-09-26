<?php

/**
 * The search terms report: the search terms of the storefront with their uses and results.
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
    shortName: 'SearchTermsReport',
    description: 'Search terms report',
    provider: SearchTermsReportProvider::class,
    operations: [
        new Get(
            uriTemplate: '/reports/search-terms',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the search terms of the storefront. Query: search (every word must match part of the term), sort (popularity, results or updatedAt, default popularity, largest or newest first), '
                . ReportDocs::SCOPE_QUERY . ', page, pageSize (at most 100, default 20). '
                . 'Response: report, sort, scope, totalItems, page, pageSize and member: id, queryText, storeId, results, uses, updatedAt.',
        ),
    ],
    graphQlOperations: [],
)]
class SearchTermsReport extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/search';

    #[ApiProperty(identifier: true, description: 'Report code')]
    public string $report = 'search-terms';
}
