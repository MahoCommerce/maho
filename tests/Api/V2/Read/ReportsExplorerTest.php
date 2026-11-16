<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 report explorer: the report, the datasets, the saved reports and the assistant tools.
 *
 * @group read
 */

const EXPLORER_API_DAY = '2004-04-20';
const EXPLORER_API_REPORT = '/api/rest/v2/reports/explore';
const EXPLORER_API_DATASETS = '/api/rest/v2/reports/explore/datasets';
const EXPLORER_API_SAVED = '/api/rest/v2/reports/saved';
const EXPLORER_API_RECORDS = '/api/rest/v2/reports/explore/records';
const EXPLORER_API_EXPLAIN = '/api/rest/v2/reports/explore/explain';
const EXPLORER_API_NOTES = '/api/rest/v2/reports/annotations';
const EXPLORER_API_TARGETS = '/api/rest/v2/reports/targets';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('explorer_api', 10.0);
    ReportFixture::placeOrder($product, 2, EXPLORER_API_DAY . ' 12:00:00');
    ReportFixture::placeOrder($product, 3, EXPLORER_API_DAY . ' 13:00:00');
});

afterAll(function (): void {
    ReportFixture::restore();
    ReportFixture::deleteFixtures();
    cleanupTestData();
});

/** @param array<string, string> $query */
function explorerApiUrl(array $query, string $path = EXPLORER_API_REPORT): string
{
    return $path . '?' . http_build_query($query + ['from' => EXPLORER_API_DAY, 'to' => EXPLORER_API_DAY, 'storeIds' => '1']);
}

