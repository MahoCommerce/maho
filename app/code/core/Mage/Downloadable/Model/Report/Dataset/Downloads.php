<?php

/**
 * The downloads dataset of the report explorer: one row per purchased download link.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
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
use Maho\Report\Definition\Preset;

class Mage_Downloadable_Model_Report_Dataset_Downloads implements DatasetProvider
{
    public const CODE = 'downloads';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('downloadable');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Downloads'), 'products', 'report/products/downloads', $resource->getTableName('downloadable/link_purchased_item'), 'main', null, true);
        foreach ([
            new Field('product_id', $helper->__('Product'), FieldType::Id, 'main.product_id', labels: 'product'),
            new Field('link_title', $helper->__('Link'), FieldType::String, 'main.link_title'),
            new Field('status', $helper->__('Status'), FieldType::String, 'main.status'),
            new Field('bought', $helper->__('Downloads bought'), FieldType::Integer, (string) $adapter->getIfNullSql('main.number_of_downloads_bought', 0)),
            new Field('used', $helper->__('Downloads used'), FieldType::Integer, (string) $adapter->getIfNullSql('main.number_of_downloads_used', 0)),
            new Field(
                'has_activity',
                $helper->__('Bought or used'),
                FieldType::Boolean,
                'CASE WHEN main.number_of_downloads_bought > 0 OR main.number_of_downloads_used > 0 THEN 1 ELSE 0 END',
            ),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addMetric(Metric::aggregate('links', $helper->__('Links bought'), Aggregate::Count, null));
        $dataset->addMetric(Metric::aggregate('purchases', $helper->__('Downloads bought'), Aggregate::Sum, 'bought', description: $helper->__('0 means unlimited downloads.')));
        $dataset->addMetric(Metric::aggregate('downloads', $helper->__('Downloads used'), Aggregate::Sum, 'used'));
        $dataset->addDimension(new Dimension('product_id', $helper->__('Product'), 'product_id'));
        $dataset->addDimension(new Dimension('link_title', $helper->__('Link'), 'link_title'));
        $dataset->addDimension(new Dimension('status', $helper->__('Status'), 'status'));

        $dataset->addPreset(new Preset('downloads', $helper->__('Downloads'), Mage::helper('sales')->__('Products'), [
            'metrics' => ['purchases', 'downloads'],
            'dimensions' => ['product_id', 'link_title'],
            'filters' => [['field' => 'has_activity', 'operator' => 'eq', 'value' => 1]],
            'sort' => '-downloads',
        ], ChartType::None));

        return $dataset;
    }
}
