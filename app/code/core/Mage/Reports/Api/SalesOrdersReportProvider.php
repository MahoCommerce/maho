<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class SalesOrdersReportProvider extends ReportProviderBase
{
    /**
     * Value key => column of the aggregated order tables.
     */
    public const VALUES = [
        'ordersCount' => 'orders_count',
        'qtyOrdered' => 'total_qty_ordered',
        'qtyInvoiced' => 'total_qty_invoiced',
        'salesTotal' => 'total_income_amount',
        'revenue' => 'total_revenue_amount',
        'profit' => 'total_profit_amount',
        'invoiced' => 'total_invoiced_amount',
        'paid' => 'total_paid_amount',
        'refunded' => 'total_refunded_amount',
        'tax' => 'total_tax_amount',
        'taxActual' => 'total_tax_amount_actual',
        'shipping' => 'total_shipping_amount',
        'shippingActual' => 'total_shipping_amount_actual',
        'discount' => 'total_discount_amount',
        'discountActual' => 'total_discount_amount_actual',
        'canceled' => 'total_canceled_amount',
    ];

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user, ['created', 'updated'], orderStatuses: true);

        /** @var \Mage_Sales_Model_Resource_Report_Order_Collection $collection */
        $collection = \Mage::getResourceModel($query->dateBasis === 'updated'
            ? 'sales/report_order_updatedat_collection'
            : 'sales/report_order_collection');

        return $this->valueReport('sales-orders', 'sales', $query, $collection, self::VALUES);
    }
}
