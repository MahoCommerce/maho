<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class ProductsOrderedReportProvider extends ReportProviderBase
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forRange($filters, $user);
        $limit = $query->readLimit($filters, self::DEFAULT_LIMIT, self::MAX_LIMIT);
        [$from, $to] = $query->utcRange();

        /** @var \Mage_Reports_Model_Resource_Product_Sold_Collection $collection */
        $collection = \Mage::getResourceModel('reports/product_sold_collection');
        $collection->setDateRange($from, $to);
        if ($query->scoped) {
            $collection->setStoreIds($query->storeIds);
        }
        // A second order makes the rank the same on all databases when two products have the same quantity
        $collection->getSelect()->order('order_items.product_id ASC')->limit($limit);

        $member = [];
        foreach ($collection->getConnection()->fetchAll($collection->getSelect()) as $index => $row) {
            $member[] = [
                'rank' => $index + 1,
                'productId' => $row['entity_id'] ? (int) $row['entity_id'] : null,
                'sku' => $row['sku'] !== null ? (string) $row['sku'] : null,
                'name' => (string) $row['order_items_name'],
                'qtyOrdered' => self::quantity($row['ordered_qty']),
            ];
        }

        return $this->listEnvelope('products-ordered', $query, ['limit' => $limit], $member);
    }
}
