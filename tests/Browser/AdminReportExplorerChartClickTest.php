<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * A click on a chart of Reports > Explorer with two groupings opens the drill menu of the series under the pointer.
 *
 * One day has 10 units in a processing order and 1 unit in a pending order, so the processing bar is tall and the
 * pending bar beside it is short. The test clicks inside the tall bar near its base, at a point that is nearer to
 * the center of the short bar. With `intersect: false`, Chart.js picks the bar with the nearest center, which is the
 * short bar. With `intersect: true`, it picks only a bar that holds the click, which is the tall bar.
 */

const EXPLORER_CLICK_ADMIN_USER = 'explorer-click-admin';
const EXPLORER_CLICK_ADMIN_PASSWORD = 'Password123!';
const EXPLORER_CLICK_DAY = '2004-07-10';

/** Find a tall bar beside a short bar of the same day, and click inside the tall bar near its base. */
const EXPLORER_CLICK_BAR = <<<'JS'
    (() => {
        const canvas = document.querySelector('#report-explorer [data-role=chart]');
        const chart = Chart.getChart(canvas);
        const rect = canvas.getBoundingClientRect();
        const sets = chart.data.datasets.map((dataset, index) => ({ label: dataset.label, bars: chart.getDatasetMeta(index).data }));
        for (let day = 0; day < sets[0].bars.length; day++) {
            for (const tall of sets) {
                for (const short of sets) {
                    const tallBar = tall.bars[day].getProps(['x', 'y', 'base'], true);
                    const shortBar = short.bars[day].getProps(['x', 'y', 'base'], true);
                    if (tall === short || !(shortBar.base - shortBar.y > 0) || !(tallBar.base - tallBar.y >= 4 * (shortBar.base - shortBar.y))) {
                        continue;
                    }
                    const x = tallBar.x;
                    const y = tallBar.base - 2;
                    if (!(Math.hypot(shortBar.x - x, (shortBar.y + shortBar.base) / 2 - y) < (tallBar.base - tallBar.y) / 2 - 2)) {
                        continue;
                    }
                    canvas.dispatchEvent(new MouseEvent('click', { clientX: rect.left + x, clientY: rect.top + y, bubbles: true }));
                    return tall.label;
                }
            }
        }
        return null;
    })()
    JS;

/** Click on the line of the first series, halfway between its second and third points. */
const EXPLORER_CLICK_LINE = <<<'JS'
    (() => {
        const canvas = document.querySelector('#report-explorer [data-role=chart]');
        const chart = Chart.getChart(canvas);
        const rect = canvas.getBoundingClientRect();
        const points = chart.getDatasetMeta(0).data;
        const second = points[1].getProps(['x', 'y'], true);
        const third = points[2].getProps(['x', 'y'], true);
        canvas.dispatchEvent(new MouseEvent('click', { clientX: rect.left + (second.x + third.x) / 2, clientY: rect.top + (second.y + third.y) / 2 + 3, bubbles: true }));
        return true;
    })()
    JS;

beforeEach(function () {
    createBrowserAdmin(EXPLORER_CLICK_ADMIN_USER, EXPLORER_CLICK_ADMIN_PASSWORD);

    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer_click', 10.0);
    ReportFixture::placeOrder($product, 10, EXPLORER_CLICK_DAY . ' 12:00:00');
    $pending = ReportFixture::placeOrder($product, 1, EXPLORER_CLICK_DAY . ' 13:00:00', invoice: false);
    ReportFixture::adapter()->update(ReportFixture::table('sales/order'), ['state' => 'new', 'status' => 'pending'], ['entity_id = ?' => (int) $pending->getId()]);
    $this->productId = (int) $product->getId();
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    try {
        ReportFixture::restore();
    } finally {
        Mage::unregister('isSecureArea');
        deleteBrowserAdmin(EXPLORER_CLICK_ADMIN_USER);
    }
});

/** Open Reports > Explorer on the units of the fixture product by day and by order status, as a chart of $chartType. */
function openExplorerClickChart(int $productId, string $chartType): object
{
    $encode = static fn(array $data): string => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $state = [
        'dataset' => 'sales_items',
        'metrics' => ['units'],
        'dimensions' => ['period:day', 'status'],
        'scope' => 'all',
        'from' => '2004-07-01',
        'to' => '2004-07-14',
        'filters' => [['field' => 'product_id', 'operator' => 'eq', 'value' => $productId]],
    ];
    $page = adminLoginAndVisit(
        EXPLORER_CLICK_ADMIN_USER,
        EXPLORER_CLICK_ADMIN_PASSWORD,
        '/admin/report_explorer/index?state=' . $encode($state) . '&view=' . $encode(['tab' => 'explore', 'chartType' => $chartType]),
        '#report-explorer',
    );
    waitForScript($page, "(() => { const c = document.querySelector('#report-explorer [data-role=chart]'); return Boolean(window.Chart && c && Chart.getChart(c) && Chart.getChart(c).data.datasets.length === 2); })()", 15);

    return $page;
}

it('opens the drill menu of the bar under the pointer, not of the short bar beside it', function () {
    $page = openExplorerClickChart($this->productId, 'bar');

    $label = $page->script(EXPLORER_CLICK_BAR);
    expect($label)->toBeString();
    waitForScript($page, "document.querySelector('[role=menu] .d-menu-title') !== null");
    expect($page->script("document.querySelector('[role=menu] .d-menu-title').textContent"))->toContain($label);
});

it('opens the drill menu of the nearest point on a line chart, also between two points', function () {
    $page = openExplorerClickChart($this->productId, 'line');

    $page->script(EXPLORER_CLICK_LINE);
    waitForScript($page, "document.querySelector('[role=menu] .d-menu-title') !== null");
    expect($page->script("document.querySelector('[role=menu] .d-menu-title').textContent"))->toContain('Order status');
});
