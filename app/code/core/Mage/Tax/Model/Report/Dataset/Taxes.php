<?php

/**
 * The tax dataset of the report explorer: one row per tax rate applied to an order.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
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

class Mage_Tax_Model_Report_Dataset_Taxes implements DatasetProvider
{
    public const CODE = 'sales_taxes';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('tax');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Taxes'), 'sales', 'report/salesroot', $resource->getTableName('tax/sales_order_tax'), 'main', 'store_id');
        $dataset->addJoin(new Join('ord', $resource->getTableName('sales/order'), 'ord.entity_id = main.order_id', JoinType::Inner));
        Mage_Sales_Model_Report_Dataset_Orders::addScopes($dataset, $adapter, 'ord', ['ord']);

        foreach ([
            new Field('created_at', $helper->__('Order date'), FieldType::Date, 'ord.created_at', ['ord']),
            new Field('updated_at', $helper->__('Last update of the order'), FieldType::Date, 'ord.updated_at', ['ord']),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'ord.store_id', ['ord'], labels: 'store'),
            new Field('status', $helper->__('Order status'), FieldType::String, 'ord.status', ['ord'], labels: 'order_status'),
            new Field('order_id', $helper->__('Order'), FieldType::Id, 'main.order_id', filterable: false),
            new Field('code', $helper->__('Tax rate'), FieldType::String, 'main.code'),
            new Field('title', $helper->__('Tax title'), FieldType::String, 'main.title'),
            new Field('percent', $helper->__('Percent'), FieldType::Decimal, 'main.percent'),
            new Field(
                'tax',
                $helper->__('Tax'),
                FieldType::Money,
                $adapter->getIfNullSql('main.base_amount', 0) . ' * ' . $adapter->getIfNullSql('ord.base_to_global_rate', 0),
                ['ord'],
            ),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('created_at', $helper->__('Order date'))->addDateField('updated_at');

        $dataset->addMetric(Metric::aggregate('orders', $helper->__('Orders'), Aggregate::CountDistinct, 'order_id'));
        $dataset->addMetric(Metric::aggregate('tax', $helper->__('Tax'), Aggregate::Sum, 'tax', MetricFormat::Money));
        $dataset->addMetric(Metric::aggregate('max_percent', $helper->__('Percent'), Aggregate::Max, 'percent', MetricFormat::Decimal));
        foreach (['code' => $helper->__('Tax rate'), 'percent' => $helper->__('Percent'), 'store_id' => $helper->__('Store view'), 'status' => $helper->__('Order status')] as $code => $label) {
            $dataset->addDimension(new Dimension($code, $label, $code));
        }

        $dataset->addPreset(new Preset('tax_by_rate', $helper->__('Tax by rate'), Mage::helper('sales')->__('Sales'), [
            'metrics' => ['orders', 'tax'],
            'dimensions' => ['code', 'percent'],
            'sort' => '-tax',
            'range' => 'last_30_days',
            'compare' => 'previous_period',
        ], ChartType::Bar));

        return $dataset;
    }
}
