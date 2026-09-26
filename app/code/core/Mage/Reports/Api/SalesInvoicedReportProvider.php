<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class SalesInvoicedReportProvider extends ReportProviderBase
{
    public const VALUES = [
        'ordersCount' => 'orders_count',
        'ordersInvoiced' => 'orders_invoiced',
        'invoiced' => 'invoiced',
        'invoicedCaptured' => 'invoiced_captured',
        'invoicedNotCaptured' => 'invoiced_not_captured',
    ];

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user, ['order', 'invoice']);

        /** @var \Mage_Sales_Model_Resource_Report_Invoiced_Collection_Order $collection */
        $collection = \Mage::getResourceModel($query->dateBasis === 'invoice'
            ? 'sales/report_invoiced_collection_invoiced'
            : 'sales/report_invoiced_collection_order');

        return $this->valueReport('sales-invoiced', 'invoiced', $query, $collection, self::VALUES, ['ordersCount', 'ordersInvoiced']);
    }
}
