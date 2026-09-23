<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class CartsAbandonedReportProvider extends ReportProviderBase
{
    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forScope($filters, $user);
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(['filters' => $filters], 20, 100);
        $customersOnly = $this->booleanFilter($filters, 'customersOnly') ?? false;

        // The same carts as the admin report: active carts with items, the last changed first
        /** @var \Mage_Reports_Model_Resource_Quote_Collection $collection */
        $collection = \Mage::getResourceModel('reports/quote_collection');
        $collection->addFieldToFilter('main_table.items_count', ['neq' => 0])
            ->addFieldToFilter('main_table.is_active', 1);
        if ($customersOnly) {
            $collection->addFieldToFilter('main_table.customer_id', ['notnull' => true]);
        }
        if ($query->scoped) {
            $collection->addStoreFilter($query->storeIds);
        }
        $collection->getSelect()
            ->reset(\Maho\Db\Select::COLUMNS)
            ->columns([
                'entity_id', 'store_id', 'customer_id', 'customer_email', 'customer_firstname', 'customer_middlename',
                'customer_lastname', 'items_count', 'items_qty', 'coupon_code', 'created_at', 'updated_at',
                'subtotal' => new \Maho\Db\Expr('main_table.base_subtotal_with_discount * main_table.base_to_global_rate'),
            ], 'main_table')
            ->order('main_table.updated_at DESC')
            ->order('main_table.entity_id DESC');
        $total = (int) $collection->getSize();
        $select = (clone $collection->getSelect())->limitPage($page, $pageSize);
        $member = [];
        if (($page - 1) * $pageSize < $total) {
            foreach ($collection->getConnection()->fetchAll($select) as $row) {
                $member[] = [
                    'cartId' => (int) $row['entity_id'],
                    'storeId' => (int) $row['store_id'],
                    'customerId' => $row['customer_id'] ? (int) $row['customer_id'] : null,
                    'customerName' => self::personName($row['customer_firstname'], $row['customer_middlename'], $row['customer_lastname']),
                    'customerEmail' => $row['customer_email'] !== null && $row['customer_email'] !== '' ? (string) $row['customer_email'] : null,
                    'itemsCount' => (int) $row['items_count'],
                    'itemsQty' => self::quantity($row['items_qty']),
                    'subtotal' => self::amount($row['subtotal']),
                    'couponCode' => $row['coupon_code'] !== null && $row['coupon_code'] !== '' ? (string) $row['coupon_code'] : null,
                    'createdAt' => self::isoDate($row['created_at']),
                    'updatedAt' => self::isoDate($row['updated_at']),
                ];
            }
        }

        return [
            'report' => 'carts-abandoned',
            'currency' => self::currency(),
            'customersOnly' => $customersOnly,
            'scope' => $query->scopeArray(),
            'totalItems' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'member' => $member,
        ];
    }
}
