<?php

/**
 * The tags dataset of the report explorer: one row per tag that a customer gave to a product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
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

class Mage_Tag_Model_Report_Dataset_Tags implements DatasetProvider
{
    public const CODE = 'tags';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('tag');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Tags'), 'tags', 'report/tags', $resource->getTableName('tag/relation'), 'main', 'store_id');
        $dataset->addJoin(new Join('tag', $resource->getTableName('tag/tag'), 'tag.tag_id = main.tag_id', JoinType::Inner));

        foreach ([
            new Field('tag_id', $helper->__('Tag ID'), FieldType::Id, 'main.tag_id', filterable: false),
            new Field('tag_name', $helper->__('Tag'), FieldType::String, 'tag.name', ['tag']),
            new Field('tag_status', $helper->__('Tag status'), FieldType::Integer, 'tag.status', ['tag'], options: [
                Mage_Tag_Model_Tag::STATUS_DISABLED => $helper->__('Disabled'),
                Mage_Tag_Model_Tag::STATUS_PENDING => $helper->__('Pending'),
                Mage_Tag_Model_Tag::STATUS_APPROVED => $helper->__('Approved'),
            ]),
            new Field('is_active', $helper->__('Active'), FieldType::Boolean, (string) $adapter->getIfNullSql('main.active', 0)),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('product_id', $helper->__('Product'), FieldType::Id, 'main.product_id', labels: 'product'),
            new Field('customer_id', $helper->__('Customer'), FieldType::Id, 'main.customer_id', personal: true, labels: 'customer'),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addMetric(Metric::aggregate('taggings', $helper->__('Uses'), Aggregate::Count, null));
        $dataset->addMetric(Metric::aggregate('tags', $helper->__('Tags'), Aggregate::CountDistinct, 'tag_id'));
        $dataset->addMetric(Metric::aggregate('products', $helper->__('Products'), Aggregate::CountDistinct, 'product_id'));
        foreach (['tag_name' => $helper->__('Tag'), 'product_id' => $helper->__('Product'), 'customer_id' => $helper->__('Customer'), 'store_id' => $helper->__('Store view')] as $code => $label) {
            $dataset->addDimension(new Dimension($code, $label, $code));
        }

        $group = $helper->__('Tags');
        $approved = ['field' => 'tag_status', 'operator' => 'eq', 'value' => Mage_Tag_Model_Tag::STATUS_APPROVED];
        $dataset->addPreset(new Preset('popular_tags', $helper->__('Popular tags'), $group, [
            'metrics' => ['taggings'],
            'dimensions' => ['tag_name'],
            'filters' => [$approved, ['field' => 'is_active', 'operator' => 'eq', 'value' => 1]],
            'sort' => '-taggings',
        ], ChartType::Bar));
        $dataset->addPreset(new Preset('tags_by_product', $helper->__('Tags by product'), $group, [
            'metrics' => ['taggings', 'tags'],
            'dimensions' => ['product_id'],
            'filters' => [$approved],
            'sort' => '-taggings',
        ], ChartType::Bar));
        $dataset->addPreset(new Preset('tags_by_customer', $helper->__('Tags by customer'), $group, [
            'metrics' => ['taggings', 'tags'],
            'dimensions' => ['customer_id'],
            'filters' => [$approved],
            'sort' => '-taggings',
        ], ChartType::None));

        return $dataset;
    }
}
