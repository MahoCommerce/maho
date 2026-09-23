<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class BestsellersReportProvider extends ReportProviderBase
{
    use RankedProductsTrait;

    public const TABLES = [
        ReportQuery::PERIOD_DAY => 'sales/bestsellers_aggregated_daily',
        ReportQuery::PERIOD_MONTH => 'sales/bestsellers_aggregated_monthly',
        ReportQuery::PERIOD_YEAR => 'sales/bestsellers_aggregated_yearly',
    ];

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user);
        $report = $this->rankedProducts($query, self::TABLES, 'qty_ordered', 'qtyOrdered', true);
        return $this->envelope('products-bestsellers', $query, $report['totals'], $report['periods'], 'bestsellers');
    }
}
