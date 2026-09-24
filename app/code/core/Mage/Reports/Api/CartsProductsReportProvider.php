<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class CartsProductsReportProvider extends ReportProviderBase
{
    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forScope($filters, $user);
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(['filters' => $filters], 20, 100);

        /** @var \Mage_Reports_Model_Resource_Quote_Collection $collection */
        $collection = \Mage::getResourceModel('reports/quote_collection');
        $collection->prepareForProductsInCarts()
            ->setSelectCountSqlType(\Mage_Reports_Model_Resource_Quote_Collection::SELECT_COUNT_SQL_TYPE_CART);
        if ($query->scoped) {
            $collection->addStoreFilter($query->storeIds);
        }
        // The default values of the name and the price, not the value of one of the store views
        $collection->getSelect()
            ->where('product_name.store_id = ?', \Mage_Core_Model_App::ADMIN_STORE_ID)
            ->where('product_price.store_id = ?', \Mage_Core_Model_App::ADMIN_STORE_ID)
            ->order(new \Maho\Db\Expr('COUNT(quote_items.item_id) DESC'))
            ->order('e.entity_id ASC');
        $total = (int) $collection->getSize();
        $select = (clone $collection->getSelect())->limitPage($page, $pageSize);
        $member = [];
        if (($page - 1) * $pageSize < $total) {
            $rows = $collection->getConnection()->fetchAll($select);
            $orders = $this->orderCounts(array_map(static fn(array $row): int => (int) $row['entity_id'], $rows), $query);
            foreach ($rows as $row) {
                $member[] = [
                    'productId' => (int) $row['entity_id'],
                    'sku' => (string) $row['sku'],
                    'name' => (string) $row['name'],
                    'price' => self::amount($row['price']),
                    'carts' => (int) $row['carts'],
                    'orders' => $orders[(int) $row['entity_id']] ?? 0,
                ];
            }
        }

        return [
            'report' => 'carts-products',
            'currency' => self::currency(),
            'scope' => $query->scopeArray(),
            'totalItems' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'member' => $member,
        ];
    }

    /**
     * The number of order items of each product in $productIds, in the stores of the scope.
     *
     * The orders column of the core collection counts the order items of all stores.
     *
     * @param list<int> $productIds
     * @return array<int, int> product ID => order items
     */
    private function orderCounts(array $productIds, ReportQuery $query): array
    {
        if ($productIds === []) {
            return [];
        }
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('sales/order_item'), ['product_id', 'orders' => new \Maho\Db\Expr('COUNT(1)')])
            ->where('product_id IN (?)', $productIds)
            ->group('product_id');
        if ($query->scoped) {
            $select->where('store_id IN (?)', $query->storeIds);
        }
        return array_map(intval(...), $adapter->fetchPairs($select));
    }
}