describe('Report explorer API', function (): void {

    it('needs a token', function (): void {
        expect(apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders']))['status'])->toBe(401)
            ->and(apiGet(EXPLORER_API_DATASETS)['status'])->toBe(401);
    });

    it('runs a report from the query string', function (): void {
        $response = apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders,units', 'scope' => 'not_canceled']), adminToken());

        expect($response['status'])->toBe(200)
            ->and($response['json']['totals']['values'])->toEqual(['orders' => 2, 'units' => 5]);
    });

    it('gives the same report for QUERY with a body as for GET', function (): void {
        $token = adminToken();
        $query = apiQuery(EXPLORER_API_REPORT, [
            'dataset' => 'sales_orders',
            'metrics' => ['orders', 'units'],
            'scope' => 'not_canceled',
            'from' => EXPLORER_API_DAY,
            'to' => EXPLORER_API_DAY,
            'storeIds' => [1],
        ], $token);
        // The PHP built-in server (CI and local dev) answers 501 to the QUERY method before Maho runs
        if ($query['status'] === 501) {
            $this->markTestSkipped('The web server does not route the HTTP QUERY method');
        }
        $get = apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders,units', 'scope' => 'not_canceled']), $token);

        expect($query['status'])->toBe(200)
            ->and($query['json']['totals'])->toBe($get['json']['totals'])
            ->and($query['json']['rows'])->toBe($get['json']['rows']);
    });

    it('runs a preset and lets the query change its period', function (): void {
        $response = apiGet(explorerApiUrl(['preset' => 'sales_overview']), adminToken());

        expect($response['status'])->toBe(200)
            ->and($response['json']['dataset'])->toBe('sales_orders')
            ->and($response['json']['period']['from'])->toBe(EXPLORER_API_DAY)
            ->and($response['json']['totals']['values']['orders'])->toEqual(2);
    });

    it('names every unknown code in one 422 answer', function (): void {
        $response = apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders,nope', 'dimensions' => 'nothing']), adminToken());

        expect($response['status'])->toBe(422)
            ->and(count($response['json']['details']['errors'] ?? []))->toBeGreaterThanOrEqual(2);
    });

    it('refuses filters that are not JSON with 400', function (): void {
        $response = apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders', 'filters' => '[{']), adminToken());

        expect($response['status'])->toBe(400);
    });

    it('needs the customers permission for the personal data', function (): void {
        $reports = serviceToken(['reports/read']);
        $catalog = apiGet(EXPLORER_API_DATASETS, $reports);
        $orders = array_first(array_filter($catalog['json'], fn(array $d): bool => $d['code'] === 'sales_orders'));
        $personal = explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders', 'dimensions' => 'customer_email']);

        expect($catalog['status'])->toBe(200)
            ->and(array_column($orders['dimensions'], 'code'))->not->toContain('customer_email')
            ->and(apiGet($personal, $reports)['status'])->toBe(422)
            ->and(apiGet($personal, serviceToken(['reports/read', 'customers/read']))['status'])->toBe(200);
    });

    it('keeps a token with a store restriction in its stores', function (): void {
        $token = serviceToken(['reports/read'], [1]);
        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));

        expect(apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders']), $token)['status'])->toBe(200);
        if ($otherStores !== []) {
            $url = explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders', 'storeIds' => (string) $otherStores[0]]);
            expect(apiGet($url, $token)['status'])->toBe(422);
        }
    });

    it('applies the admin ACL of the explorer and of each dataset', function (): void {
        $salesOnly = adminTokenWithAcl(['admin/report/salesroot/sales'], 'apitest_explorer_sales');
        $explorer = adminTokenWithAcl(['admin/report/explorer', 'admin/report/salesroot/sales'], 'apitest_explorer_orders');

        expect(apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders']), $salesOnly)['status'])->toBe(403)
            ->and(apiGet(explorerApiUrl(['dataset' => 'sales_orders', 'metrics' => 'orders']), $explorer)['status'])->toBe(200)
            ->and(apiGet(explorerApiUrl(['dataset' => 'sales_items', 'metrics' => 'units']), $explorer)['status'])->toBe(422);

        $codes = array_column(apiGet(EXPLORER_API_DATASETS, $explorer)['json'], 'code');
        expect($codes)->toContain('sales_orders')->not->toContain('sales_items');
    });
});

describe('Saved reports API', function (): void {

    it('lets an administrator create, change and delete an own report', function (): void {
        $token = adminToken();
        $created = apiPost(EXPLORER_API_SAVED, [
            'name' => '  Orders by day  ',
            'request' => ['dataset' => 'sales_orders', 'metrics' => ['orders'], 'dimensions' => ['period:day']],
        ], $token);
        expect($created['status'])->toBe(201)
            ->and($created['json']['name'])->toBe('Orders by day')
            ->and($created['json']['isShared'])->toBeFalse()
            ->and($created['json']['request']['dataset'])->toBe('sales_orders');
        $url = EXPLORER_API_SAVED . '/' . $created['json']['id'];

        $patched = apiPatch($url, ['isShared' => true], $token, ['Content-Type' => 'application/merge-patch+json']);
        expect($patched['status'])->toBe(200)
            ->and($patched['json']['isShared'])->toBeTrue()
            ->and($patched['json']['name'])->toBe('Orders by day');

        expect(apiGet($url, $token)['json']['request']['metrics'])->toBe(['orders'])
            ->and(apiDelete($url, $token)['status'])->toBe(204)
            ->and(apiGet($url, $token)['status'])->toBe(404);
    });

    it('refuses a report with an unknown metric or no name', function (): void {
        $response = apiPost(EXPLORER_API_SAVED, ['name' => '', 'request' => ['dataset' => 'sales_orders', 'metrics' => ['nope']]], adminToken());

        expect($response['status'])->toBe(422);
    });

    it('lets an API key read the shared reports only, and never write', function (): void {
        $admin = adminToken();
        $shared = apiPost(EXPLORER_API_SAVED, ['name' => 'Shared', 'isShared' => true, 'request' => ['dataset' => 'sales_orders', 'metrics' => ['orders']]], $admin);
        $private = apiPost(EXPLORER_API_SAVED, ['name' => 'Private', 'request' => ['dataset' => 'sales_orders', 'metrics' => ['orders']]], $admin);
        $key = serviceToken(['reports/read']);

        try {
            $names = array_column(apiGet(EXPLORER_API_SAVED, $key)['json']['member'] ?? [], 'name');
            expect($names)->toContain('Shared')->not->toContain('Private')
                ->and(apiGet(EXPLORER_API_SAVED . '/' . $private['json']['id'], $key)['status'])->toBe(404)
                ->and(apiPost(EXPLORER_API_SAVED, ['name' => 'Key', 'request' => ['dataset' => 'sales_orders', 'metrics' => ['orders']]], $key)['status'])->toBe(403)
                ->and(apiDelete(EXPLORER_API_SAVED . '/' . $shared['json']['id'], $key)['status'])->toBe(403);
        } finally {
            apiDelete(EXPLORER_API_SAVED . '/' . $shared['json']['id'], $admin);
            apiDelete(EXPLORER_API_SAVED . '/' . $private['json']['id'], $admin);
        }
    });
});

describe('Records and explanations API', function (): void {

    it('lists the orders behind a report, with the permission of the orders', function (): void {
        $url = explorerApiUrl(['dataset' => 'sales_orders', 'scope' => 'not_canceled'], EXPLORER_API_RECORDS);
        $response = apiGet($url, adminToken());

        expect($response['status'])->toBe(200)
            ->and($response['json']['totalRows'])->toBe(2)
            ->and(array_column($response['json']['columns'], 'key'))->toContain('increment_id')
            ->and($response['json']['rows'][0])->toHaveKeys(['id', 'values'])
            ->and(apiGet($url, serviceToken(['reports/read']))['status'])->toBe(422)
            ->and(apiGet($url, serviceToken(['reports/read', 'orders/read']))['status'])->toBe(200);
    });

    it('explains the change of a metric against the period before', function (): void {
        $url = explorerApiUrl(['dataset' => 'sales_orders', 'scope' => 'not_canceled', 'metric' => 'units', 'explainDimensions' => 'status'], EXPLORER_API_EXPLAIN);
        $response = apiGet($url, adminToken());

        expect($response['status'])->toBe(200)
            ->and($response['json']['total'])->toMatchArray(['current' => 5, 'earlier' => 0, 'change' => 5])
            // Both orders have one status, and a grouping with one value tells nothing more than the total
            ->and($response['json']['dimensions'])->toBe([])
            ->and(apiGet(explorerApiUrl(['dataset' => 'sales_orders'], EXPLORER_API_EXPLAIN), adminToken())['status'])->toBe(422);
    });
});

describe('Dashboard, notes and targets API', function (): void {

    it('pins a saved report to the dashboard of the administrator, and takes it off', function (): void {
        $token = adminToken();
        $report = apiPost(EXPLORER_API_SAVED, ['name' => 'Pinned', 'request' => ['dataset' => 'sales_orders', 'metrics' => ['orders']]], $token);
        $url = EXPLORER_API_SAVED . '/' . $report['json']['id'];

        try {
            expect(apiPost($url . '/pin', [], $token)['status'])->toBe(200)
                ->and(apiGet($url, $token)['json']['isPinned'])->toBeTrue()
                ->and(apiPost($url . '/pin', [], serviceToken(['reports/read']))['status'])->toBe(403);
            expect(apiDelete($url . '/pin', $token)['status'])->toBe(204)
                ->and(apiGet($url, $token)['json']['isPinned'])->toBeFalse();
        } finally {
            apiDelete($url, $token);
        }
    });

    it('lets an administrator add, list, change and delete a note', function (): void {
        $token = adminToken();
        $created = apiPost(EXPLORER_API_NOTES, ['date' => EXPLORER_API_DAY, 'title' => 'Newsletter sent'], $token);
        expect($created['status'])->toBe(201)
            ->and($created['json']['date'])->toBe(EXPLORER_API_DAY);
        $url = EXPLORER_API_NOTES . '/' . $created['json']['id'];

        try {
            $list = apiGet(EXPLORER_API_NOTES . '?' . http_build_query(['from' => EXPLORER_API_DAY, 'to' => EXPLORER_API_DAY]), $token);
            expect(array_column($list['json']['member'] ?? [], 'title'))->toContain('Newsletter sent')
                ->and(apiPatch($url, ['title' => 'Spring newsletter'], $token, ['Content-Type' => 'application/merge-patch+json'])['json']['title'])->toBe('Spring newsletter')
                ->and(apiPost(EXPLORER_API_NOTES, ['date' => '2004-02-30', 'title' => 'Bad day'], $token)['status'])->toBe(422)
                ->and(apiPost(EXPLORER_API_NOTES, ['date' => EXPLORER_API_DAY, 'title' => 'Key'], serviceToken(['reports/read']))['status'])->toBe(403);
        } finally {
            expect(apiDelete($url, $token)['status'])->toBe(204);
        }
    });

    it('keeps a monthly target of an additive metric and shows it in a report by day', function (): void {
        $token = adminToken();
        expect(apiPost(EXPLORER_API_TARGETS, ['dataset' => 'sales_orders', 'metric' => 'aov', 'month' => '2004-04', 'value' => 10], $token)['status'])->toBe(422);
        $created = apiPost(EXPLORER_API_TARGETS, ['dataset' => 'sales_orders', 'metric' => 'units', 'month' => '2004-04', 'value' => 300], $token);
        expect($created['status'])->toBe(201)
            ->and($created['json']['month'])->toBe('2004-04');

        try {
            $list = apiGet(EXPLORER_API_TARGETS . '?dataset=sales_orders&metric=units', $token);
            // A target of store 0 applies to a report of all the stores
            $report = apiGet(EXPLORER_API_REPORT . '?' . http_build_query(['dataset' => 'sales_orders', 'metrics' => 'units', 'dimensions' => 'period:day', 'from' => EXPLORER_API_DAY, 'to' => EXPLORER_API_DAY]), $token);
            expect(array_column($list['json']['member'] ?? [], 'value'))->toContain(300)
                ->and($report['json']['targets']['units']['total'] ?? null)->toEqual(10);
        } finally {
            expect(apiDelete(EXPLORER_API_TARGETS . '/' . $created['json']['id'], $token)['status'])->toBe(204);
        }
    });
});

describe('Report explorer tools of the assistant', function (): void {

    it('offers the report tool with the arguments of the query string', function (): void {
        if (!mcpPackagesInstalled()) {
            $this->markTestSkipped('The MCP packages are not installed.');
        }
        $token = adminToken();
        $tools = mcpTools($token, mcpSession($token));

        expect($tools)->toHaveKeys([
            'explorer_reports_explore_list', 'explorer_reports_explore_datasets_list', 'explorer_reports_saved_list',
            'explorer_reports_explore_records_list', 'explorer_reports_explore_explain_list',
            'explorer_reports_saved_pin_create', 'explorer_reports_saved_pin_delete',
            'explorer_reports_annotations_list', 'explorer_reports_annotations_create',
            'explorer_reports_targets_list', 'explorer_reports_targets_create',
        ])
            ->and(array_keys($tools['explorer_reports_explore_explain_list']['inputSchema']['properties'] ?? []))
            ->toContain('metric', 'explainDimensions')
            ->and(array_keys($tools['explorer_reports_explore_list']['inputSchema']['properties'] ?? []))
            ->toContain('preset', 'dataset', 'metrics', 'dimensions', 'filters', 'range', 'compare');
    });
});
