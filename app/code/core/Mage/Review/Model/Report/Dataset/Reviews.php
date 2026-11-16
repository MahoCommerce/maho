<?php

/**
 * The reviews dataset of the report explorer: one row per product review.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
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
use Maho\Report\Definition\Records;

class Mage_Review_Model_Report_Dataset_Reviews implements DatasetProvider
{
    public const CODE = 'reviews';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('review');
        $resource = Mage::getSingleton('core/resource');
        $productEntity = (int) Mage::getModel('review/review')->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE);

        $dataset = new Dataset(self::CODE, $helper->__('Reviews'), 'reviews', 'report/review', $resource->getTableName('review/review'), 'main', 'store_id');

        $dataset->addField(new Field('review_id', $helper->__('Review'), FieldType::Id, 'main.review_id', filterable: false));

        $dataset->addField(new Field('title', $helper->__('Title'), FieldType::String, 'detail.title', ['detail']));

        $dataset->setRecords(new Records(['created_at', 'product_id', 'title', 'nickname', 'status_id'], 'review_id', 'catalog/reviews_ratings/reviews/all', 'reviews', 'adminhtml/catalog_product_review/edit', 'id'));
        $dataset->addCondition('main.entity_id = ' . $productEntity);
        $dataset->addJoin(new Join('detail', $resource->getTableName('review/review_detail'), 'detail.review_id = main.review_id', JoinType::Inner));

        $statuses = [];
        foreach (Mage::getResourceModel('review/review_status_collection') as $status) {
            $statuses[(int) $status->getId()] = $helper->__($status->getStatusCode());
        }
        foreach ([
            new Field('created_at', $helper->__('Date'), FieldType::Date, 'main.created_at'),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'detail.store_id', ['detail'], labels: 'store'),
            new Field('product_id', $helper->__('Product'), FieldType::Id, 'main.entity_pk_value', labels: 'product'),
            new Field('status_id', $helper->__('Status'), FieldType::Id, 'main.status_id', options: $statuses),
            new Field('customer_id', $helper->__('Customer'), FieldType::Id, 'detail.customer_id', ['detail'], personal: true, labels: 'customer'),
            new Field('nickname', $helper->__('Nickname'), FieldType::String, 'detail.nickname', ['detail'], personal: true),
            new Field(
                'customer_type',
                $helper->__('Customer type'),
                FieldType::String,
                "CASE WHEN detail.customer_id IS NULL THEN 'guest' ELSE 'registered' END",
                ['detail'],
                options: ['guest' => $helper->__('Guest'), 'registered' => $helper->__('Registered')],
            ),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('created_at', $helper->__('Date'));
        $dataset->addMetric(Metric::aggregate('reviews', $helper->__('Reviews'), Aggregate::Count, null));
        $dataset->addMetric(Metric::aggregate('products', $helper->__('Products'), Aggregate::CountDistinct, 'product_id'));
        $dataset->addMetric(Metric::aggregate('last_review_at', $helper->__('Last review'), Aggregate::Max, 'created_at', Maho\Report\Definition\MetricFormat::Date));
        foreach (['product_id' => $helper->__('Product'), 'status_id' => $helper->__('Status'), 'store_id' => $helper->__('Store view'), 'customer_id' => $helper->__('Customer'), 'customer_type' => $helper->__('Customer type')] as $code => $label) {
            $dataset->addDimension(new Dimension($code, $label, $code));
        }

        $group = $helper->__('Reviews');
        $dataset->addPreset(new Preset('reviews_by_product', $helper->__('Reviews by product'), $group, [
            'metrics' => ['reviews', 'last_review_at'],
            'dimensions' => ['product_id'],
            'sort' => '-reviews',
            'range' => 'last_12_months',
        ], ChartType::Bar));
        $dataset->addPreset(new Preset('reviews_by_customer', $helper->__('Reviews by customer'), $group, [
            'metrics' => ['reviews', 'last_review_at'],
            'dimensions' => ['customer_id'],
            'filters' => [['field' => 'customer_type', 'operator' => 'eq', 'value' => 'registered']],
            'sort' => '-reviews',
            'range' => 'last_12_months',
        ], ChartType::None));
        $dataset->addPreset(new Preset('reviews_by_status', $helper->__('Reviews by status'), $group, [
            'metrics' => ['reviews'],
            'dimensions' => ['status_id'],
            'range' => 'last_12_months',
        ], ChartType::Bar));

        return $dataset;
    }
}
