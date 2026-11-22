<?php

/**
 * The cart items dataset of the report explorer: one row per top-level cart item, dated by the last activity of its cart.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
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

class Mage_Reports_Model_Report_Dataset_CartItems implements DatasetProvider
{
    public const string CODE = 'cart_items';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('reports');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Cart items'), 'carts', 'report/shopcart', $resource->getTableName('sales/quote_item'), 'main', 'store_id', true);

        $dataset->setRecords(new Records(['updated_at', 'customer_email', 'sku', 'qty', 'row_total', 'cart_state'], 'cart_id', 'report/shopcart', 'carts'));
        $dataset->addCondition('main.parent_item_id IS NULL');
        $dataset->addJoin(new Join('cart', $resource->getTableName('sales/quote'), 'cart.entity_id = main.quote_id AND cart.items_count > 0', JoinType::Inner));
        $dataset->addJoin(new Join(
            'billing',
            $resource->getTableName('sales/quote_address'),
            "billing.quote_id = cart.entity_id AND billing.address_type = 'billing'",
            requires: ['cart'],
        ));
        foreach (Mage_Reports_Model_Report_Dataset_Carts::stateFields($adapter, 'cart', ['cart', 'billing']) as $field) {
            $dataset->addField($field);
        }

        $rate = $adapter->getIfNullSql('cart.base_to_global_rate', 0);
        foreach ([
            new Field('updated_at', $helper->__('Last activity of the cart'), FieldType::Date, 'cart.updated_at', ['cart']),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'cart.store_id', ['cart'], labels: 'store'),
            new Field('cart_id', $helper->__('Cart'), FieldType::Id, 'main.quote_id', filterable: false),
            new Field('product_id', $helper->__('Product'), FieldType::Id, 'main.product_id', labels: 'product'),
            new Field('sku', $helper->__('SKU'), FieldType::String, 'main.sku'),
            new Field('qty', $helper->__('Units'), FieldType::Decimal, (string) $adapter->getIfNullSql('main.qty', 0)),
            new Field('row_total', $helper->__('Value'), FieldType::Money, $adapter->getIfNullSql('main.base_row_total', 0) . " * {$rate}", ['cart']),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('updated_at', $helper->__('Last activity of the cart'));

        foreach ([
            Metric::aggregate('units_in_carts', $helper->__('Units'), Aggregate::Sum, 'qty'),
            Metric::aggregate('cart_items_value', $helper->__('Value'), Aggregate::Sum, 'row_total', MetricFormat::Money),
            Metric::aggregate('carts', $helper->__('Carts'), Aggregate::CountDistinct, 'cart_id'),
            Metric::aggregate('abandoned_units', $helper->__('Abandoned units'), Aggregate::Sum, 'qty', condition: 'is_abandoned', higherIsBetter: false),
            Metric::aggregate('abandoned_items_value', $helper->__('Abandoned value'), Aggregate::Sum, 'row_total', MetricFormat::Money, 'is_abandoned', higherIsBetter: false),
            Metric::aggregate('converted_units', $helper->__('Converted units'), Aggregate::Sum, 'qty', condition: 'is_converted'),
            Metric::formula('item_conversion_rate', $helper->__('Conversion %'), 'converted_units / units_in_carts * 100', MetricFormat::Percent),
        ] as $metric) {
            $dataset->addMetric($metric);
        }
        foreach (['product_id' => $helper->__('Product'), 'sku' => $helper->__('SKU'), 'cart_state' => $helper->__('Cart state'), 'store_id' => $helper->__('Store view')] as $code => $label) {
            $dataset->addDimension(new Dimension($code, $label, $code));
        }

        $group = $helper->__('Shopping cart');
        foreach ([
            new Preset('top_abandoned_products', $helper->__('Most abandoned products'), $group, [
                'metrics' => ['abandoned_units', 'abandoned_items_value', 'carts'],
                'dimensions' => ['product_id'],
                'filters' => [['field' => 'is_abandoned', 'operator' => 'eq', 'value' => 1]],
                'sort' => '-abandoned_units',
                'range' => 'last_30_days',
            ], ChartType::Bar),
            new Preset('products_in_carts', $helper->__('Products in carts'), $group, [
                'metrics' => ['units_in_carts', 'carts', 'converted_units', 'item_conversion_rate'],
                'dimensions' => ['product_id'],
                'sort' => '-units_in_carts',
                'range' => 'last_30_days',
            ], ChartType::Bar, $helper->__('Units of each product in the carts of the period, and how many of them became orders.')),
            new Preset('recent_cart_items', $helper->__('Products in recent carts'), $group, [
                'metrics' => ['units_in_carts', 'cart_items_value', 'carts'],
                'dimensions' => ['product_id'],
                'filters' => [['field' => 'is_recent', 'operator' => 'eq', 'value' => 1]],
                'sort' => '-units_in_carts',
                'range' => 'last_7_days',
            ], ChartType::Bar),
        ] as $preset) {
            $dataset->addPreset($preset);
        }

        return $dataset;
    }
}
