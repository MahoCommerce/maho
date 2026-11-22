<?php

/**
 * The 404 errors dataset of the report explorer: one row for each storefront path and store view that answered 404.
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
use Maho\Report\Definition\Metric;
use Maho\Report\Definition\Preset;
use Maho\Report\Definition\Records;

class Mage_Reports_Model_Report_Dataset_NotFound implements DatasetProvider
{
    public const string CODE = 'not_found';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('reports');
        $resource = Mage::getSingleton('core/resource');
        $rewriteTable = $resource->getTableName('core/url_rewrite');

        $dataset = new Dataset(self::CODE, $helper->__('404 errors'), 'storefront', 'report/notfound', $resource->getTableName('reports/not_found'), 'main', 'store_id', true);

        // A rewrite of the store view wins over a rewrite of store 0, as in the URL rewrite lookup
        $dataset->addJoin(new Join('rewrite', $rewriteTable, 'rewrite.request_path = main.request_path AND rewrite.store_id = main.store_id'));
        $dataset->addJoin(new Join('rewrite_default', $rewriteTable, 'rewrite_default.request_path = main.request_path AND rewrite_default.store_id = ' . Mage_Core_Model_App::ADMIN_STORE_ID));
        $target = self::redirectTargetSql();
        $joins = ['rewrite', 'rewrite_default'];

        foreach ([
            new Field('not_found_id', $helper->__('Address'), FieldType::Id, 'main.not_found_id', filterable: false),
            new Field('request_path', $helper->__('Address'), FieldType::String, 'main.request_path'),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('hits', $helper->__('Hits'), FieldType::Integer, 'main.hits'),
            new Field('referer', $helper->__('Last referer'), FieldType::String, 'main.referer'),
            new Field('first_seen_at', $helper->__('First seen'), FieldType::Date, 'main.first_seen_at'),
            new Field('last_seen_at', $helper->__('Last seen'), FieldType::Date, 'main.last_seen_at'),
            new Field('redirect_target', $helper->__('Redirects to'), FieldType::String, $target, $joins),
            new Field('has_redirect', $helper->__('Redirected'), FieldType::Boolean, "CASE WHEN ({$target}) IS NULL THEN 0 ELSE 1 END", $joins),
            new Field('no_redirect', $helper->__('Without a redirect'), FieldType::Boolean, "CASE WHEN ({$target}) IS NULL THEN 1 ELSE 0 END", $joins, filterable: false),
            // A store view cannot have two rewrites with one request path, so only a path without one gets a new redirect
            new Field('new_redirect_path', $helper->__('Address to redirect'), FieldType::String, "CASE WHEN rewrite.url_rewrite_id IS NULL AND ({$target}) IS NULL THEN main.request_path END", $joins, filterable: false),
        ] as $field) {
            $dataset->addField($field);
        }

        $dataset->setRecords(new Records(
            ['request_path', 'store_id', 'hits', 'last_seen_at', 'first_seen_at', 'referer', 'redirect_target'],
            'not_found_id',
            'report/notfound',
            'reports',
            'adminhtml/urlrewrite/edit',
            routeQuery: ['request_path' => 'new_redirect_path', 'store_id' => 'store_id'],
            routeAclResource: 'catalog/urlrewrite',
            routeTitle: $helper->__('Redirect'),
        ));

        foreach ([
            Metric::aggregate('hits', $helper->__('Hits'), Aggregate::Sum, 'hits', higherIsBetter: false),
            Metric::aggregate('addresses', $helper->__('Addresses'), Aggregate::Count, null, higherIsBetter: false),
            Metric::aggregate('hits_without_redirect', $helper->__('Hits without a redirect'), Aggregate::Sum, 'hits', condition: 'no_redirect', higherIsBetter: false),
            Metric::aggregate('addresses_without_redirect', $helper->__('Addresses without a redirect'), Aggregate::Count, null, condition: 'no_redirect', higherIsBetter: false),
        ] as $metric) {
            $dataset->addMetric($metric);
        }

        $dataset->addDimension(new Dimension('request_path', $helper->__('Address'), 'request_path'));
        $dataset->addDimension(new Dimension('store_id', $helper->__('Store view'), 'store_id'));
        $dataset->addDimension(new Dimension('has_redirect', $helper->__('Redirected'), 'has_redirect'));
        $dataset->addDimension(new Dimension('referer', $helper->__('Last referer'), 'referer'));

        $group = $helper->__('404 errors');
        $withoutRedirect = [['field' => 'has_redirect', 'operator' => 'eq', 'value' => 0]];
        $dataset->addPreset(new Preset('missing_pages', $helper->__('Missing pages'), $group, [
            'metrics' => ['hits'],
            'dimensions' => ['request_path'],
            'filters' => $withoutRedirect,
            'sort' => '-hits',
        ], ChartType::Bar, $helper->__('The storefront addresses that answer 404 and have no redirect, with the most hits first.')));
        $dataset->addPreset(new Preset('missing_page_referers', $helper->__('Pages that link to missing pages'), $group, [
            'metrics' => ['hits', 'addresses'],
            'dimensions' => ['referer'],
            'filters' => [...$withoutRedirect, ['field' => 'referer', 'operator' => 'not_empty', 'value' => null]],
            'sort' => '-hits',
        ], ChartType::Bar));
        $dataset->addPreset(new Preset('not_found_by_store', $helper->__('404 errors by store view'), $group, [
            'metrics' => ['hits', 'addresses', 'addresses_without_redirect'],
            'dimensions' => ['store_id'],
            'sort' => '-hits',
        ], ChartType::Bar));

        return $dataset;
    }

    /**
     * The target path of the URL rewrite that redirects the path. It is null when the rewrite that the lookup picks
     * does not redirect, or when no rewrite exists.
     */
    private static function redirectTargetSql(): string
    {
        return 'CASE WHEN ' . self::redirectsSql('rewrite') . ' THEN rewrite.target_path'
            . ' WHEN rewrite.url_rewrite_id IS NULL AND ' . self::redirectsSql('rewrite_default')
            . ' THEN rewrite_default.target_path END';
    }

    /**
     * The storefront redirects a rewrite with the option R or RP, and a rewrite with a target that starts with
     * http:/ or https:, as Mage_Core_Model_Controller_Front_Observer::processRedirectOptions() does.
     */
    private static function redirectsSql(string $alias): string
    {
        return "({$alias}.options IN ('R', 'RP') OR {$alias}.target_path LIKE 'http:/%' OR {$alias}.target_path LIKE 'https:%')";
    }
}
