<?php

/**
 * The stock dataset of the report explorer: one row per stock item of a product with a quantity, with its value and its sales.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

use Maho\Db\Adapter\AdapterInterface;
use Maho\Report\Context;
use Maho\Report\DatasetProvider;
use Maho\Report\Definition\Aggregate;
use Maho\Report\Definition\ChartType;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Dimension;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Join;
use Maho\Report\Definition\JoinType;
use Maho\Report\Definition\Metric;
use Maho\Report\Definition\MetricFormat;
use Maho\Report\Definition\Preset;
use Maho\Report\Definition\Records;

class Mage_CatalogInventory_Model_Report_Dataset_Stock implements DatasetProvider
{
    public const CODE = 'catalog_stock';
    public const SALES_DAYS = 30;

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('cataloginventory');
        $resource = Mage::getSingleton('core/resource');
        $itemTable = $resource->getTableName('sales/order_item');
        $orderTable = $resource->getTableName('sales/order');
        $canceled = $adapter->quote(Mage_Sales_Model_Order::STATE_CANCELED);

        $dataset = new Dataset(self::CODE, $helper->__('Stock'), 'products', 'report/products', $resource->getTableName('cataloginventory/stock_item'), 'main', null, true);

        $dataset->setRecords(new Records(['sku', 'product_type', 'qty', 'is_in_stock', 'units_sold_30d', 'cost_value', 'retail_value'], 'product_id', 'catalog/products', 'products', 'adminhtml/catalog_product/edit', 'id'));
        $dataset->addJoin(new Join('product', $resource->getTableName('catalog/product'), 'product.entity_id = main.product_id', JoinType::Inner));
        $dataset->addCondition('main.product_id IN (SELECT qty_product.entity_id FROM ' . $adapter->quoteIdentifier($resource->getTableName('catalog/product'))
            . ' qty_product WHERE qty_product.type_id IN (' . $adapter->quote(array_keys(Mage::helper('cataloginventory')->getIsQtyTypeIds(true))) . '))');

        foreach (['price', 'cost'] as $code) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
            if ($attribute && $attribute->getId()) {
                $dataset->addJoin(new Join(
                    $code,
                    $attribute->getBackendTable(),
                    "{$code}.entity_id = main.product_id AND {$code}.attribute_id = " . (int) $attribute->getId()
                        . " AND {$code}.store_id = " . Mage_Core_Model_App::ADMIN_STORE_ID,
                ));
            }
        }
        // The sales of the store views of the request, so that a caller limited to some store views reads only their sales
        $inStores = static fn(Context $context, string $item): string => $context->storeIds === null
            ? '1 = 1'
            : "{$item}.store_id IN (" . implode(', ', array_map(intval(...), $context->storeIds ?: [-1])) . ')';
        $dataset->addJoin(new Join(
            'sold',
            static fn(Context $context): \Maho\Db\Select => $adapter->select()
                ->from(['sold_item' => $itemTable], [
                    'product_id' => 'sold_item.product_id',
                    'qty' => new Maho\Db\Expr('SUM(sold_item.qty_ordered - ' . $adapter->getIfNullSql('sold_item.qty_canceled', 0) . ')'),
                ])
                ->joinInner(['sold_order' => $orderTable], 'sold_order.entity_id = sold_item.order_id', [])
                ->where('sold_item.created_at >= ?', $context->utcAgo(self::SALES_DAYS, 'days'))
                ->where("sold_order.state <> {$canceled}")
                ->where($inStores($context, 'sold_item'))
                ->group('sold_item.product_id'),
            'sold.product_id = main.product_id',
        ));

        $manage = (int) Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);
        $notify = (float) Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_NOTIFY_STOCK_QTY);
        $managed = "(CASE WHEN main.use_config_manage_stock = 1 THEN {$manage} ELSE main.manage_stock END) = 1";
        $notifyQty = "CASE WHEN main.use_config_notify_stock_qty = 1 THEN {$notify} ELSE main.notify_stock_qty END";
        $qty = (string) $adapter->getIfNullSql('main.qty', 0);
        $soldSince = static fn(Context $context, string $since): string => 'EXISTS (SELECT 1 FROM ' . $adapter->quoteIdentifier($itemTable) . ' recent_item'
            . ' INNER JOIN ' . $adapter->quoteIdentifier($orderTable) . ' recent_order ON recent_order.entity_id = recent_item.order_id'
            . " WHERE recent_item.product_id = main.product_id AND recent_item.created_at >= {$since} AND recent_order.state <> {$canceled} AND {$inStores($context, 'recent_item')})";

        $types = [];
        foreach (Mage::getSingleton('catalog/product_type')->getOptionArray() as $value => $label) {
            $types[(string) $value] = (string) $label;
        }
        foreach ([
            new Field('product_id', $helper->__('Product'), FieldType::Id, 'main.product_id', labels: 'product'),
            new Field('sku', $helper->__('SKU'), FieldType::String, 'product.sku', ['product']),
            new Field('product_type', $helper->__('Product type'), FieldType::String, 'product.type_id', ['product'], options: $types),
            new Field('qty', $helper->__('Quantity'), FieldType::Decimal, $qty),
            new Field('is_in_stock', $helper->__('In stock'), FieldType::Boolean, (string) $adapter->getIfNullSql('main.is_in_stock', 0)),
            new Field('is_managed', $helper->__('Stock is managed'), FieldType::Boolean, "CASE WHEN {$managed} THEN 1 ELSE 0 END"),
            new Field('is_low_stock', $helper->__('Low stock'), FieldType::Boolean, "CASE WHEN {$managed} AND {$qty} < {$notifyQty} THEN 1 ELSE 0 END"),
            new Field('cost_value', $helper->__('Stock value at cost'), FieldType::Money, "{$qty} * " . $adapter->getIfNullSql('cost.value', 0), ['cost']),
            new Field('retail_value', $helper->__('Stock value at price'), FieldType::Money, "{$qty} * " . $adapter->getIfNullSql('price.value', 0), ['price']),
            new Field('units_sold_30d', $helper->__('Units sold in 30 days'), FieldType::Decimal, (string) $adapter->getIfNullSql('sold.qty', 0), ['sold']),
            new Field(
                'sold_recently',
                $helper->__('Sold in the dead stock days'),
                FieldType::Boolean,
                static fn(Context $context): string => 'CASE WHEN ' . $soldSince($context, $context->quote($context->utcAgo((int) $context->setting('dead_stock_days', 90), 'days')))
                    . ' THEN 1 ELSE 0 END',
            ),
            new Field(
                'ever_sold',
                $helper->__('Ever sold'),
                FieldType::Boolean,
                static fn(Context $context): string => 'CASE WHEN EXISTS (SELECT 1 FROM ' . $adapter->quoteIdentifier($itemTable)
                    . " any_item WHERE any_item.product_id = main.product_id AND {$inStores($context, 'any_item')}) THEN 1 ELSE 0 END",
            ),
        ] as $field) {
            $dataset->addField($field);
        }

        foreach ([
            Metric::aggregate('products', $helper->__('Products'), Aggregate::CountDistinct, 'product_id'),
            Metric::aggregate('qty_on_hand', $helper->__('Quantity'), Aggregate::Sum, 'qty'),
            Metric::aggregate('stock_value_cost', $helper->__('Stock value at cost'), Aggregate::Sum, 'cost_value', MetricFormat::Money),
            Metric::aggregate('stock_value_retail', $helper->__('Stock value at price'), Aggregate::Sum, 'retail_value', MetricFormat::Money),
            Metric::aggregate('units_sold_30d', $helper->__('Units sold in 30 days'), Aggregate::Sum, 'units_sold_30d'),
            Metric::formula('avg_daily_sales', $helper->__('Units sold per day'), 'units_sold_30d / 30', MetricFormat::Decimal),
            Metric::formula('coverage_days', $helper->__('Days of stock left'), 'qty_on_hand / avg_daily_sales', MetricFormat::Decimal),
        ] as $metric) {
            $dataset->addMetric($metric);
        }
        $dataset->addDimension(new Dimension('product_id', $helper->__('Product'), 'product_id'));
        $dataset->addDimension(new Dimension('sku', $helper->__('SKU'), 'sku'));
        $dataset->addDimension(new Dimension('product_type', $helper->__('Product type'), 'product_type'));

        $group = Mage::helper('sales')->__('Products');
        $inStock = ['field' => 'qty', 'operator' => 'gt', 'value' => 0];
        foreach ([
            new Preset('stock_value', $helper->__('Stock value'), $group, [
                'metrics' => ['qty_on_hand', 'stock_value_cost', 'stock_value_retail'],
                'dimensions' => ['product_id'],
                'filters' => [$inStock],
                'sort' => '-stock_value_cost',
            ], ChartType::Bar),
            new Preset('low_stock', $helper->__('Low stock'), $group, [
                'metrics' => ['qty_on_hand'],
                'dimensions' => ['product_id'],
                'filters' => [['field' => 'is_low_stock', 'operator' => 'eq', 'value' => 1]],
                'sort' => ['key' => 'qty_on_hand', 'direction' => 'asc'],
            ], ChartType::None, $helper->__('Products with managed stock whose quantity is below their notify quantity.')),
            new Preset('low_stock_coverage', $helper->__('Days of stock left'), $group, [
                'metrics' => ['qty_on_hand', 'units_sold_30d', 'avg_daily_sales', 'coverage_days'],
                'dimensions' => ['product_id'],
                'filters' => [$inStock, ['field' => 'units_sold_30d', 'operator' => 'gt', 'value' => 0]],
                'sort' => ['key' => 'coverage_days', 'direction' => 'asc'],
            ], ChartType::None, $helper->__('How long the stock lasts at the sales speed of the last 30 days.')),
            new Preset('dead_stock', $helper->__('Dead stock'), $group, [
                'metrics' => ['qty_on_hand', 'stock_value_cost', 'stock_value_retail'],
                'dimensions' => ['product_id'],
                'filters' => [$inStock, ['field' => 'sold_recently', 'operator' => 'eq', 'value' => 0]],
                'sort' => '-stock_value_cost',
            ], ChartType::None, $helper->__('Products in stock with no sale in the number of days of the setting "Dead stock after".')),
            new Preset('never_sold', $helper->__('Products never sold'), $group, [
                'metrics' => ['qty_on_hand', 'stock_value_cost'],
                'dimensions' => ['product_id'],
                'filters' => [['field' => 'ever_sold', 'operator' => 'eq', 'value' => 0]],
                'sort' => '-qty_on_hand',
            ], ChartType::None),
        ] as $preset) {
            $dataset->addPreset($preset);
        }

        return $dataset;
    }
}
