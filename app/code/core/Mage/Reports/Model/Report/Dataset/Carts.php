<?php

/**
 * The carts dataset of the report explorer: one row per cart with items, dated by its last activity.
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

class Mage_Reports_Model_Report_Dataset_Carts implements DatasetProvider
{
    public const string CODE = 'carts';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('reports');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Carts'), 'carts', 'report/shopcart', $resource->getTableName('sales/quote'), 'main', 'store_id', true);

        $dataset->setRecords(new Records(['updated_at', 'customer_email', 'cart_state', 'items_qty', 'cart_value', 'store_id'], 'cart_id', 'report/shopcart', 'carts'));
        $dataset->addCondition('main.items_count > 0');
        $dataset->addJoin(new Join(
            'billing',
            $resource->getTableName('sales/quote_address'),
            "billing.quote_id = main.entity_id AND billing.address_type = 'billing'",
        ));

        foreach (self::stateFields($adapter, 'main', ['billing']) as $field) {
            $dataset->addField($field);
        }
        $rate = $adapter->getIfNullSql('main.base_to_global_rate', 0);
        foreach ([
            new Field('cart_id', $helper->__('Cart'), FieldType::Id, 'main.entity_id'),
            new Field('updated_at', $helper->__('Last activity'), FieldType::Date, 'main.updated_at'),
            new Field('created_at', $helper->__('Created'), FieldType::Date, 'main.created_at'),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('customer_group_id', $helper->__('Customer group'), FieldType::Id, 'main.customer_group_id', labels: 'customer_group'),
            new Field(
                'customer_type',
                $helper->__('Customer type'),
                FieldType::String,
                "CASE WHEN main.customer_id IS NULL THEN 'guest' ELSE 'registered' END",
                options: ['guest' => $helper->__('Guest'), 'registered' => $helper->__('Registered')],
            ),
            new Field('coupon_code', $helper->__('Coupon code'), FieldType::String, 'main.coupon_code'),
            new Field('items_qty', $helper->__('Items'), FieldType::Decimal, (string) $adapter->getIfNullSql('main.items_qty', 0)),
            new Field('items_count', $helper->__('Products'), FieldType::Integer, (string) $adapter->getIfNullSql('main.items_count', 0)),
            new Field('cart_value', $helper->__('Cart value'), FieldType::Money, $adapter->getIfNullSql('main.base_grand_total', 0) . " * {$rate}"),
            new Field('cart_subtotal', $helper->__('Subtotal after discount'), FieldType::Money, $adapter->getIfNullSql('main.base_subtotal_with_discount', 0) . " * {$rate}"),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('updated_at', $helper->__('Last activity'))->addDateField('created_at');

        foreach ([
            Metric::aggregate('carts', $helper->__('Carts'), Aggregate::Count, null),
            Metric::aggregate('cart_value', $helper->__('Cart value'), Aggregate::Sum, 'cart_value', MetricFormat::Money),
            Metric::aggregate('cart_subtotal', $helper->__('Subtotal after discount'), Aggregate::Sum, 'cart_subtotal', MetricFormat::Money),
            Metric::formula('avg_cart_value', $helper->__('Average cart value'), 'cart_value / carts', MetricFormat::Money),
            Metric::aggregate('items_in_carts', $helper->__('Items'), Aggregate::Sum, 'items_qty'),
            Metric::aggregate('products_in_carts', $helper->__('Products'), Aggregate::Sum, 'items_count'),
            Metric::aggregate('abandoned_carts', $helper->__('Abandoned carts'), Aggregate::Count, null, condition: 'is_abandoned', higherIsBetter: false),
            Metric::aggregate('abandoned_value', $helper->__('Abandoned value'), Aggregate::Sum, 'cart_value', MetricFormat::Money, 'is_abandoned', higherIsBetter: false),
            Metric::aggregate('recent_carts', $helper->__('Recent carts'), Aggregate::Count, null, condition: 'is_recent'),
            Metric::aggregate('converted_carts', $helper->__('Converted carts'), Aggregate::Count, null, condition: 'is_converted'),
            Metric::aggregate('converted_value', $helper->__('Converted value'), Aggregate::Sum, 'cart_value', MetricFormat::Money, 'is_converted'),
            Metric::formula('abandonment_rate', $helper->__('Abandonment %'), 'abandoned_carts / (abandoned_carts + converted_carts) * 100', MetricFormat::Percent, higherIsBetter: false),
            Metric::formula('conversion_rate', $helper->__('Conversion %'), 'converted_carts / carts * 100', MetricFormat::Percent),
            Metric::aggregate('last_activity', $helper->__('Last activity'), Aggregate::Max, 'updated_at', MetricFormat::Date),
        ] as $metric) {
            $dataset->addMetric($metric);
        }
        foreach ([
            'cart_id' => $helper->__('Cart'),
            'cart_state' => $helper->__('Cart state'),
            'store_id' => $helper->__('Store view'),
            'customer_group_id' => $helper->__('Customer group'),
            'customer_type' => $helper->__('Customer type'),
            'customer_email' => $helper->__('Customer email'),
            'coupon_code' => $helper->__('Coupon code'),
        ] as $code => $label) {
            $dataset->addDimension(new Dimension($code, $label, $code));
        }

        $group = $helper->__('Shopping cart');
        foreach ([
            new Preset('active_carts', $helper->__('Active carts'), $group, [
                'metrics' => ['cart_subtotal', 'items_in_carts', 'products_in_carts', 'last_activity'],
                'dimensions' => ['cart_id', 'customer_email'],
                'filters' => [['field' => 'is_active', 'operator' => 'eq', 'value' => 1]],
                'sort' => '-last_activity',
                'range' => 'last_90_days',
            ], ChartType::None, $helper->__('Every active cart with items, as in the classic Abandoned carts report.')),
            new Preset('abandoned_carts_trend', $helper->__('Abandoned carts'), $group, [
                'metrics' => ['abandoned_carts', 'abandoned_value', 'abandonment_rate'],
                'dimensions' => ['period:day'],
                'range' => 'last_30_days',
                'compare' => 'previous_period',
            ], ChartType::Line, $helper->__('A cart is abandoned when it is active, has an email, has no order and was idle for the hours of the setting "Abandoned after".')),
            new Preset('abandoned_carts_list', $helper->__('Abandoned carts to follow up'), $group, [
                'metrics' => ['cart_value', 'items_in_carts', 'last_activity'],
                'dimensions' => ['cart_id', 'customer_email'],
                'filters' => [['field' => 'is_abandoned', 'operator' => 'eq', 'value' => 1]],
                'sort' => '-cart_value',
                'range' => 'last_30_days',
            ], ChartType::None),
            new Preset('recent_carts', $helper->__('Recent carts'), $group, [
                'metrics' => ['cart_value', 'items_in_carts', 'last_activity'],
                'dimensions' => ['cart_id', 'customer_type'],
                'filters' => [['field' => 'is_recent', 'operator' => 'eq', 'value' => 1]],
                'sort' => '-last_activity',
                'range' => 'last_7_days',
            ], ChartType::None, $helper->__('Active carts with no order and activity in the hours of the setting "Recent carts".')),
            new Preset('cart_conversion', $helper->__('Cart conversion'), $group, [
                'metrics' => ['carts', 'converted_carts', 'conversion_rate', 'abandonment_rate'],
                'dimensions' => ['period:day'],
                'range' => 'last_30_days',
            ], ChartType::Line),
        ] as $preset) {
            $dataset->addPreset($preset);
        }

        return $dataset;
    }

    /**
     * The state fields of a cart, shared by the carts and the cart items datasets. A cart is converted when an order
     * names it, abandoned when it is active, has an email, has no order and was idle longer than the setting, and
     * recent when it is active, has no order and had activity within the setting.
     *
     * @param list<string> $joins the joins that $alias and the billing address need
     * @return list<Field>
     */
    public static function stateFields(AdapterInterface $adapter, string $alias, array $joins): array
    {
        $helper = Mage::helper('reports');
        $orderTable = Mage::getSingleton('core/resource')->getTableName('sales/order');
        $email = "LOWER(TRIM(COALESCE(NULLIF({$alias}.customer_email, ''), billing.email)))";
        $hasEmail = "{$email} IS NOT NULL AND {$email} <> ''";
        $converted = 'EXISTS (SELECT 1 FROM ' . $adapter->quoteIdentifier($orderTable) . " cart_order WHERE cart_order.quote_id = {$alias}.entity_id)";
        $idleSince = static fn(Context $c): string => $c->quote($c->utcAgo((int) $c->setting('abandoned_after_hours', 24)));
        $recentSince = static fn(Context $c): string => $c->quote($c->utcAgo((int) $c->setting('recent_cart_hours', 24)));

        $abandoned = static fn(Context $c): string => "{$alias}.is_active = 1 AND {$hasEmail} AND {$alias}.updated_at < {$idleSince($c)} AND NOT {$converted}";
        $recent = static fn(Context $c): string => "{$alias}.is_active = 1 AND {$alias}.updated_at >= {$recentSince($c)} AND NOT {$converted}";

        return [
            new Field('customer_email', $helper->__('Customer email'), FieldType::String, $email, $joins, personal: true),
            new Field('has_email', $helper->__('With an email'), FieldType::Boolean, "CASE WHEN {$hasEmail} THEN 1 ELSE 0 END", $joins),
            new Field('is_active', $helper->__('Active'), FieldType::Boolean, (string) $adapter->getIfNullSql("{$alias}.is_active", 0), array_diff($joins, ['billing'])),
            new Field('is_converted', $helper->__('Converted'), FieldType::Boolean, "CASE WHEN {$converted} THEN 1 ELSE 0 END", array_diff($joins, ['billing'])),
            new Field('is_abandoned', $helper->__('Abandoned'), FieldType::Boolean, static fn(Context $c): string => "CASE WHEN {$abandoned($c)} THEN 1 ELSE 0 END", $joins),
            new Field('is_recent', $helper->__('Recent'), FieldType::Boolean, static fn(Context $c): string => "CASE WHEN {$recent($c)} THEN 1 ELSE 0 END", array_diff($joins, ['billing'])),
            new Field(
                'cart_state',
                $helper->__('Cart state'),
                FieldType::String,
                static fn(Context $c): string => "CASE WHEN {$converted} THEN 'converted'"
                    . " WHEN {$alias}.is_active = 1 AND {$alias}.updated_at >= {$idleSince($c)} THEN 'active'"
                    . " WHEN {$alias}.is_active = 1 AND {$hasEmail} THEN 'abandoned'"
                    . " WHEN {$alias}.is_active = 1 THEN 'abandoned_no_email'"
                    . " ELSE 'inactive' END",
                $joins,
                options: [
                    'converted' => $helper->__('Converted'),
                    'active' => $helper->__('Active'),
                    'abandoned' => $helper->__('Abandoned'),
                    'abandoned_no_email' => $helper->__('Abandoned without email'),
                    'inactive' => $helper->__('Inactive'),
                ],
            ),
        ];
    }
}
