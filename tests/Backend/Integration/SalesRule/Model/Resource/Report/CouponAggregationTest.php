<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function couponAggregationAdapter(): \Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function couponAggregationCleanUp(): void
{
    $resource = Mage::getSingleton('core/resource');
    couponAggregationAdapter()->delete($resource->getTableName('sales/order'), ["created_at BETWEEN '2001-03-01' AND '2001-03-31'"]);
    couponAggregationAdapter()->delete($resource->getTableName('salesrule/coupon_aggregated'), ["period BETWEEN '2001-03-01' AND '2001-03-31'"]);
}

beforeEach(fn() => couponAggregationCleanUp());
afterEach(fn() => couponAggregationCleanUp());

it('counts only the orders that carry a coupon code', function () {
    $resource = Mage::getSingleton('core/resource');

    // An order without a coupon holds '' or NULL: the quote uses both for "no coupon".
    foreach (['SUMMER', '', null] as $couponCode) {
        couponAggregationAdapter()->insert($resource->getTableName('sales/order'), [
            'store_id' => 1,
            'status' => 'complete',
            'coupon_code' => $couponCode,
            'base_subtotal' => 10,
            'base_to_global_rate' => 1,
            'created_at' => '2001-03-14 12:00:00',
            'updated_at' => '2001-03-14 12:00:00',
        ]);
    }

    /** @var Mage_SalesRule_Model_Resource_Report_Rule_Createdat $report */
    $report = Mage::getResourceModel('salesrule/report_rule_createdat');
    $report->aggregate('2001-03-01', '2001-03-31');

    $rows = couponAggregationAdapter()->fetchAll(
        couponAggregationAdapter()->select()
            ->from($resource->getTableName('salesrule/coupon_aggregated'), ['coupon_code', 'coupon_uses'])
            ->where("period BETWEEN '2001-03-01' AND '2001-03-31'")
            ->where('store_id = ?', 1),
    );

    expect($rows)->toHaveCount(1);
    expect($rows[0]['coupon_code'])->toBe('SUMMER');
    expect((int) $rows[0]['coupon_uses'])->toBe(1);
});
