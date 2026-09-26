<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class SalesShippingReportProvider extends ReportProviderBase
{
    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user, ['order', 'shipment'], orderStatuses: true);

        /** @var \Mage_Sales_Model_Resource_Report_Shipping_Collection_Order $collection */
        $collection = \Mage::getResourceModel($query->dateBasis === 'shipment'
            ? 'sales/report_shipping_collection_shipment'
            : 'sales/report_shipping_collection_order');

        return $this->rowReport(
            'sales-shipping',
            'shipping',
            $query,
            $collection,
            static fn(array $row): array => [
                'shippingDescription' => (string) $row['shipping_description'],
                'ordersCount' => (int) $row['orders_count'],
                'totalShipping' => self::amount($row['total_shipping']),
                'totalShippingActual' => self::amount($row['total_shipping_actual']),
            ],
            ['ordersCount', 'totalShipping', 'totalShippingActual'],
            static fn(array $a, array $b): int => [$b['ordersCount'], $a['shippingDescription']] <=> [$a['ordersCount'], $b['shippingDescription']],
        );
    }
}
