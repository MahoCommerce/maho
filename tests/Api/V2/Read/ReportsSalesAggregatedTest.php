<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 tax, invoiced, shipping, refunded and coupons reports on a day far in the past.
 *
 * @group read
 */

const REPORT_SALES_DAY = '2003-04-08';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $rule = ReportFixture::createCouponRule('REPORTFIXTURE' . strtoupper(substr(uniqid(), -6)));
    // Taxable goods, shipped to California: the sample tax rule US-CA applies when the store has it
    $product = ReportFixture::createProduct('report-sales', 10.0, 2);

    $order = ReportFixture::placeOrder($product, 2, couponCode: (string) $rule->getCouponCode());
    $order = ReportFixture::shipOrder($order);
    $order = ReportFixture::refundOrder($order);
    ReportFixture::moveOrderDates((int) $order->getId(), REPORT_SALES_DAY . ' 09:00:00');
    ReportFixture::aggregate(['tax', 'invoiced', 'shipping', 'refunded', 'coupons'], REPORT_SALES_DAY, REPORT_SALES_DAY);

    $GLOBALS['reportSales'] = ['order' => Mage::getModel('sales/order')->load($order->getId()), 'rule' => $rule];
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

function reportSalesGet(string $report, string $query = ''): array
{
    $response = apiGet('/api/rest/v2/reports/' . $report . '?from=' . REPORT_SALES_DAY . '&to=' . REPORT_SALES_DAY . $query, adminToken());
    expect($response['status'])->toBe(200);
    expect(array_column($response['json']['periods'], 'period'))->toBe([REPORT_SALES_DAY]);
    return $response['json'];
}

describe('Aggregated sales reports', function (): void {

    it('reports the tax of each rate', function (): void {
        /** @var Mage_Sales_Model_Order $order */
        $order = $GLOBALS['reportSales']['order'];
        if ((float) $order->getBaseTaxAmount() <= 0) {
            $this->markTestSkipped('No tax rule applies to the fixture order in this store');
        }
        $json = reportSalesGet('sales/tax');
        expect($json['report'])->toBe('sales-tax')
            ->and($json['statistics']['code'])->toBe('tax');
        $rows = $json['periods'][0]['rows'];
        expect($rows)->not->toBe([]);
        expect(array_keys($rows[0]))->toBe(['code', 'percent', 'ordersCount', 'taxAmount'])
            ->and($rows[0]['ordersCount'])->toBe(1)
            ->and((float) array_sum(array_column($rows, 'taxAmount')))
            ->toEqualWithDelta((float) $order->getBaseTaxAmount() * (float) $order->getBaseToGlobalRate(), 0.01)
            ->and((float) $json['totals']['taxAmount'])->toEqualWithDelta((float) array_sum(array_column($rows, 'taxAmount')), 0.0001);
    });

    it('reports the invoiced amounts by order date and by invoice date', function (): void {
        $order = $GLOBALS['reportSales']['order'];
        $rate = (float) $order->getBaseToGlobalRate();
        foreach (['', '&dateBasis=invoice'] as $basis) {
            $json = reportSalesGet('sales/invoiced', $basis);
            $values = $json['periods'][0]['values'];
            expect($values['ordersCount'])->toBe(1)
                ->and($values['ordersInvoiced'])->toBe(1)
                ->and((float) $values['invoiced'])->toEqualWithDelta((float) $order->getBaseTotalInvoiced() * $rate, 0.0001)
                ->and($json['totals'])->toBe($values);
        }
        expect(reportSalesGet('sales/invoiced', '&dateBasis=invoice')['dateBasis'])->toBe('invoice');
    });

    it('reports the shipping of each method', function (): void {
        $order = $GLOBALS['reportSales']['order'];
        foreach (['', '&dateBasis=shipment'] as $basis) {
            $rows = reportSalesGet('sales/shipping', $basis)['periods'][0]['rows'];
            expect($rows)->toHaveCount(1)
                ->and($rows[0]['shippingDescription'])->toBe((string) $order->getShippingDescription())
                ->and($rows[0]['ordersCount'])->toBe(1)
                ->and((float) $rows[0]['totalShipping'])->toEqualWithDelta((float) $order->getBaseShippingAmount() * (float) $order->getBaseToGlobalRate(), 0.0001);
        }
    });

    it('reports the refunded amounts by order date and by refund date', function (): void {
        $order = $GLOBALS['reportSales']['order'];
        foreach (['', '&dateBasis=refund'] as $basis) {
            $values = reportSalesGet('sales/refunded', $basis)['periods'][0]['values'];
            expect($values['ordersCount'])->toBe(1)
                ->and((float) $values['refunded'])->toEqualWithDelta((float) $order->getBaseTotalRefunded() * (float) $order->getBaseToGlobalRate(), 0.0001)
                ->and((float) $values['refunded'])->toBeGreaterThan(0.0)
                ->and((float) $values['offlineRefunded'])->toEqual((float) $values['refunded'])
                ->and((float) $values['onlineRefunded'])->toEqual(0.0);
        }
    });

    it('reports the use of each coupon and filters by rule', function (): void {
        $order = $GLOBALS['reportSales']['order'];
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = $GLOBALS['reportSales']['rule'];

        $json = reportSalesGet('sales/coupons', '&ruleIds=' . $rule->getId());
        expect($json['ruleIds'])->toBe([(int) $rule->getId()]);
        $rows = $json['periods'][0]['rows'];
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['couponCode'])->toBe($rule->getCouponCode())
            ->and($rows[0]['ruleName'])->toBe($rule->getName())
            ->and($rows[0]['uses'])->toBe(1)
            ->and((float) $rows[0]['discount'])->toEqualWithDelta(abs((float) $order->getBaseDiscountAmount()) * (float) $order->getBaseToGlobalRate(), 0.0001)
            ->and((float) $rows[0]['discount'])->toBeGreaterThan(0.0)
            ->and($json['totals']['uses'])->toBe(1);

        expect(reportSalesGet('sales/coupons', '&ruleIds=999999999')['periods'][0]['rows'])->toBe([])
            ->and(apiGet('/api/rest/v2/reports/sales/coupons?from=2003-04-08&to=2003-04-08&ruleIds=abc', adminToken())['status'])->toBe(400);
    });

    it('filters the tax, shipping and coupon reports by order status', function (): void {
        $status = (string) $GLOBALS['reportSales']['order']->getStatus();
        $other = $status === 'complete' ? 'processing' : 'complete';
        expect(reportSalesGet('sales/shipping', '&orderStatuses=' . $status)['periods'][0]['rows'])->toHaveCount(1)
            ->and(reportSalesGet('sales/shipping', '&orderStatuses=' . $other)['periods'][0]['rows'])->toBe([])
            ->and(reportSalesGet('sales/coupons', '&orderStatuses=' . $other)['periods'][0]['rows'])->toBe([]);
    });
});
