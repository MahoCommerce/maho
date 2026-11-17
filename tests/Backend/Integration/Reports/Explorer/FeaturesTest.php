<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Report\Request;
use Tests\Helpers\AdminRoleFixture;
use Tests\Helpers\ReportFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The records behind a report, the explanation of a change, the dashboard pins, the notes and the targets.
 *
 * Day one has an invoiced order of 2 units and a pending order of 3 units. Day two has one invoiced order of 1 unit.
 */

const EXPLORER_FEATURES_DAY_ONE = '2004-05-10';
const EXPLORER_FEATURES_DAY_TWO = '2004-05-11';

/** @return array{one: list<Mage_Sales_Model_Order>, two: list<Mage_Sales_Model_Order>} */
function explorerFeatureOrders(): array
{
    static $orders = null;
    if ($orders !== null) {
        return $orders;
    }
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer_features', 10.0);
    $pending = ReportFixture::placeOrder($product, 3, EXPLORER_FEATURES_DAY_ONE . ' 13:00:00', invoice: false);
    // The helper leaves an order without invoice in processing; a new order of the storefront waits in pending
    ReportFixture::adapter()->update(ReportFixture::table('sales/order'), ['state' => 'new', 'status' => 'pending'], ['entity_id = ?' => (int) $pending->getId()]);
    return $orders = [
        'one' => [ReportFixture::placeOrder($product, 2, EXPLORER_FEATURES_DAY_ONE . ' 12:00:00'), $pending],
        'two' => [ReportFixture::placeOrder($product, 1, EXPLORER_FEATURES_DAY_TWO . ' 12:00:00')],
    ];
}

/** @param array<string, mixed> $data */
function explorerFeatureRequest(string $day, array $data = []): Request
{
    return Request::fromArray($data + ['dataset' => 'sales_orders', 'metrics' => ['units'], 'scope' => 'all', 'from' => $day, 'to' => $day, 'storeIds' => [1]]);
}

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
    explorerFeatureOrders();
});

afterAll(function () {
    // tearDown() resets Mage after each test, so the cleanup starts it again and leaves it reset
    Mage::app();
    Mage::register('isSecureArea', true, true);
    try {
        ReportFixture::restore();
        ReportFixture::deleteFixtures();
    } finally {
        Mage::reset();
        restore_error_handler();
        restore_exception_handler();
    }
});

describe('the records behind a report', function () {
    it('lists the orders of the report, newest first, with their IDs', function () {
        $orders = explorerFeatureOrders()['one'];
        $result = Mage::getService('reports/explorer')->records(explorerFeatureRequest(EXPLORER_FEATURES_DAY_ONE), Mage_Reports_Model_Explorer_Access::full());

        expect($result['totalRows'])->toBe(2)
            ->and(array_column($result['rows'], 'id'))->toBe([(int) $orders[1]->getId(), (int) $orders[0]->getId()])
            ->and(array_column(array_column($result['rows'], 'values'), 'increment_id'))->toBe([$orders[1]->getIncrementId(), $orders[0]->getIncrementId()])
            ->and($result['route'])->toBe('adminhtml/sales_order/view');
    });

    it('keeps the filters of the report and hides the personal fields without the permission', function () {
        $request = explorerFeatureRequest(EXPLORER_FEATURES_DAY_ONE, ['filters' => [['field' => 'status', 'operator' => 'eq', 'value' => 'processing']]]);
        $access = new Mage_Reports_Model_Explorer_Access(null, false, null, null);
        $result = Mage::getService('reports/explorer')->records($request, $access);

        expect($result['totalRows'])->toBe(1)
            ->and(array_column($result['columns'], 'key'))->not->toContain('customer_email');
    });

    it('refuses the records to a caller without their permission', function () {
        $access = new Mage_Reports_Model_Explorer_Access(null, true, null, ['customers']);

        expect(fn() => Mage::getService('reports/explorer')->records(explorerFeatureRequest(EXPLORER_FEATURES_DAY_ONE), $access))
            ->toThrow(Mage_Core_Exception_Input::class);
    });
});

describe('the totals', function () {
    it('adds up the rows for the totals, and queries the totals when a metric does not add up or the limit cuts the rows', function () {
        $runner = Mage::getSingleton('reports/explorer_runner');
        $access = Mage_Reports_Model_Explorer_Access::full();
        $request = explorerFeatureRequest(EXPLORER_FEATURES_DAY_ONE, ['metrics' => ['units', 'orders', 'sales_total', 'aov'], 'dimensions' => ['status']]);
        $statements = static fn(Request $request): array => array_column($runner->explain($request, $access), 'label');
        $totals = $runner->run($request, $access)['totals']['values'];

        expect($statements($request))->toBe(['rows'])
            ->and($totals)->toMatchArray(['units' => 5.0, 'orders' => 2.0])
            ->and($totals['aov'])->toEqualWithDelta($totals['sales_total'] / 2, 0.0001)
            ->and($statements($request->with(['metrics' => ['units', 'unique_customers']])))->toBe(['rows', 'totals'])
            ->and($statements($request->with(['limit' => 1])))->toBe(['rows', 'totals'])
            ->and($runner->run($request->with(['limit' => 1]), $access)['totals']['values']['units'])->toBe(5.0);
    });
});

