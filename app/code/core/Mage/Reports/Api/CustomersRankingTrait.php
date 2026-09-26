<?php

/**
 * Build the customer reports by orders total and by orders count from the orders of a date range.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

trait CustomersRankingTrait
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;

    /**
     * Return the document of the report $report with the customers that the orders of the range rank first.
     * $sort sorts the collection, with the largest first.
     *
     * @param array<string, mixed> $filters
     * @param \Closure(\Mage_Reports_Model_Resource_Order_Collection): mixed $sort
     * @return array<string, mixed>
     */
    protected function customersRanking(array $filters, ApiUser $user, string $report, \Closure $sort): array
    {
        $query = ReportQuery::forRange($filters, $user);
        $limit = $query->readLimit($filters, self::DEFAULT_LIMIT, self::MAX_LIMIT);
        [$from, $to] = $query->utcRange();

        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        $collection->groupByCustomer()->addOrdersCount();
        // The names are read one by one: a SQL concatenation gives NULL on some databases when the middle name is NULL
        $collection->getSelect()->columns([
            'firstname' => new \Maho\Db\Expr('MAX(main_table.customer_firstname)'),
            'middlename' => new \Maho\Db\Expr('MAX(main_table.customer_middlename)'),
            'lastname' => new \Maho\Db\Expr('MAX(main_table.customer_lastname)'),
        ]);
        $collection->addFieldToFilter('main_table.created_at', ['from' => $from, 'to' => $to]);
        if ($query->scoped) {
            $collection->addFieldToFilter('main_table.store_id', ['in' => $query->storeIds]);
        }
        // A store filter of 0 converts the amounts to the global base currency
        $collection->addSumAvgTotals(0);
        $sort($collection);
        $collection->getSelect()->order('main_table.customer_id ASC')->limit($limit);

        $member = [];
        foreach ($collection->getConnection()->fetchAll($collection->getSelect()) as $index => $row) {
            $member[] = [
                'rank' => $index + 1,
                'customerId' => (int) $row['customer_id'],
                'name' => self::personName($row['firstname'], $row['middlename'], $row['lastname']),
                'ordersCount' => (int) $row['orders_count'],
                'averageOrder' => self::amount($row['orders_avg_amount']),
                'totalOrders' => self::amount($row['orders_sum_amount']),
            ];
        }

        return $this->listEnvelope($report, $query, ['limit' => $limit], $member);
    }
}
