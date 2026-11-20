<?php

/**
 * The order items dataset of the report explorer: one row per top-level order item, with cost and margin.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Maho\Db\Adapter\AdapterInterface;
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

class Mage_Sales_Model_Report_Dataset_Items implements DatasetProvider
{
    public const CODE = 'sales_items';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('sales');
        $resource = Mage::getSingleton('core/resource');
        $itemTable = $resource->getTableName('sales/order_item');

        $dataset = new Dataset(self::CODE, $helper->__('Order items'), 'products', 'report/products', $itemTable, 'main', 'store_id');

        $dataset->addField(new Field('order_increment_id', $helper->__('Order #'), FieldType::String, 'ord.increment_id', ['ord']));

        $dataset->setRecords(new Records(['created_at', 'order_increment_id', 'sku', 'name', 'qty_ordered', 'gross_sales', 'status'], 'order_id', 'sales/order/actions/view', 'orders', 'adminhtml/sales_order/view', 'order_id'));
        $dataset->addCondition('main.parent_item_id IS NULL');
        $dataset->addJoin(new Join('ord', $resource->getTableName('sales/order'), 'ord.entity_id = main.order_id', JoinType::Inner));
        $dataset->addJoin(new Join(
            'child',
            $itemTable,
            "child.parent_item_id = main.item_id AND main.product_type = 'configurable'",
        ));
        $dataset->addJoin(new Join('category', $resource->getTableName('catalog/category_product'), 'category.product_id = main.product_id', manyRows: true));
        Mage_Sales_Model_Report_Dataset_Orders::addScopes($dataset, $adapter, 'ord', ['ord']);

        $rate = $adapter->getIfNullSql('ord.base_to_global_rate', 0);
        $ifNull = static fn(string $sql): string => (string) $adapter->getIfNullSql($sql, 0);
        $units = '(main.qty_ordered - ' . $ifNull('main.qty_canceled') . ')';
        $uncanceledShare = "CASE WHEN main.qty_ordered > 0 THEN {$units} * 1.0 / main.qty_ordered ELSE 0 END";
        $unitCost = 'COALESCE(child.base_cost, main.base_cost)';

        $types = [];
        foreach (Mage::getSingleton('catalog/product_type')->getOptionArray() as $value => $label) {
            $types[(string) $value] = (string) $label;
        }

        foreach ([
            new Field('item_id', $helper->__('Item ID'), FieldType::Id, 'main.item_id', filterable: false),
            new Field('created_at', $helper->__('Order date'), FieldType::Date, 'ord.created_at', ['ord']),
            new Field('updated_at', $helper->__('Last update of the order'), FieldType::Date, 'ord.updated_at', ['ord']),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('status', $helper->__('Order status'), FieldType::String, 'ord.status', ['ord'], labels: 'order_status'),
            new Field('customer_group_id', $helper->__('Customer group'), FieldType::Id, 'ord.customer_group_id', ['ord'], labels: 'customer_group'),
            new Field('product_id', $helper->__('Product'), FieldType::Id, 'main.product_id', labels: 'product'),
            new Field('sku', $helper->__('SKU'), FieldType::String, 'main.sku'),
            new Field('name', $helper->__('Product name'), FieldType::String, 'main.name'),
            new Field('product_type', $helper->__('Product type'), FieldType::String, 'main.product_type', options: $types),
            new Field('category_id', $helper->__('Category'), FieldType::Id, 'category.category_id', ['category'], labels: 'category'),
            new Field('qty_ordered', $helper->__('Quantity ordered'), FieldType::Decimal, $ifNull('main.qty_ordered')),
            new Field('units', $helper->__('Units'), FieldType::Decimal, $units),
            new Field('units_invoiced', $helper->__('Units invoiced'), FieldType::Decimal, $ifNull('main.qty_invoiced')),
            new Field('units_shipped', $helper->__('Units shipped'), FieldType::Decimal, $ifNull('main.qty_shipped')),
            new Field('units_refunded', $helper->__('Units refunded'), FieldType::Decimal, $ifNull('main.qty_refunded')),
            new Field('gross_sales', $helper->__('Gross sales'), FieldType::Money, "{$ifNull('main.base_row_total')} * ({$uncanceledShare}) * {$rate}", ['ord']),
            new Field('discount', $helper->__('Discounts'), FieldType::Money, "ABS({$ifNull('main.base_discount_amount')}) * ({$uncanceledShare}) * {$rate}", ['ord']),
            new Field('returns', $helper->__('Returns'), FieldType::Money, "{$ifNull('main.base_amount_refunded')} * {$rate}", ['ord']),
            new Field('cost', $helper->__('Cost'), FieldType::Money, "{$ifNull($unitCost)} * {$units} * {$rate}", ['ord', 'child']),
            new Field(
                'has_cost',
                $helper->__('With a cost'),
                FieldType::Boolean,
                "CASE WHEN {$unitCost} IS NOT NULL AND {$unitCost} > 0 THEN 1 ELSE 0 END",
                ['child'],
            ),
            new Field('order_id', $helper->__('Order'), FieldType::Id, 'main.order_id', filterable: false),
        ] as $field) {
            $dataset->addField($field);
        }
        $this->addAttributeFields($dataset);
        $dataset->addDateField('created_at', $helper->__('Order date'))->addDateField('updated_at');

        $sum = static fn(string $code, string $label, MetricFormat $format = MetricFormat::Money, bool $higherIsBetter = true): Metric
            => Metric::aggregate($code, $label, Aggregate::Sum, $code, $format, higherIsBetter: $higherIsBetter);
        foreach ([
            Metric::aggregate('orders', $helper->__('Orders'), Aggregate::CountDistinct, 'order_id'),
            Metric::aggregate('lines', $helper->__('Order lines'), Aggregate::Count, null),
            $sum('qty_ordered', $helper->__('Quantity ordered'), MetricFormat::Number),
            $sum('units', $helper->__('Units'), MetricFormat::Number),
            $sum('units_invoiced', $helper->__('Units invoiced'), MetricFormat::Number),
            $sum('units_shipped', $helper->__('Units shipped'), MetricFormat::Number),
            $sum('units_refunded', $helper->__('Units refunded'), MetricFormat::Number, false),
            $sum('gross_sales', $helper->__('Gross sales')),
            $sum('discount', $helper->__('Discounts'), higherIsBetter: false),
            $sum('returns', $helper->__('Returns'), higherIsBetter: false),
            $sum('cost', $helper->__('Cost'), higherIsBetter: false),
            Metric::aggregate('lines_with_cost', $helper->__('Lines with a cost'), Aggregate::Count, null, condition: 'has_cost', hidden: true),
            Metric::formula('net_sales', $helper->__('Net sales'), 'gross_sales - discount - returns', MetricFormat::Money),
            Metric::formula('gross_margin', $helper->__('Gross margin'), 'net_sales - cost', MetricFormat::Money),
            Metric::formula('margin_pct', $helper->__('Margin %'), 'gross_margin / net_sales * 100', MetricFormat::Percent),
            Metric::formula('return_rate', $helper->__('Return rate %'), 'units_refunded / units * 100', MetricFormat::Percent, higherIsBetter: false),
            Metric::formula(
                'cost_coverage',
                $helper->__('Cost coverage %'),
                'lines_with_cost / lines * 100',
                MetricFormat::Percent,
                description: $helper->__('The share of order lines whose product had a cost. A margin is reliable only near 100%.'),
            ),
            Metric::formula('avg_price', $helper->__('Average unit price'), 'gross_sales / units', MetricFormat::Money),
        ] as $metric) {
            $dataset->addMetric($metric);
        }

        foreach ([
            ['product_id', $helper->__('Product'), false],
            ['sku', $helper->__('SKU'), false],
            ['name', $helper->__('Product name'), false],
            ['product_type', $helper->__('Product type'), false],
            ['category_id', $helper->__('Category'), true],
            ['store_id', $helper->__('Store view'), false],
            ['status', $helper->__('Order status'), false],
            ['customer_group_id', $helper->__('Customer group'), false],
        ] as [$code, $label, $nonAdditive]) {
            $dataset->addDimension(new Dimension($code, $label, $code, nonAdditive: $nonAdditive));
        }

        $products = $helper->__('Products');
        foreach ([
            new Preset('product_performance', $helper->__('Product performance'), $products, [
                'metrics' => ['units', 'net_sales', 'cost', 'gross_margin', 'margin_pct', 'return_rate'],
                'dimensions' => ['product_id'],
                'sort' => '-net_sales',
                'range' => 'last_30_days',
            ], ChartType::Bar, $helper->__('Sales, cost and margin of each product. Check the cost coverage before you trust the margin.')),
            new Preset('products_ordered', $helper->__('Products ordered'), $products, [
                'scope' => 'not_canceled',
                'metrics' => ['qty_ordered'],
                'dimensions' => ['product_id'],
                'sort' => '-qty_ordered',
                'range' => 'last_30_days',
            ], ChartType::Bar),
            new Preset('bestsellers', $helper->__('Bestsellers'), $products, [
                'scope' => 'not_canceled',
                'metrics' => ['qty_ordered', 'net_sales'],
                'dimensions' => ['product_id'],
                'sort' => '-qty_ordered',
                'range' => 'last_30_days',
                'limit' => 10,
            ], ChartType::Bar),
            new Preset('sales_by_category', $helper->__('Sales by category'), $products, [
                'metrics' => ['units', 'net_sales'],
                'dimensions' => ['category_id'],
                'sort' => '-net_sales',
                'range' => 'last_30_days',
            ], ChartType::Bar, $helper->__('A product in several categories counts in each of them.')),
            new Preset('most_refunded_products', $helper->__('Most refunded products'), $products, [
                'metrics' => ['units_refunded', 'returns', 'return_rate'],
                'dimensions' => ['product_id'],
                'filters' => [['field' => 'units_refunded', 'operator' => 'gt', 'value' => 0]],
                'sort' => '-units_refunded',
                'range' => 'last_90_days',
            ], ChartType::Bar),
        ] as $preset) {
            $dataset->addPreset($preset);
        }
        foreach ($dataset->getDimensions() as $dimension) {
            if (str_starts_with($dimension->code, 'attr_')) {
                $dataset->addPreset(new Preset(
                    'sales_by_' . $dimension->code,
                    $helper->__('Sales by %s', $dimension->label),
                    $products,
                    ['metrics' => ['units', 'net_sales'], 'dimensions' => [$dimension->code], 'sort' => '-net_sales', 'range' => 'last_30_days'],
                    ChartType::Bar,
                ));
            }
        }

        return $dataset;
    }

    /**
     * A dimension for each product attribute of the setting "Attributes for Sales by Attribute". The value comes from
     * the default scope. A select attribute shows the label of its option.
     */
    private function addAttributeFields(Dataset $dataset): void
    {
        $codes = array_filter(explode(',', (string) Mage::getStoreConfig('reports/explorer/report_attributes')));
        foreach ($codes as $code) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
            if (!$attribute || !$attribute->getId() || !in_array($attribute->getBackendType(), ['int', 'varchar'], true)) {
                continue;
            }
            $alias = 'attr_' . preg_replace('/[^a-z0-9_]/', '', $code);
            $dataset->addJoin(new Join(
                $alias,
                $attribute->getBackendTable(),
                "{$alias}.entity_id = main.product_id AND {$alias}.attribute_id = " . (int) $attribute->getId()
                    . " AND {$alias}.store_id = " . Mage_Core_Model_App::ADMIN_STORE_ID,
            ));
            $isOption = $attribute->usesSource() && $attribute->getBackendType() === 'int';
            $label = (string) ($attribute->getStoreLabel() ?: $attribute->getFrontendLabel() ?: $code);
            $dataset->addField(new Field(
                $alias,
                $label,
                $isOption ? FieldType::Id : FieldType::String,
                "{$alias}.value",
                [$alias],
                labels: $isOption ? 'attribute_option' : null,
            ));
            $dataset->addDimension(new Dimension($alias, $label, $alias));
        }
    }
}
