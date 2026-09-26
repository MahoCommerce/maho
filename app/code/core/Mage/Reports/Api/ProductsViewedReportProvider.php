<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class ProductsViewedReportProvider extends ReportProviderBase
{
    use RankedProductsTrait;

    public const TABLES = [
        ReportQuery::PERIOD_DAY => 'reports/viewed_aggregated_daily',
        ReportQuery::PERIOD_MONTH => 'reports/viewed_aggregated_monthly',
        ReportQuery::PERIOD_YEAR => 'reports/viewed_aggregated_yearly',
    ];

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user);
        $report = $this->rankedProducts($query, self::TABLES, 'views_num', 'views', false);
        return $this->envelope('products-viewed', $query, $report['totals'], $report['periods'], 'viewed');
    }
}
