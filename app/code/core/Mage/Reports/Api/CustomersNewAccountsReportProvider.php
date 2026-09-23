<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class CustomersNewAccountsReportProvider extends ReportProviderBase
{
    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forPeriods($filters, $user);
        [$from, $to] = $query->utcRange();

        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $table = $resource->getTableName('customer/entity');
        // The same conversion to the local day as the aggregation of the report tables
        $day = $adapter->getDatePartSql(
            \Mage::getResourceModel('sales/report_order')->getStoreTZOffsetQuery(['e' => $table], 'e.created_at', $from, $to),
        );
        $select = $adapter->select()
            ->from(['e' => $table], ['day' => $day, 'accounts' => new \Maho\Db\Expr('COUNT(e.entity_id)')])
            ->where('e.created_at >= ?', $from)
            ->where('e.created_at <= ?', $to)
            ->group($day);
        if ($query->scoped) {
            $select->where('e.store_id IN (?)', $query->storeIds);
        }

        $byPeriod = [];
        $total = 0;
        foreach ($adapter->fetchAll($select) as $row) {
            $label = $query->periodLabel($row['day']);
            $byPeriod[$label] = ['accounts' => ($byPeriod[$label]['accounts'] ?? 0) + (int) $row['accounts']];
            $total += (int) $row['accounts'];
        }

        return $this->envelope(
            'customers-new-accounts',
            $query,
            ['accounts' => $total],
            $this->periodList($query, $byPeriod, ['accounts' => 0]),
            null,
        );
    }
}
