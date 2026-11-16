<?php

/**
 * The search terms dataset of the report explorer: one row per search term of a store view.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
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

class Mage_CatalogSearch_Model_Report_Dataset_SearchTerms implements DatasetProvider
{
    public const CODE = 'search_terms';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('catalogsearch');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Search terms'), 'search', 'report/search', $resource->getTableName('catalogsearch/search_query'), 'main', 'store_id', true);

        $dataset->addField(new Field('query_id', $helper->__('Search term'), FieldType::Id, 'main.query_id', filterable: false));

        $dataset->setRecords(new Records(['query_text', 'store_id', 'popularity', 'num_results'], 'query_id', 'catalog/search', 'reports', 'adminhtml/catalog_search/edit', 'id'));
        foreach ([
            new Field('query_text', $helper->__('Search term'), FieldType::String, 'main.query_text'),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('num_results', $helper->__('Results'), FieldType::Integer, (string) $adapter->getIfNullSql('main.num_results', 0)),
            new Field('popularity', $helper->__('Searches'), FieldType::Integer, (string) $adapter->getIfNullSql('main.popularity', 0)),
            new Field('has_results', $helper->__('With results'), FieldType::Boolean, 'CASE WHEN main.num_results > 0 THEN 1 ELSE 0 END'),
            new Field('no_results', $helper->__('Without results'), FieldType::Boolean, 'CASE WHEN main.num_results > 0 THEN 0 ELSE 1 END', filterable: false),
        ] as $field) {
            $dataset->addField($field);
        }

        foreach ([
            Metric::aggregate('searches', $helper->__('Searches'), Aggregate::Sum, 'popularity'),
            Metric::aggregate('terms', $helper->__('Search terms'), Aggregate::Count, null),
            Metric::aggregate('terms_no_results', $helper->__('Terms without results'), Aggregate::Count, null, condition: 'no_results', higherIsBetter: false),
            Metric::aggregate('results', $helper->__('Results'), Aggregate::Max, 'num_results'),
            Metric::aggregate('avg_results', $helper->__('Average results'), Aggregate::Avg, 'num_results', MetricFormat::Decimal),
            Metric::formula('no_results_rate', $helper->__('Without results %'), 'terms_no_results / terms * 100', MetricFormat::Percent, higherIsBetter: false),
        ] as $metric) {
            $dataset->addMetric($metric);
        }
        $dataset->addDimension(new Dimension('query_text', $helper->__('Search term'), 'query_text'));
        $dataset->addDimension(new Dimension('store_id', $helper->__('Store view'), 'store_id'));

        $group = Mage::helper('reports')->__('Search');
        $dataset->addPreset(new Preset('top_search_terms', $helper->__('Search terms'), $group, [
            'metrics' => ['searches', 'results'],
            'dimensions' => ['query_text', 'store_id'],
            'sort' => '-searches',
        ], ChartType::Bar));
        $dataset->addPreset(new Preset('search_terms_no_results', $helper->__('Search terms without results'), $group, [
            'metrics' => ['searches'],
            'dimensions' => ['query_text'],
            'filters' => [['field' => 'has_results', 'operator' => 'eq', 'value' => 0]],
            'sort' => '-searches',
        ], ChartType::Bar));

        return $dataset;
    }
}
