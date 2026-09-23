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
            foreach ($collection->getConnection()->fetchAll($select) as $row) {
                $member[] = [
                    'productId' => (int) $row['entity_id'],
                    'sku' => (string) $row['sku'],
                    'name' => (string) $row['name'],
                    'price' => self::amount($row['price']),
                    'carts' => (int) $row['carts'],
                    'orders' => (int) $row['orders'],
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
}
