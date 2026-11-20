<?php

/**
 * The invoices dataset of the report explorer: one row per invoice, dated by the invoice.
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

class Mage_Sales_Model_Report_Dataset_Invoices implements DatasetProvider
{
    public const CODE = 'sales_invoices';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('sales');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Invoices'), 'sales', 'report/salesroot', $resource->getTableName('sales/invoice'), 'main', 'store_id');

        $dataset->addField(new Field('invoice_id', $helper->__('Invoice'), FieldType::Id, 'main.entity_id', filterable: false));

        $dataset->addField(new Field('increment_id', $helper->__('Invoice #'), FieldType::String, 'main.increment_id'));

        $dataset->addField(new Field('order_increment_id', $helper->__('Order #'), FieldType::String, 'ord.increment_id', ['ord']));

        $dataset->setRecords(new Records(['increment_id', 'created_at', 'order_increment_id', 'status', 'grand_total'], 'invoice_id', 'sales/invoice', 'invoices', 'adminhtml/sales_order_invoice/view', 'invoice_id'));
        $dataset->addJoin(new Join('ord', $resource->getTableName('sales/order'), 'ord.entity_id = main.order_id', JoinType::Inner));
        Mage_Sales_Model_Report_Dataset_Orders::addScopes($dataset, $adapter, 'ord', ['ord']);
        $dataset->setDefaultScope('not_canceled');

        $rate = $adapter->getIfNullSql('main.base_to_global_rate', 0);
        $amount = static fn(string $column): string => $adapter->getIfNullSql("main.{$column}", 0) . " * {$rate}";

        foreach ([
            new Field('created_at', $helper->__('Date'), FieldType::Date, 'main.created_at'),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('order_id', $helper->__('Order'), FieldType::Id, 'main.order_id', filterable: false),
            new Field('status', $helper->__('Order status'), FieldType::String, 'ord.status', ['ord'], labels: 'order_status'),
            new Field('grand_total', $helper->__('Invoiced'), FieldType::Money, $amount('base_grand_total')),
            new Field('subtotal', $helper->__('Subtotal'), FieldType::Money, $amount('base_subtotal')),
            new Field('tax', $helper->__('Tax'), FieldType::Money, $amount('base_tax_amount')),
            new Field('shipping', $helper->__('Shipping'), FieldType::Money, $amount('base_shipping_amount')),
            new Field('discount', $helper->__('Discounts'), FieldType::Money, 'ABS(' . $adapter->getIfNullSql('main.base_discount_amount', 0) . ") * {$rate}"),
            new Field('units', $helper->__('Units'), FieldType::Decimal, (string) $adapter->getIfNullSql('main.total_qty', 0)),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('created_at', $helper->__('Invoice date'));

        foreach ([
            Metric::aggregate('invoices', $helper->__('Invoices'), Aggregate::Count, null),
            Metric::aggregate('orders', $helper->__('Orders'), Aggregate::CountDistinct, 'order_id'),
            Metric::aggregate('invoiced', $helper->__('Invoiced'), Aggregate::Sum, 'grand_total', MetricFormat::Money),
            Metric::aggregate('subtotal', $helper->__('Subtotal'), Aggregate::Sum, 'subtotal', MetricFormat::Money),
            Metric::aggregate('tax', $helper->__('Tax'), Aggregate::Sum, 'tax', MetricFormat::Money),
            Metric::aggregate('shipping', $helper->__('Shipping'), Aggregate::Sum, 'shipping', MetricFormat::Money),
            Metric::aggregate('discount', $helper->__('Discounts'), Aggregate::Sum, 'discount', MetricFormat::Money, higherIsBetter: false),
            Metric::aggregate('units', $helper->__('Units'), Aggregate::Sum, 'units'),
        ] as $metric) {
            $dataset->addMetric($metric);
        }
        $dataset->addDimension(new Dimension('store_id', $helper->__('Store view'), 'store_id'));
        $dataset->addDimension(new Dimension('status', $helper->__('Order status'), 'status'));

        $dataset->addPreset(new Preset('invoiced', $helper->__('Invoiced by invoice date'), $helper->__('Sales'), [
            'metrics' => ['invoices', 'orders', 'invoiced', 'tax', 'shipping'],
            'dimensions' => ['period:day'],
            'range' => 'last_30_days',
            'compare' => 'previous_period',
        ], ChartType::Line, $helper->__('Each invoice counts on its own date, so a partial invoice counts when it is created.')));

        return $dataset;
    }
}
