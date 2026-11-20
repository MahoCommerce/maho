<?php

/**
 * The product views dataset of the report explorer: the daily views of each product that the views cron aggregates.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
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

class Mage_Reports_Model_Report_Dataset_ProductViews implements DatasetProvider
{
    public const CODE = 'product_views';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('reports');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Product views'), 'products', 'report/products', $resource->getTableName('reports/viewed_aggregated_daily'), 'main', 'store_id');
        foreach ([
            new Field('period', $helper->__('Day'), FieldType::Date, 'main.period', localDate: true),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('product_id', $helper->__('Product'), FieldType::Id, 'main.product_id', labels: 'product'),
            new Field('product_name', $helper->__('Product name'), FieldType::String, 'main.product_name'),
            new Field('views', $helper->__('Views'), FieldType::Integer, (string) $adapter->getIfNullSql('main.views_num', 0)),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('period', $helper->__('Day'));
        $dataset->addMetric(Metric::aggregate('views', $helper->__('Views'), Aggregate::Sum, 'views'));
        $dataset->addMetric(Metric::aggregate('products', $helper->__('Products'), Aggregate::CountDistinct, 'product_id'));
        $dataset->addDimension(new Dimension('product_id', $helper->__('Product'), 'product_id'));
        $dataset->addDimension(new Dimension('store_id', $helper->__('Store view'), 'store_id'));

        $dataset->addPreset(new Preset('most_viewed', $helper->__('Most viewed products'), Mage::helper('sales')->__('Products'), [
            'metrics' => ['views'],
            'dimensions' => ['product_id'],
            'sort' => '-views',
            'range' => 'last_30_days',
        ], ChartType::Bar, $helper->__('The views come from a daily cron job, so today has no views yet.')));

        return $dataset;
    }
}