describe('the explanation of a change', function () {
    it('ranks the order statuses by how much of the drop of units they caused', function () {
        $result = Mage::getService('reports/explorer')->explain(
            explorerFeatureRequest(EXPLORER_FEATURES_DAY_TWO, ['compare' => 'previous_period']),
            Mage_Reports_Model_Explorer_Access::full(),
            'units',
            ['status'],
        );

        expect($result['total'])->toMatchArray(['current' => 1.0, 'earlier' => 5.0, 'change' => -4.0, 'percent' => -80.0])
            ->and($result['dimensions'])->toHaveCount(1)
            ->and(array_column($result['dimensions'][0]['drivers'], 'value'))->toBe(['pending', 'processing'])
            ->and(array_column($result['dimensions'][0]['drivers'], 'share'))->toBe([75.0, 25.0]);
    });

    it('shows a grouping once when another grouping splits the orders the same way', function () {
        $product = ReportFixture::createProduct('explorer_explain_twins', 10.0);
        ReportFixture::placeOrder($product, 2, '2004-05-20 12:00:00');
        ReportFixture::placeOrder($product, 1, '2004-05-20 13:00:00', customer: ReportFixture::createCustomer('explorer_twins'));
        ReportFixture::placeOrder($product, 1, '2004-05-21 12:00:00');

        $result = Mage::getService('reports/explorer')->explain(
            explorerFeatureRequest('2004-05-21', ['compare' => 'previous_period']),
            Mage_Reports_Model_Explorer_Access::full(),
            'units',
            ['customer_group_id', 'customer_type'],
        );

        expect(array_column($result['dimensions'], 'code'))->toBe(['customer_group_id'])
            ->and(array_column($result['dimensions'][0]['drivers'], 'change'))->toBe([-1.0, -1.0]);
    });

    it('refuses to explain a dataset that has no period', function () {
        $request = Request::fromArray(['dataset' => 'catalog_stock', 'metrics' => ['qty_on_hand']]);

        expect(fn() => Mage::getService('reports/explorer')->explain($request, Mage_Reports_Model_Explorer_Access::full(), 'qty_on_hand'))
            ->toThrow(Mage_Core_Exception_Input::class);
    });
});

