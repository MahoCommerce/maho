<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class SalesCouponsReportProvider extends ReportProviderBase
{
    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user, ['created', 'updated'], orderStatuses: true);
        $ruleIds = $query->readIdList($filters, 'ruleIds');

        /** @var \Mage_SalesRule_Model_Resource_Report_Collection $collection */
        $collection = \Mage::getResourceModel($query->dateBasis === 'updated'
            ? 'salesrule/report_updatedat_collection'
            : 'salesrule/report_collection');

        if ($ruleIds !== null) {
            // The report tables keep the name of the rule, not its ID, the same as the rule filter of the admin
            $names = \Mage::getResourceModel('salesrule/rule_collection')
                ->addFieldToFilter('rule_id', ['in' => $ruleIds])
                ->getColumnValues('name');
            $collection->getSelect()->where('rule_name IN (?)', $names === [] ? [''] : array_values(array_unique($names)));
        }

        return $this->rowReport(
            'sales-coupons',
            'coupons',
            $query,
            $collection,
            static fn(array $row): array => [
                'couponCode' => (string) $row['coupon_code'],
                'ruleName' => (string) $row['rule_name'],
                'uses' => (int) $row['coupon_uses'],
                'subtotal' => self::amount($row['subtotal_amount']),
                'discount' => self::amount($row['discount_amount']),
                'total' => self::amount($row['total_amount']),
                'subtotalActual' => self::amount($row['subtotal_amount_actual']),
                'discountActual' => self::amount($row['discount_amount_actual']),
                'totalActual' => self::amount($row['total_amount_actual']),
            ],
            ['uses', 'subtotal', 'discount', 'total', 'subtotalActual', 'discountActual', 'totalActual'],
            static fn(array $a, array $b): int => [$b['uses'], $a['couponCode']] <=> [$a['uses'], $b['couponCode']],
            $ruleIds !== null ? ['ruleIds' => $ruleIds] : [],
        );
    }
}
