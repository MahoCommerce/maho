<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class ProductsLowStockReportProvider extends ReportProviderBase
{
    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forScope($filters, $user);
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(['filters' => $filters], 20, 100);
        $configStoreId = $query->storeId;

        /** @var \Mage_Reports_Model_Resource_Product_Lowstock_Collection $collection */
        $collection = \Mage::getResourceModel('reports/product_lowstock_collection');
        $collection->addAttributeToSelect('name')
            ->setStoreId($configStoreId ?? \Mage_Core_Model_App::ADMIN_STORE_ID)
            ->filterByIsQtyProductTypes()
            ->joinInventoryItem('qty')
            ->useManageStockFilter($configStoreId)
            ->useNotifyStockQtyFilter($configStoreId);
        if ($query->scoped) {
            $collection->addWebsiteFilter($query->websiteIds() ?: [-1]);
        }

        // The threshold of each item: its own notify_stock_qty, or the configuration value
        $adapter = $collection->getConnection();
        $collection->getSelect()->columns([
            'notify_stock_qty' => $adapter->getCheckSql(
                'lowstock_inventory_item.use_config_notify_stock_qty = 1',
                (string) \Mage::getStoreConfigAsFloat(\Mage_CatalogInventory_Model_Stock_Item::XML_PATH_NOTIFY_STOCK_QTY, $configStoreId),
                'lowstock_inventory_item.notify_stock_qty',
            ),
        ]);
        $collection->getSelect()->order('lowstock_inventory_item.qty ASC')->order('e.entity_id ASC');
        $collection->setPageSize($pageSize)->setCurPage($page);

        $total = (int) $collection->getSize();
        $member = [];
        if (($page - 1) * $pageSize < $total) {
            foreach ($collection as $product) {
                $member[] = [
                    'productId' => (int) $product->getId(),
                    'sku' => (string) $product->getData('sku'),
                    'name' => (string) $product->getData('name'),
                    'type' => (string) $product->getData('type_id'),
                    'qty' => self::quantity($product->getData('qty')),
                    'notifyStockQty' => self::quantity($product->getData('notify_stock_qty')),
                ];
            }
        }

        return [
            'report' => 'products-low-stock',
            'scope' => $query->scopeArray(),
            'totalItems' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'member' => $member,
        ];
    }
}
