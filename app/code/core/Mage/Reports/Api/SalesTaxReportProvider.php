<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class SalesTaxReportProvider extends ReportProviderBase
{
    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user, ['created', 'updated'], orderStatuses: true);

        /** @var \Mage_Tax_Model_Resource_Report_Collection $collection */
        $collection = \Mage::getResourceModel($query->dateBasis === 'updated'
            ? 'tax/report_updatedat_collection'
            : 'tax/report_collection');

        return $this->rowReport(
            'sales-tax',
            'tax',
            $query,
            $collection,
            static fn(array $row): array => [
                'code' => (string) $row['code'],
                'percent' => self::amount($row['percent']),
                'ordersCount' => (int) $row['orders_count'],
                'taxAmount' => self::amount($row['tax_base_amount_sum']),
            ],
            ['ordersCount', 'taxAmount'],
            static fn(array $a, array $b): int => [$b['taxAmount'], $a['code'], $a['percent']] <=> [$a['taxAmount'], $b['code'], $b['percent']],
        );
    }
}
