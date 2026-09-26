<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class SalesRefundedReportProvider extends ReportProviderBase
{
    public const VALUES = [
        'ordersCount' => 'orders_count',
        'refunded' => 'refunded',
        'onlineRefunded' => 'online_refunded',
        'offlineRefunded' => 'offline_refunded',
    ];

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user, ['order', 'refund']);

        /** @var \Mage_Sales_Model_Resource_Report_Refunded_Collection_Order $collection */
        $collection = \Mage::getResourceModel($query->dateBasis === 'refund'
            ? 'sales/report_refunded_collection_refunded'
            : 'sales/report_refunded_collection_order');

        return $this->valueReport('sales-refunded', 'refunded', $query, $collection, self::VALUES);
    }
}
