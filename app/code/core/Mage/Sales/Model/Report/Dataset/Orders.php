<?php

/**
 * The orders dataset of the report explorer: one row per order, with the amounts of the classic Orders report.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
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
use Maho\Report\Definition\Metric;
use Maho\Report\Definition\MetricFormat;
use Maho\Report\Definition\Preset;
use Maho\Report\Definition\Records;
use Maho\Report\Definition\Scope;

class Mage_Sales_Model_Report_Dataset_Orders implements DatasetProvider
{
    public const string CODE = 'sales_orders';

    /** The order states that the classic Orders, Tax and Shipping reports leave out. */
    public const array NOT_PLACED_STATES = [Mage_Sales_Model_Order::STATE_NEW, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT];

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('sales');
        $resource = Mage::getSingleton('core/resource');
        $orderTable = $resource->getTableName('sales/order');
        $itemTable = $resource->getTableName('sales/order_item');

        $dataset = new Dataset(
            self::CODE,
            $helper->__('Orders'),
            'sales',
            'report/salesroot',
            $orderTable,
            'main',
            'store_id',
        );

        $dataset->setRecords(new Records(['increment_id', 'created_at', 'status', 'customer_email', 'payment_method', 'shipping_country', 'units', 'sales_total'], 'order_id', 'sales/order/actions/view', 'orders', 'adminhtml/sales_order/view', 'order_id'));

        self::addScopes($dataset, $adapter);

        $dataset->addJoin(new Join('payment', $resource->getTableName('sales/order_payment'), 'payment.parent_id = main.entity_id'));
        $dataset->addJoin(new Join(
            'billing',
            $resource->getTableName('sales/order_address'),
            "billing.parent_id = main.entity_id AND billing.address_type = 'billing'",
        ));
        $dataset->addJoin(new Join(
            'shipping',
            $resource->getTableName('sales/order_address'),
            "shipping.parent_id = main.entity_id AND shipping.address_type = 'shipping'",
        ));
        // A subquery for each order reads the items through their order_id index, which is faster than a join of all the items of the period
        $itemSum = static fn(string $quantity): string => (string) $adapter->getIfNullSql(
            '(SELECT SUM(' . $quantity . ') FROM ' . $adapter->quoteIdentifier($itemTable) . ' item'
            . ' WHERE item.order_id = main.entity_id AND item.parent_item_id IS NULL)',
            0,
        );
        $rate = $adapter->getIfNullSql('main.base_to_global_rate', 0);
        $amount = static fn(string $sql): string => "({$sql}) * {$rate}";
        $col = static fn(string $column): string => (string) $adapter->getIfNullSql("main.{$column}", 0);

        $customerTypes = ['guest' => $helper->__('Guest'), 'registered' => $helper->__('Registered')];
        $customerKinds = ['new' => $helper->__('New customer'), 'returning' => $helper->__('Returning customer')];
        $returning = 'EXISTS (SELECT 1 FROM ' . $adapter->quoteIdentifier($orderTable) . ' earlier'
            . ' WHERE earlier.customer_email = main.customer_email AND earlier.created_at < main.created_at'
            . ' AND earlier.state NOT IN (' . $adapter->quote(self::NOT_PLACED_STATES) . '))';

        // The first placed order of the customer email of the order, for the customer cohorts
        $firstOrderOf = static fn(string $date): string => '(SELECT MIN(' . $date . ') FROM ' . $adapter->quoteIdentifier($orderTable) . ' first_order'
            . ' WHERE first_order.customer_email = main.customer_email'
            . ' AND first_order.state NOT IN (' . $adapter->quote(self::NOT_PLACED_STATES) . '))';
        $firstOrder = $firstOrderOf('first_order.created_at');
        $monthNumber = static fn(string $date): string => '(' . $adapter->getDateExtractSql($date, AdapterInterface::INTERVAL_YEAR)
            . ' * 12 + ' . $adapter->getDateExtractSql($date, AdapterInterface::INTERVAL_MONTH) . ')';

        foreach ([
            new Field(
                'first_order_month',
                $helper->__('Month of the first order'),
                FieldType::String,
                static fn(Context $context): string => (string) $adapter->getDateFormatSql($firstOrderOf($context->localTime('first_order.created_at')), '%Y-%m'),
            ),
            new Field(
                'months_since_first_order',
                $helper->__('Months after the first order'),
                FieldType::Integer,
                static fn(Context $context): string => '(' . $monthNumber($context->localTime('main.created_at'))
                    . ' - ' . $monthNumber($firstOrderOf($context->localTime('first_order.created_at'))) . ')',
            ),
            new Field(
                'first_order_in_period',
                $helper->__('First order in the period'),
                FieldType::Boolean,
                static fn(Context $context): string => $context->utcFrom === null
                    ? '1'
                    : "CASE WHEN {$firstOrder} >= " . $context->quote($context->utcFrom) . ' THEN 1 ELSE 0 END',
            ),
            new Field('order_id', $helper->__('Order ID'), FieldType::Id, 'main.entity_id', filterable: false),
            new Field('increment_id', $helper->__('Order #'), FieldType::String, 'main.increment_id'),
            new Field('created_at', $helper->__('Order date'), FieldType::Date, 'main.created_at'),
            new Field('updated_at', $helper->__('Last update of the order'), FieldType::Date, 'main.updated_at'),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('status', $helper->__('Order status'), FieldType::String, 'main.status', labels: 'order_status'),
            new Field('state', $helper->__('Order state'), FieldType::String, 'main.state', labels: 'order_state'),
            new Field('customer_group_id', $helper->__('Customer group'), FieldType::Id, 'main.customer_group_id', labels: 'customer_group'),
            new Field(
                'customer_type',
                $helper->__('Customer type'),
                FieldType::String,
                "CASE WHEN main.customer_id IS NULL THEN 'guest' ELSE 'registered' END",
                options: $customerTypes,
            ),
            new Field(
                'customer_kind',
                $helper->__('New or returning'),
                FieldType::String,
                "CASE WHEN {$returning} THEN 'returning' ELSE 'new' END",
                options: $customerKinds,
            ),
            new Field('is_returning', $helper->__('Returning customer'), FieldType::Boolean, "CASE WHEN {$returning} THEN 1 ELSE 0 END"),
            new Field('is_new_customer', $helper->__('New customer'), FieldType::Boolean, "CASE WHEN {$returning} THEN 0 ELSE 1 END"),
            new Field('customer_id', $helper->__('Customer'), FieldType::Id, 'main.customer_id', personal: true, labels: 'customer'),
            new Field('customer_email', $helper->__('Customer email'), FieldType::String, 'LOWER(TRIM(main.customer_email))', personal: true),
            new Field('customer_key', $helper->__('Customer'), FieldType::String, 'LOWER(TRIM(main.customer_email))', filterable: false),
            new Field('coupon_code', $helper->__('Coupon code'), FieldType::String, 'main.coupon_code'),
            new Field(
                'has_coupon',
                $helper->__('With a coupon'),
                FieldType::Boolean,
                "CASE WHEN main.coupon_code IS NOT NULL AND main.coupon_code <> '' THEN 1 ELSE 0 END",
            ),
            new Field('shipping_method', $helper->__('Shipping carrier'), FieldType::String, 'main.shipping_method', labels: 'shipping_carrier'),
            new Field('shipping_description', $helper->__('Shipping method'), FieldType::String, 'main.shipping_description'),
            new Field('is_virtual', $helper->__('Virtual order'), FieldType::Boolean, $col('is_virtual')),
            new Field('payment_method', $helper->__('Payment method'), FieldType::String, 'payment.method', ['payment'], labels: 'payment_method'),
            new Field('billing_country', $helper->__('Billing country'), FieldType::String, 'billing.country_id', ['billing'], labels: 'country'),
            new Field('shipping_country', $helper->__('Shipping country'), FieldType::String, 'shipping.country_id', ['shipping'], labels: 'country'),
            new Field(
                'tax_country',
                $helper->__('Tax country'),
                FieldType::String,
                'COALESCE(shipping.country_id, billing.country_id)',
                ['shipping', 'billing'],
                labels: 'country',
            ),
            new Field('billing_postcode', $helper->__('Billing postcode'), FieldType::String, 'UPPER(TRIM(billing.postcode))', ['billing'], personal: true),
            new Field('units', $helper->__('Units'), FieldType::Decimal, $itemSum('item.qty_ordered - ' . $adapter->getIfNullSql('item.qty_canceled', 0))),
            new Field('units_invoiced', $helper->__('Units invoiced'), FieldType::Decimal, $itemSum('item.qty_invoiced')),
            new Field('sales_total', $helper->__('Sales total'), FieldType::Money, $amount($col('base_grand_total') . ' - ' . $col('base_total_canceled'))),
            new Field('revenue', $helper->__('Revenue'), FieldType::Money, $amount(sprintf(
                '%s - %s - %s - (%s - %s - %s)',
                $col('base_total_invoiced'),
                $col('base_tax_invoiced'),
                $col('base_shipping_invoiced'),
                $col('base_total_refunded'),
                $col('base_tax_refunded'),
                $col('base_shipping_refunded'),
            ))),
            new Field('profit', $helper->__('Profit'), FieldType::Money, $amount(sprintf(
                '(%s - %s) - (%s - %s) - (%s - %s) - %s',
                $col('base_total_paid'),
                $col('base_total_refunded'),
                $col('base_tax_invoiced'),
                $col('base_tax_refunded'),
                $col('base_shipping_invoiced'),
                $col('base_shipping_refunded'),
                $col('base_total_invoiced_cost'),
            ))),
            new Field('invoiced', $helper->__('Invoiced'), FieldType::Money, $amount($col('base_total_invoiced'))),
            new Field('paid', $helper->__('Paid'), FieldType::Money, $amount($col('base_total_paid'))),
            new Field('refunded', $helper->__('Refunded'), FieldType::Money, $amount($col('base_total_refunded'))),
            new Field('canceled', $helper->__('Canceled'), FieldType::Money, $amount($col('base_total_canceled'))),
            new Field('tax', $helper->__('Tax'), FieldType::Money, $amount($col('base_tax_amount') . ' - ' . $col('base_tax_canceled'))),
            new Field('tax_actual', $helper->__('Tax invoiced'), FieldType::Money, $amount($col('base_tax_invoiced') . ' - ' . $col('base_tax_refunded'))),
            new Field('shipping', $helper->__('Shipping'), FieldType::Money, $amount($col('base_shipping_amount') . ' - ' . $col('base_shipping_canceled'))),
            new Field(
                'shipping_actual',
                $helper->__('Shipping invoiced'),
                FieldType::Money,
                $amount($col('base_shipping_invoiced') . ' - ' . $col('base_shipping_refunded')),
            ),
            new Field('discount', $helper->__('Discounts'), FieldType::Money, $amount('ABS(' . $col('base_discount_amount') . ') - ' . $col('base_discount_canceled'))),
            new Field(
                'discount_actual',
                $helper->__('Discounts invoiced'),
                FieldType::Money,
                $amount($col('base_discount_invoiced') . ' - ' . $col('base_discount_refunded')),
            ),
            new Field('gross_sales', $helper->__('Gross sales'), FieldType::Money, $amount($col('base_subtotal') . ' - ' . $col('base_subtotal_canceled'))),
            new Field('returns', $helper->__('Returns'), FieldType::Money, $amount($col('base_subtotal_refunded') . ' - ABS(' . $col('base_discount_refunded') . ')')),
            new Field('cost', $helper->__('Cost'), FieldType::Money, $amount($col('base_total_invoiced_cost'))),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('created_at', $helper->__('Order date'))->addDateField('updated_at');

        $sum = static fn(string $code, string $label, MetricFormat $format = MetricFormat::Money, bool $higherIsBetter = true, ?string $description = null): Metric
            => Metric::aggregate($code, $label, Aggregate::Sum, $code, $format, higherIsBetter: $higherIsBetter, description: $description);
        foreach ([
            Metric::aggregate('orders', $helper->__('Orders'), Aggregate::Count, null),
            $sum('units', $helper->__('Units'), MetricFormat::Number, description: $helper->__('Units ordered, without the canceled units. A configurable or bundle product counts once.')),
            $sum('units_invoiced', $helper->__('Units invoiced'), MetricFormat::Number),
            $sum('sales_total', $helper->__('Sales total'), description: $helper->__('Grand total of the orders, without the canceled amount.')),
            $sum('revenue', $helper->__('Revenue'), description: $helper->__('Invoiced amount without tax and shipping, minus the refunded amount without tax and shipping.')),
            $sum('profit', $helper->__('Profit'), description: $helper->__('Paid minus refunded, without tax and shipping, minus the cost of the invoiced items.')),
            $sum('invoiced', $helper->__('Invoiced')),
            $sum('paid', $helper->__('Paid')),
            $sum('refunded', $helper->__('Refunded'), higherIsBetter: false),
            $sum('canceled', $helper->__('Canceled'), higherIsBetter: false),
            $sum('tax', $helper->__('Tax')),
            $sum('tax_actual', $helper->__('Tax invoiced')),
            $sum('shipping', $helper->__('Shipping')),
            $sum('shipping_actual', $helper->__('Shipping invoiced')),
            $sum('discount', $helper->__('Discounts'), higherIsBetter: false),
            $sum('discount_actual', $helper->__('Discounts invoiced'), higherIsBetter: false),
            $sum('gross_sales', $helper->__('Gross sales'), description: $helper->__('Subtotal without tax and shipping, without the canceled subtotal.')),
            $sum('returns', $helper->__('Returns'), higherIsBetter: false, description: $helper->__('Refunded subtotal, without the refunded discount.')),
            $sum('cost', $helper->__('Cost'), higherIsBetter: false),
            Metric::formula('net_sales', $helper->__('Net sales'), 'gross_sales - discount - returns', MetricFormat::Money, description: $helper->__('Gross sales minus discounts minus returns.')),
            Metric::formula('aov', $helper->__('Average order value'), 'sales_total / orders', MetricFormat::Money),
            Metric::aggregate('unique_customers', $helper->__('Customers'), Aggregate::CountDistinct, 'customer_key', description: $helper->__('Different customer emails.')),
            Metric::aggregate('new_customer_orders', $helper->__('Orders of new customers'), Aggregate::Count, null, condition: 'is_new_customer'),
            Metric::aggregate('returning_customer_orders', $helper->__('Orders of returning customers'), Aggregate::Count, null, condition: 'is_returning'),
            Metric::aggregate('first_order_at', $helper->__('First order'), Aggregate::Min, 'created_at', MetricFormat::Date),
            Metric::aggregate('last_order_at', $helper->__('Last order'), Aggregate::Max, 'created_at', MetricFormat::Date),
        ] as $metric) {
            $dataset->addMetric($metric);
        }

        foreach ([
            ['store_id', $helper->__('Store view')],
            ['status', $helper->__('Order status')],
            ['state', $helper->__('Order state')],
            ['customer_group_id', $helper->__('Customer group')],
            ['customer_type', $helper->__('Customer type')],
            ['customer_kind', $helper->__('New or returning')],
            ['first_order_month', $helper->__('Month of the first order')],
            ['months_since_first_order', $helper->__('Months after the first order')],
            ['payment_method', $helper->__('Payment method')],
            ['shipping_method', $helper->__('Shipping carrier')],
            ['shipping_description', $helper->__('Shipping method')],
            ['shipping_country', $helper->__('Shipping country')],
            ['billing_country', $helper->__('Billing country')],
            ['tax_country', $helper->__('Tax country')],
            ['coupon_code', $helper->__('Coupon code')],
            ['customer_id', $helper->__('Customer')],
            ['customer_email', $helper->__('Customer email')],
            ['billing_postcode', $helper->__('Billing postcode')],
        ] as [$code, $label]) {
            $dataset->addDimension(new Dimension($code, $label, $code));
        }

        $this->addPresets($dataset);
        return $dataset;
    }

    /** The order scopes, shared by every dataset that joins the order as "main" or as another alias. */
    public static function addScopes(Dataset $dataset, AdapterInterface $adapter, string $alias = 'main', array $joins = []): void
    {
        $helper = Mage::helper('sales');
        $dataset->addScope(new Scope(
            'placed',
            $helper->__('Placed orders (not new or pending payment)'),
            "{$alias}.state NOT IN (" . $adapter->quote(self::NOT_PLACED_STATES) . ')',
            $joins,
        ));
        $dataset->addScope(new Scope(
            'not_canceled',
            $helper->__('Orders that are not canceled'),
            "{$alias}.state <> " . $adapter->quote(Mage_Sales_Model_Order::STATE_CANCELED),
            $joins,
        ));
        $dataset->addScope(new Scope('all', $helper->__('All orders'), null));
    }

    private function addPresets(Dataset $dataset): void
    {
        $helper = Mage::helper('sales');
        $sales = $helper->__('Sales');
        $customers = $helper->__('Customers');
        $promotions = $helper->__('Promotions');
        $byDimension = static fn(string $dimension, array $metrics = ['orders', 'net_sales', 'aov']): array => [
            'metrics' => $metrics,
            'dimensions' => [$dimension],
            'sort' => '-' . $metrics[1],
            'range' => 'last_30_days',
            'compare' => 'previous_period',
        ];

        foreach ([
            new Preset('sales_overview', $helper->__('Sales overview'), $sales, [
                'metrics' => ['orders', 'net_sales', 'sales_total', 'aov', 'units', 'gross_sales', 'discount', 'returns', 'tax', 'shipping'],
                'dimensions' => ['period:day'],
                'range' => 'last_30_days',
                'compare' => 'previous_period',
            ], ChartType::Line, $helper->__('Orders and amounts for each day, compared with the period before.')),
            new Preset('orders_by_status', $helper->__('Orders by status'), $sales, ['scope' => 'all'] + $byDimension('status', ['orders', 'sales_total']), ChartType::Bar),
            new Preset('sales_by_store', $helper->__('Sales by store view'), $sales, $byDimension('store_id'), ChartType::Bar),
            new Preset('sales_by_payment_method', $helper->__('Sales by payment method'), $sales, $byDimension('payment_method'), ChartType::Bar),
            new Preset('sales_by_shipping_method', $helper->__('Shipping by method'), $sales, [
                'filters' => [['field' => 'is_virtual', 'operator' => 'eq', 'value' => 0]],
            ] + $byDimension('shipping_description', ['orders', 'shipping', 'shipping_actual']), ChartType::Bar),
            new Preset('sales_by_shipping_country', $helper->__('Sales by shipping country'), $sales, $byDimension('shipping_country'), ChartType::Bar),
            new Preset('taxes_by_country', $helper->__('Taxes by country'), $sales, $byDimension('tax_country', ['orders', 'tax', 'net_sales']), ChartType::Bar),
            new Preset('sales_by_customer_group', $helper->__('Sales by customer group'), $customers, $byDimension('customer_group_id'), ChartType::Bar),
            new Preset('sales_by_hour', $helper->__('Sales by hour of the day'), $sales, [
                'metrics' => ['orders', 'net_sales'],
                'dimensions' => ['period:hour_of_day'],
                'range' => 'last_90_days',
            ], ChartType::Bar),
            new Preset('sales_by_weekday', $helper->__('Sales by day of the week'), $sales, [
                'metrics' => ['orders', 'net_sales'],
                'dimensions' => ['period:day_of_week'],
                'range' => 'last_90_days',
            ], ChartType::Bar),
            new Preset('new_vs_returning', $helper->__('New and returning customers'), $customers, $byDimension('customer_kind'), ChartType::Bar),
            new Preset('coupon_performance', $helper->__('Coupon performance'), $promotions, [
                'scope' => 'all',
                'filters' => [['field' => 'has_coupon', 'operator' => 'eq', 'value' => 1]],
            ] + $byDimension('coupon_code', ['orders', 'gross_sales', 'discount', 'net_sales']), ChartType::Bar),
            new Preset('customer_value', $helper->__('Customer value'), $customers, [
                'scope' => 'not_canceled',
                'metrics' => ['orders', 'net_sales', 'aov', 'last_order_at'],
                'dimensions' => ['customer_id'],
                'filters' => [['field' => 'customer_type', 'operator' => 'eq', 'value' => 'registered']],
                'sort' => '-net_sales',
                'range' => 'last_12_months',
            ], ChartType::None, $helper->__('Registered customers with their orders and amounts.')),
            new Preset('customer_value_by_email', $helper->__('Customer value by email'), $customers, [
                'scope' => 'not_canceled',
                'metrics' => ['orders', 'net_sales', 'aov', 'last_order_at'],
                'dimensions' => ['customer_email'],
                'sort' => '-net_sales',
                'range' => 'last_12_months',
            ], ChartType::None, $helper->__('Every customer email, guests included.')),
            new Preset('customer_cohorts', $helper->__('Customer cohorts'), $customers, [
                'metrics' => ['unique_customers', 'orders', 'net_sales'],
                'dimensions' => ['first_order_month', 'months_since_first_order'],
                'filters' => [['field' => 'first_order_in_period', 'operator' => 'eq', 'value' => 1]],
                'sort' => 'first_order_month',
                'range' => 'last_12_months',
            ], ChartType::Matrix, $helper->__('The customers of each month of their first order, and how many of them order again in each month after it.')),
        ] as $preset) {
            $dataset->addPreset($preset);
        }
    }
}
