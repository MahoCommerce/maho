<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class DashboardProvider extends ReportProviderBase
{
    public const PERIODS = ['24h', '7d', '1m', '3m', '6m', '1y', '2y'];

    public const SECTIONS = [
        'lifetime', 'totals', 'chart', 'lastOrders', 'lastSearchTerms',
        'topSearchTerms', 'bestsellers', 'mostViewed', 'newCustomers', 'topCustomers',
    ];

    /**
     * The number of rows of each list, the same as the admin dashboard.
     */
    public const LIST_SIZE = 5;

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forScope($filters, $user);
        $period = $query->readEnum($filters, 'period', self::PERIODS) ?? '24h';
        $sections = $this->readSections($filters);
        $live = !\Mage::getStoreConfigFlag('sales/dashboard/use_aggregated_data');

        $document = [
            'period' => $period,
            'timezone' => self::timezone(),
            'currency' => self::currency(),
            'source' => $live ? 'live' : 'aggregated',
            'scope' => $query->scopeArray(),
        ];

        $builders = [
            'lifetime' => fn(): array => $this->lifetime($query, $live),
            'totals' => fn(): array => $this->totals($query, $live, $period),
            'chart' => fn(): array => $this->chart($query, $live, $period),
            'lastOrders' => fn(): array => $this->lastOrders($query),
            'lastSearchTerms' => fn(): array => $this->lastSearchTerms($query),
            'topSearchTerms' => fn(): array => $this->topSearchTerms($query),
            'bestsellers' => fn(): array => $this->bestsellers($query),
            'mostViewed' => fn(): array => $this->mostViewed($query),
            'newCustomers' => fn(): array => $this->newCustomers($query),
            'topCustomers' => fn(): array => $this->topCustomers($query),
        ];
        foreach ($sections as $section) {
            $document[$section] = $builders[$section]();
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<string>
     */
    private function readSections(array $filters): array
    {
        $value = $this->stringFilter($filters, 'sections');
        if ($value === null) {
            return self::SECTIONS;
        }
        $requested = [];
        foreach (explode(',', $value) as $section) {
            $section = trim($section);
            if (!in_array($section, self::SECTIONS, true)) {
                throw new BadRequestHttpException('sections must be a comma-separated list of: ' . implode(', ', self::SECTIONS));
            }
            $requested[$section] = true;
        }
        return array_values(array_filter(self::SECTIONS, static fn(string $section): bool => isset($requested[$section])));
    }

    /**
     * Restrict an order collection to the stores of the scope. Without a scope, the aggregated
     * tables must use their rows of the admin store, which hold the sum of all stores.
     */
    private function applyOrderScope(\Mage_Reports_Model_Resource_Order_Collection $collection, ReportQuery $query, bool $live): void
    {
        if ($query->scoped) {
            $collection->addFieldToFilter('main_table.store_id', ['in' => $query->storeIds]);
        } elseif (!$live) {
            $collection->addFieldToFilter('main_table.store_id', \Mage_Core_Model_App::ADMIN_STORE_ID);
        }
    }

    /**
     * @return array{sales: float, averageOrder: float}
     */
    private function lifetime(ReportQuery $query, bool $live): array
    {
        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        // A filter value of 0 converts the live amounts to the global base currency. The aggregated
        // amounts are always converted, and a filter value of 1 only stops the filter on the admin store.
        $collection->calculateSales(!$live && $query->scoped ? 1 : 0);
        if ($query->scoped) {
            $collection->addFieldToFilter('main_table.store_id', ['in' => $query->storeIds]);
        }
        $row = $collection->load()->getFirstItem();
        return ['sales' => self::amount($row->getData('lifetime')), 'averageOrder' => self::amount($row->getData('average'))];
    }

    /**
     * @return array{revenue: float, tax: float, shipping: float, orders: int}
     */
    private function totals(ReportQuery $query, bool $live, string $period): array
    {
        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        $collection->addCreateAtPeriodFilter($period)->calculateTotals(0);
        $this->applyOrderScope($collection, $query, $live);
        $row = $collection->load()->getFirstItem();
        return [
            'revenue' => self::amount($row->getData('revenue')),
            'tax' => self::amount($row->getData('tax')),
            'shipping' => self::amount($row->getData('shipping')),
            'orders' => (int) $row->getData('quantity'),
        ];
    }

    /**
     * @return array{bucket: string, points: list<array{start: string, label: string, orders: int, revenue: float}>}
     */
    private function chart(ReportQuery $query, bool $live, string $period): array
    {
        [$bucket, $keyFormat, $labelFormat, $step] = match ($period) {
            '24h' => ['hour', 'Y-m-d H', 'Y-m-d H:00', '+1 hour'],
            '7d', '1m' => ['day', 'Y-m-d', 'Y-m-d', '+1 day'],
            default => ['month', 'Y-m', 'Y-m', '+1 month'],
        };

        // The buckets of the admin chart: from the start of the range, one step at a time, while before its end
        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        [$rangeStart, $rangeEnd] = $collection->getDateRange($period, '', '', true);
        $timezone = new \DateTimeZone(self::timezone());
        $cursor = \DateTimeImmutable::createFromInterface($rangeStart)->setTimezone($timezone);
        $end = \DateTimeImmutable::createFromInterface($rangeEnd)->setTimezone($timezone);
        if ($bucket === 'month') {
            $cursor = $cursor->setDate((int) $cursor->format('Y'), (int) $cursor->format('n'), 1);
        }
        $buckets = [];
        while ($cursor < $end) {
            $buckets[] = [
                'start' => match ($bucket) {
                    'hour' => $cursor->setTime((int) $cursor->format('G'), 0),
                    default => $cursor->setTime(0, 0),
                },
                'key' => $cursor->format($keyFormat),
                'label' => $cursor->format($labelFormat),
            ];
            $cursor = $cursor->modify($step);
        }

        $values = $live
            ? $this->liveChartValues($query, $period, $buckets, $rangeStart, $rangeEnd)
            : $this->aggregatedChartValues($query, $period, $keyFormat);

        $points = [];
        foreach ($buckets as $item) {
            $points[] = [
                'start' => $item['start']->format(\DateTimeInterface::ATOM),
                'label' => $item['label'],
                'orders' => $values[$item['key']]['orders'] ?? 0,
                'revenue' => self::amount($values[$item['key']]['revenue'] ?? 0),
            ];
        }

        return ['bucket' => $bucket, 'points' => $points];
    }

    /**
     * Count the orders and the revenue of each bucket in the order table. A CASE expression puts each
     * order in its bucket, so the buckets follow the time zone and its daylight saving changes on all databases.
     *
     * @param list<array{start: \DateTimeImmutable, key: string, label: string}> $buckets
     * @return array<string, array{orders: int, revenue: float}>
     */
    private function liveChartValues(ReportQuery $query, string $period, array $buckets, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if ($buckets === []) {
            return [];
        }

        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        $collection->checkIsLive($period)->calculateTotals(0);
        $adapter = $collection->getConnection();
        $utc = new \DateTimeZone('UTC');

        $case = 'CASE';
        foreach (array_slice($buckets, 1) as $index => $item) {
            $case .= sprintf(
                ' WHEN main_table.created_at < %s THEN %d',
                $adapter->quote($item['start']->setTimezone($utc)->format(\Mage_Core_Model_Locale::DATETIME_FORMAT)),
                $index,
            );
        }
        $case .= ' ELSE ' . (count($buckets) - 1) . ' END';

        $select = $collection->getSelect();
        $select->columns(['bucket' => new \Maho\Db\Expr($case)])
            ->where('main_table.created_at >= ?', \DateTimeImmutable::createFromInterface($from)->setTimezone($utc)->format(\Mage_Core_Model_Locale::DATETIME_FORMAT))
            ->where('main_table.created_at <= ?', \DateTimeImmutable::createFromInterface($to)->setTimezone($utc)->format(\Mage_Core_Model_Locale::DATETIME_FORMAT))
            ->group(new \Maho\Db\Expr($case));
        if ($query->scoped) {
            $select->where('main_table.store_id IN (?)', $query->storeIds);
        }

        $values = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $key = $buckets[(int) $row['bucket']]['key'] ?? null;
            if ($key !== null) {
                $values[$key] = ['orders' => (int) $row['quantity'], 'revenue' => (float) $row['revenue']];
            }
        }
        return $values;
    }

    /**
     * Read the orders and the revenue of each day or month from the aggregated order table. It has no
     * hours, so with the period 24h each day is in the bucket of its midnight, the same as in the admin.
     *
     * @return array<string, array{orders: int, revenue: float}>
     */
    private function aggregatedChartValues(ReportQuery $query, string $period, string $keyFormat): array
    {
        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        $collection->prepareSummary($period, 0, 0, 0);
        $this->applyOrderScope($collection, $query, false);

        $length = strlen((new \DateTimeImmutable('2000-01-01'))->format($keyFormat));
        $values = [];
        foreach ($collection as $row) {
            // The range labels of the hours have the form "2026-09-23 00:", then a minute part that differs per database
            $key = substr(str_pad((string) $row->getData('range'), $length, '0'), 0, $length);
            if ($keyFormat === 'Y-m-d H' && strlen((string) $row->getData('range')) <= 10) {
                $key = substr((string) $row->getData('range'), 0, 10) . ' 00';
            }
            $values[$key] = [
                'orders' => ($values[$key]['orders'] ?? 0) + (int) $row->getData('quantity'),
                'revenue' => ($values[$key]['revenue'] ?? 0.0) + (float) $row->getData('revenue'),
            ];
        }
        return $values;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lastOrders(ReportQuery $query): array
    {
        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        $collection->addItemCountExpr()->orderByCreatedAt();
        if ($query->scoped) {
            $collection->addFieldToFilter('main_table.store_id', ['in' => $query->storeIds]);
        }
        $collection->addRevenueToSelect(true);
        $collection->setPageSize(self::LIST_SIZE)->setCurPage(1);

        $rows = [];
        foreach ($collection as $order) {
            $rows[] = [
                'id' => (int) $order->getId(),
                'incrementId' => (string) $order->getData('increment_id'),
                'customerName' => self::personName($order->getData('customer_firstname'), $order->getData('customer_middlename'), $order->getData('customer_lastname')),
                'customerId' => $order->getData('customer_id') !== null ? (int) $order->getData('customer_id') : null,
                'itemsCount' => (int) $order->getData('items_count'),
                'grandTotal' => self::amount($order->getData('revenue')),
                'status' => (string) $order->getData('status'),
                'storeId' => (int) $order->getData('store_id'),
                'createdAt' => self::isoDate($order->getData('created_at')),
            ];
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lastSearchTerms(ReportQuery $query): array
    {
        /** @var \Mage_CatalogSearch_Model_Resource_Query_Collection $collection */
        $collection = \Mage::getModel('catalogsearch/query')->getResourceCollection();
        $collection->setRecentQueryFilter();
        if ($query->scoped) {
            $collection->addFieldToFilter('main_table.store_id', ['in' => $query->storeIds]);
        }
        $collection->setPageSize(self::LIST_SIZE)->setCurPage(1);

        $rows = [];
        foreach ($collection as $term) {
            $rows[] = [
                'id' => (int) $term->getId(),
                'queryText' => (string) $term->getData('query_text'),
                'results' => (int) $term->getData('num_results'),
                'uses' => (int) $term->getData('popularity'),
                'storeId' => (int) $term->getData('store_id'),
                'updatedAt' => self::isoDate($term->getData('updated_at')),
            ];
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topSearchTerms(ReportQuery $query): array
    {
        /** @var \Mage_CatalogSearch_Model_Resource_Query_Collection $collection */
        $collection = \Mage::getModel('catalogsearch/query')->getResourceCollection();
        $collection->setPopularQueryFilter($query->scoped ? $query->storeIds : '');
        $collection->setPageSize(self::LIST_SIZE)->setCurPage(1);

        $rows = [];
        foreach ($collection as $term) {
            $rows[] = [
                'id' => (int) $term->getData('query_id'),
                'queryText' => (string) $term->getData('name'),
                'results' => (int) $term->getData('num_results'),
                'uses' => (int) $term->getData('popularity'),
            ];
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bestsellers(ReportQuery $query): array
    {
        /** @var \Mage_Sales_Model_Resource_Report_Bestsellers_Collection $collection */
        $collection = \Mage::getResourceModel('sales/report_bestsellers_collection');
        $collection->addStoreFilter($query->scoped ? $query->storeIds : \Mage_Core_Model_App::ADMIN_STORE_ID);

        $rows = [];
        foreach ($collection as $item) {
            $rows[] = [
                'productId' => (int) $item->getData('product_id'),
                'name' => (string) $item->getData('product_name'),
                'price' => self::amount($item->getData('product_price')),
                'qtyOrdered' => self::quantity($item->getData('qty_ordered')),
            ];
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mostViewed(ReportQuery $query): array
    {
        /** @var \Mage_Reports_Model_Resource_Product_Collection $collection */
        $collection = \Mage::getResourceModel('reports/product_collection');
        $collection->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID)
            ->addAttributeToSelect(['name', 'price'])
            ->addViewsCount();
        if ($query->scoped) {
            $collection->getSelect()->where('report_table_views.store_id IN (?)', $query->storeIds);
        }
        $collection->setPageSize(self::LIST_SIZE)->setCurPage(1);

        $rows = [];
        foreach ($collection as $product) {
            $rows[] = [
                'productId' => (int) $product->getId(),
                'sku' => (string) $product->getData('sku'),
                'name' => (string) $product->getData('name'),
                'price' => self::amount($product->getData('price')),
                'views' => (int) $product->getData('views'),
            ];
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newCustomers(ReportQuery $query): array
    {
        /** @var \Mage_Reports_Model_Resource_Customer_Collection $collection */
        $collection = \Mage::getResourceModel('reports/customer_collection');
        $collection->addAttributeToSelect(['firstname', 'middlename', 'lastname']);
        if ($query->storeId !== null) {
            $collection->addAttributeToFilter('store_id', $query->storeId);
        } elseif ($query->scoped) {
            $collection->addAttributeToFilter('website_id', ['in' => $query->websiteIds() ?: [-1]]);
        }
        $collection->orderByCustomerRegistration();
        $collection->setPageSize(self::LIST_SIZE)->setCurPage(1);
        $statistics = $this->customerOrderStatistics(array_map(intval(...), array_keys($collection->getItems())), $query);

        $rows = [];
        foreach ($collection as $customer) {
            $orders = $statistics[(int) $customer->getId()] ?? [];
            $rows[] = [
                'id' => (int) $customer->getId(),
                'name' => self::personName($customer->getData('firstname'), $customer->getData('middlename'), $customer->getData('lastname')),
                'createdAt' => self::isoDate($customer->getData('created_at')),
                'ordersCount' => (int) ($orders['orders_count'] ?? 0),
                'averageOrder' => self::amount($orders['orders_avg_amount'] ?? 0),
                'totalOrders' => self::amount($orders['orders_sum_amount'] ?? 0),
            ];
        }
        return $rows;
    }

    /**
     * The orders of each customer in $customerIds, in the stores of the scope: count, average and sum of the
     * subtotal minus the canceled and refunded subtotal, in the global base currency. Canceled orders are left out.
     *
     * The order statistics of the core customer collection count the orders of all stores.
     *
     * @param list<int> $customerIds
     * @return array<int, array<string, mixed>> customer ID => statistics
     */
    private function customerOrderStatistics(array $customerIds, ReportQuery $query): array
    {
        if ($customerIds === []) {
            return [];
        }
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $refunded = $adapter->getIfNullSql('orders.base_subtotal_refunded', 0);
        $canceled = $adapter->getIfNullSql('orders.base_subtotal_canceled', 0);
        $total = "(orders.base_subtotal - {$canceled} - {$refunded}) * orders.base_to_global_rate";
        $select = $adapter->select()
            ->from(['orders' => $resource->getTableName('sales/order')], [
                'customer_id',
                'orders_count' => new \Maho\Db\Expr('COUNT(orders.entity_id)'),
                'orders_avg_amount' => new \Maho\Db\Expr("AVG({$total})"),
                'orders_sum_amount' => new \Maho\Db\Expr("SUM({$total})"),
            ])
            ->where('orders.state <> ?', \Mage_Sales_Model_Order::STATE_CANCELED)
            ->where('orders.customer_id IN (?)', $customerIds)
            ->group('orders.customer_id');
        if ($query->scoped) {
            $select->where('orders.store_id IN (?)', $query->storeIds);
        }
        $statistics = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $statistics[(int) $row['customer_id']] = $row;
        }
        return $statistics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topCustomers(ReportQuery $query): array
    {
        /** @var \Mage_Reports_Model_Resource_Order_Collection $collection */
        $collection = \Mage::getResourceModel('reports/order_collection');
        $collection->groupByCustomer()->addOrdersCount();
        // The names are read one by one: a SQL concatenation gives NULL on some databases when the middle name is NULL
        $collection->getSelect()->columns([
            'firstname' => new \Maho\Db\Expr('MAX(main_table.customer_firstname)'),
            'middlename' => new \Maho\Db\Expr('MAX(main_table.customer_middlename)'),
            'lastname' => new \Maho\Db\Expr('MAX(main_table.customer_lastname)'),
        ]);
        if ($query->scoped) {
            $collection->addFieldToFilter('main_table.store_id', ['in' => $query->storeIds]);
        }
        $collection->addSumAvgTotals(0)->orderByTotalAmount();
        $collection->setPageSize(self::LIST_SIZE)->setCurPage(1);

        $rows = [];
        foreach ($collection as $row) {
            $rows[] = [
                'customerId' => (int) $row->getData('customer_id'),
                'name' => self::personName($row->getData('firstname'), $row->getData('middlename'), $row->getData('lastname')),
                'ordersCount' => (int) $row->getData('orders_count'),
                'averageOrder' => self::amount($row->getData('orders_avg_amount')),
                'totalOrders' => self::amount($row->getData('orders_sum_amount')),
            ];
        }
        return $rows;
    }
}