describe('the dashboard, the notes and the targets', function () {
    beforeEach(function () {
        $this->owner = AdminRoleFixture::login(['report']);
        $this->other = AdminRoleFixture::login(['report']);
    });

    afterEach(function () {
        AdminRoleFixture::delete($this->owner);
        AdminRoleFixture::delete($this->other);
    });

    it('pins a shared report to the dashboard of another administrator, until its owner stops sharing it', function () {
        $service = Mage::getService('reports/savedReport');
        $access = Mage_Reports_Model_Explorer_Access::full();
        $report = $service->save($service->newReport()->setName('Units')->setIsShared()->setRequestData(['dataset' => 'sales_orders', 'metrics' => ['units']]), (int) $this->owner->getId(), $access);
        $other = (int) $this->other->getId();

        $service->pin($report, $other);
        $service->pin($report, $other);
        expect($service->pinnedIds($other))->toBe([(int) $report->getId()])
            ->and(array_map(static fn($r): int => (int) $r->getId(), $service->dashboardFor($other, $access)))->toBe([(int) $report->getId()]);

        $service->save($report->setIsShared(false), (int) $this->owner->getId(), $access);
        expect($service->dashboardFor($other, $access))->toBe([]);

        $service->unpin($report, $other);
        expect($service->pinnedIds($other))->toBe([]);
    });

    it('lets only the author change a note, and shows the notes on the days of a report', function () {
        $service = Mage::getService('reports/annotation');
        $note = $service->save($service->newAnnotation()->setAnnotationDate(EXPLORER_FEATURES_DAY_ONE)->setTitle('  Newsletter sent  '), (int) $this->owner->getId());

        try {
            $other = (int) $this->other->getId();
            expect(fn() => $service->delete($note, $other))->toThrow(Mage_Core_Exception_Forbidden::class)
                ->and(fn() => $service->save($service->getById((int) $note->getId())->setTitle('Changed'), $other))
                ->toThrow(Mage_Core_Exception_Forbidden::class)
                ->and(fn() => $service->save($service->newAnnotation()->setAnnotationDate(EXPLORER_FEATURES_DAY_ONE)->setTitle('   '), $other))
                ->toThrow(Mage_Core_Exception_Input::class)
                ->and(fn() => $service->save($service->newAnnotation()->setAnnotationDate('2004-02-30')->setTitle('A note'), (int) $this->owner->getId()))
                ->toThrow(Mage_Core_Exception_Input::class);

            $result = Mage::getService('reports/explorer')->run(
                explorerFeatureRequest(EXPLORER_FEATURES_DAY_ONE, ['dimensions' => ['period:day'], 'to' => EXPLORER_FEATURES_DAY_TWO]),
                Mage_Reports_Model_Explorer_Access::full(),
            );
            $notes = array_values(array_filter($result['annotations'], static fn(array $a): bool => $a['source'] === 'note'));
            expect($notes)->toHaveCount(1)
                ->and($notes[0])->toMatchArray(['title' => 'Newsletter sent', 'key' => EXPLORER_FEATURES_DAY_ONE]);
        } finally {
            $service->delete($note, (int) $this->owner->getId());
        }
    });

    it('keeps one target for each metric and month, refuses an average, and spreads it over the days of a report', function () {
        $service = Mage::getService('reports/target');
        $access = Mage_Reports_Model_Explorer_Access::full();
        $target = fn(string $metric, float $value) => $service->newTarget()->setDataset('sales_orders')->setMetric($metric)->setMonth('2004-05')->setValue($value);

        expect(fn() => $service->save($target('aov', 10.0), $access))->toThrow(Mage_Core_Exception_Input::class);
        $service->save($target('units', 62.0), $access);
        $kept = $service->save($target('units', 31.0), $access);

        try {
            expect($service->listFor('sales_orders', $access, 'units'))->toHaveCount(1);
            $result = Mage::getService('reports/explorer')->run(
                Request::fromArray(['dataset' => 'sales_orders', 'metrics' => ['units', 'aov'], 'dimensions' => ['period:day'], 'scope' => 'all', 'from' => EXPLORER_FEATURES_DAY_ONE, 'to' => EXPLORER_FEATURES_DAY_TWO]),
                $access,
            );
            expect($result['targets'])->toBe(['units' => ['buckets' => [EXPLORER_FEATURES_DAY_ONE => 1.0, EXPLORER_FEATURES_DAY_TWO => 1.0], 'total' => 2.0]]);

            $filtered = Mage::getService('reports/explorer')->run(
                Request::fromArray(['dataset' => 'sales_orders', 'metrics' => ['units'], 'dimensions' => ['period:day'], 'scope' => 'all', 'from' => EXPLORER_FEATURES_DAY_ONE, 'to' => EXPLORER_FEATURES_DAY_TWO, 'filters' => [['field' => 'status', 'operator' => 'eq', 'value' => 'processing']]]),
                $access,
            );
            expect($filtered['targets'])->toBe([]);
        } finally {
            $service->delete($kept);
        }
    });

    it('applies the target of one store view only to a report on that store view', function () {
        $service = Mage::getService('reports/target');
        $access = Mage_Reports_Model_Explorer_Access::full();
        $kept = $service->save($service->newTarget()->setDataset('sales_orders')->setMetric('units')->setMonth('2004-05')->setValue(31.0)->setStoreId(1), $access);
        $run = static fn(?array $storeIds): array => Mage::getService('reports/explorer')->run(
            Request::fromArray(['dataset' => 'sales_orders', 'metrics' => ['units'], 'dimensions' => ['period:day'], 'scope' => 'all', 'from' => EXPLORER_FEATURES_DAY_ONE, 'to' => EXPLORER_FEATURES_DAY_TWO] + ($storeIds === null ? [] : ['storeIds' => $storeIds])),
            $access,
        )['targets'];

        try {
            expect($run([1]))->toBe(['units' => ['buckets' => [EXPLORER_FEATURES_DAY_ONE => 1.0, EXPLORER_FEATURES_DAY_TWO => 1.0], 'total' => 2.0]])
                ->and($run(null))->toBe([])
                ->and($run([1, 2]))->toBe([]);
        } finally {
            $service->delete($kept);
        }
    });

    it('refuses a month that does not exist, and hides the targets of a dataset or a store that the caller may not read', function () {
        $service = Mage::getService('reports/target');
        $full = Mage_Reports_Model_Explorer_Access::full();
        foreach (['2026-13', '2026-00'] as $month) {
            expect(fn() => $service->save($service->newTarget()->setDataset('sales_orders')->setMetric('units')->setMonth($month)->setValue(1.0), $full))
                ->toThrow(Mage_Core_Exception_Input::class);
        }
        $kept = $service->save($service->newTarget()->setDataset('sales_orders')->setMetric('units')->setMonth('2004-05')->setValue(31.0)->setStoreId(1), $full);
        $id = (int) $kept->getId();

        try {
            foreach ([new Mage_Reports_Model_Explorer_Access(['carts'], true), new Mage_Reports_Model_Explorer_Access(null, true, [2])] as $access) {
                expect($service->listFor('sales_orders', $access, 'units'))->toBe([])
                    ->and(fn() => $service->getById($id, $access))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
            }
            expect(array_map(static fn($t): int => (int) $t->getId(), $service->listFor('sales_orders', $full, 'units')))->toBe([$id]);
        } finally {
            $service->delete($kept);
        }
    });
});
