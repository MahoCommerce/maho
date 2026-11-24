<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1832: the 404 Errors report keeps one row for each storefront path that answered 404.
 */
function notFoundRecord(string $uri, int $status = 404, array $server = []): bool
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create($uri, 'GET', [], [], [], $server));
    $response = new Mage_Core_Controller_Response_Http();
    $response->setHttpResponseCode($status);
    return Mage::getSingleton('reports/notFound')->recordRequest($request, $response);
}

function notFoundRow(string $path, int $storeId): ?array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $row = $adapter->fetchRow(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('reports/not_found'))
            ->where('request_path = ?', $path)
            ->where('store_id = ?', $storeId),
    );
    return $row ?: null;
}

/**
 * The records of the 404 errors dataset of the report explorer for one path.
 *
 * @param list<array<string, mixed>> $filters
 * @return array<string, mixed>
 */
function notFoundReportRows(string $path, array $filters = []): array
{
    $request = Maho\Report\Request::fromArray([
        'dataset' => 'not_found',
        'metrics' => ['hits'],
        'filters' => [['field' => 'request_path', 'operator' => 'eq', 'value' => $path], ...$filters],
    ]);
    return Mage::getService('reports/explorer')->records($request, Mage_Reports_Model_Explorer_Access::full());
}

/** The number of rows of a path that a URL rewrite redirects ($hasRedirect true), or of the other rows. */
function notFoundCount(string $path, bool $hasRedirect): int
{
    return notFoundReportRows($path, [['field' => 'has_redirect', 'operator' => 'eq', 'value' => (int) $hasRedirect]])['totalRows'];
}

describe('404 Errors report', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->store = Mage::app()->getStore();
        $this->storeId = (int) $this->store->getId();
        $this->store->setConfig(Mage_Reports_Model_NotFound::XML_PATH_ENABLED, '1');
        $this->store->setConfig(Mage_Reports_Model_NotFound::XML_PATH_LOG_RATE_LIMIT, '0');
        $this->path = 'missing-page-' . uniqid();
        $this->write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->table = Mage::getSingleton('core/resource')->getTableName('reports/not_found');
    });

    afterEach(function () {
        $this->write->delete($this->table, ['request_path LIKE ?' => '%missing-page-%']);
    });

    it('creates a row for a 404 and counts the next hits', function () {
        expect(notFoundRecord('http://localhost/' . $this->path))->toBeTrue();
        $first = notFoundRow($this->path, $this->storeId);
        expect((int) $first['hits'])->toBe(1);
        expect($first['first_seen_at'])->not->toBeNull();

        expect(notFoundRecord('http://localhost/' . $this->path))->toBeTrue();
        $second = notFoundRow($this->path, $this->storeId);
        expect((int) $second['hits'])->toBe(2);
        expect($second['first_seen_at'])->toBe($first['first_seen_at']);
    });

    it('keeps the path in the format of a URL rewrite', function () {
        $upper = strtoupper($this->path);
        notFoundRecord('http://localhost/' . $upper . '/?utm_source=mail');
        notFoundRecord('http://localhost/' . $this->path . '?utm_source=other');

        $row = notFoundRow($this->path, $this->storeId);
        expect($row)->not->toBeNull();
        expect((int) $row['hits'])->toBe(2);
    });

    it('keeps the last referer when a hit has none', function () {
        notFoundRecord('http://localhost/' . $this->path, 404, ['HTTP_REFERER' => 'https://example.com/old-link']);
        notFoundRecord('http://localhost/' . $this->path);

        expect(notFoundRow($this->path, $this->storeId)['referer'])->toBe('https://example.com/old-link');

        notFoundRecord('http://localhost/' . $this->path, 404, ['HTTP_REFERER' => 'https://example.org/new-link']);
        expect(notFoundRow($this->path, $this->storeId)['referer'])->toBe('https://example.org/new-link');
    });

    it('records only a 404', function (int $status) {
        expect(notFoundRecord('http://localhost/' . $this->path, $status))->toBeFalse();
        expect(notFoundRow($this->path, $this->storeId))->toBeNull();
    })->with([200, 301, 410, 500]);

    it('skips a path that a URL rewrite cannot hold', function (string $suffix) {
        expect(notFoundRecord('http://localhost/' . $suffix))->toBeFalse();
    })->with([
        'the home page' => [''],
        'the API' => ['api/soap/missing-page-x'],
        'a well-known path' => ['.well-known/missing-page-x'],
        'a path over 255 characters' => ['missing-page-' . str_repeat('a', 260)],
    ]);

    it('counts the length of the path in characters', function () {
        $path = $this->path . '-' . str_repeat('é', 128);

        expect(notFoundRecord('http://localhost/' . $path))->toBeTrue();
        expect(notFoundRow($path, $this->storeId))->not->toBeNull();
    });

    it('records nothing in the admin store', function () {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        try {
            expect(notFoundRecord('http://localhost/' . $this->path))->toBeFalse();
        } finally {
            Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        }
    });

    it('records nothing when the report is off', function () {
        $this->store->setConfig(Mage_Reports_Model_NotFound::XML_PATH_ENABLED, '0');

        expect(notFoundRecord('http://localhost/' . $this->path))->toBeFalse();
        expect(notFoundRow($this->path, $this->storeId))->toBeNull();
    });

    it('stops recording a client after its hourly budget', function () {
        $this->store->setConfig(Mage_Reports_Model_NotFound::XML_PATH_LOG_RATE_LIMIT, '1');
        Mage::helper('core')
            ->rateLimiter(Mage_Reports_Model_NotFound::RATE_LIMIT_NAMESPACE, 1, Mage_Reports_Model_NotFound::RATE_LIMIT_WINDOW)
            ->clear();

        expect(notFoundRecord('http://localhost/' . $this->path))->toBeTrue();
        expect(notFoundRecord('http://localhost/' . $this->path . '-second'))->toBeFalse();
        expect(notFoundRow($this->path . '-second', $this->storeId))->toBeNull();
    });

    it('deletes old rows and keeps the newest rows up to the maximum', function () {
        $locale = Mage::app()->getLocale();
        foreach ([1, 2, 3, 40] as $daysAgo) {
            $date = $locale->formatDateForDb("-{$daysAgo} days");
            $this->write->insert($this->table, [
                'store_id' => $this->storeId,
                'request_path' => "{$this->path}-{$daysAgo}",
                'hits' => 1,
                'first_seen_at' => $date,
                'last_seen_at' => $date,
            ]);
        }
        $adapter = $this->write;
        $count = fn(): int => (int) $adapter->fetchOne(
            $adapter->select()->from($this->table, 'COUNT(*)')->where('request_path LIKE ?', $this->path . '-%'),
        );

        Mage::getResourceSingleton('reports/notFound')->clean(30, 0);
        expect($count())->toBe(3);
        expect(notFoundRow("{$this->path}-40", $this->storeId))->toBeNull();

        $others = (int) $adapter->fetchOne($adapter->select()->from($this->table, 'COUNT(*)'))
            - $count();
        Mage::getResourceSingleton('reports/notFound')->clean(0, $others + 2);
        expect($count())->toBe(2);
        expect(notFoundRow("{$this->path}-3", $this->storeId))->toBeNull();
    });

    it('keeps one row for each path and store', function () {
        $resource = Mage::getResourceSingleton('reports/notFound');
        $resource->recordHit($this->storeId, $this->path, null);
        $resource->recordHit(Mage_Core_Model_App::ADMIN_STORE_ID, $this->path, null);
        $resource->recordHit($this->storeId, $this->path, null);

        expect((int) notFoundRow($this->path, $this->storeId)['hits'])->toBe(2);
        expect((int) notFoundRow($this->path, Mage_Core_Model_App::ADMIN_STORE_ID)['hits'])->toBe(1);
    });

    it('shows the target of a URL rewrite that redirects the path', function () {
        notFoundRecord('http://localhost/' . $this->path);
        expect(notFoundCount($this->path, false))->toBe(1);
        expect(notFoundCount($this->path, true))->toBe(0);

        $rewriteTable = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $this->write->insert($rewriteTable, [
            'store_id' => $this->storeId,
            'id_path' => $this->path,
            'request_path' => $this->path,
            'target_path' => 'new-page.html',
            'is_system' => 0,
            'options' => 'RP',
        ]);

        try {
            $row = notFoundReportRows($this->path)['rows'][0];
            expect($row['values']['redirect_target'])->toBe('new-page.html');
            expect($row['routeQuery'])->toBeNull();
            expect(notFoundCount($this->path, false))->toBe(0);
        } finally {
            $this->write->delete($rewriteTable, ['id_path = ?' => $this->path]);
        }
    });
});

describe('404 Errors report with a URL rewrite that does not redirect', function () {
    beforeEach(function () {
        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $this->path = 'missing-page-' . uniqid();
        $this->write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->rewriteTable = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $this->addRewrite = fn(int $storeId, string $options, string $target) => $this->write->insert($this->rewriteTable, [
            'store_id' => $storeId,
            'id_path' => "{$this->path}-{$storeId}",
            'request_path' => $this->path,
            'target_path' => $target,
            'is_system' => 0,
            'options' => $options,
        ]);
        $this->row = fn(): array => notFoundReportRows($this->path)['rows'][0];
        Mage::getResourceSingleton('reports/notFound')->recordHit($this->storeId, $this->path, null);
    });

    afterEach(function () {
        $this->write->delete($this->rewriteTable, ['request_path = ?' => $this->path]);
        $this->write->delete(
            Mage::getSingleton('core/resource')->getTableName('reports/not_found'),
            ['request_path = ?' => $this->path],
        );
    });

    it('does not count a rewrite of the store view that does not redirect, and offers no new redirect', function () {
        ($this->addRewrite)($this->storeId, '', 'catalog/product/view/id/1');

        $row = ($this->row)();
        expect($row['values']['redirect_target'])->toBeNull();
        expect($row['routeQuery'])->toBeNull();
        expect(notFoundCount($this->path, false))->toBe(1);
        expect(notFoundCount($this->path, true))->toBe(0);
    });

    it('does not count a redirect of store 0 when the rewrite of the store view does not redirect', function () {
        ($this->addRewrite)($this->storeId, '', 'catalog/product/view/id/1');
        ($this->addRewrite)(Mage_Core_Model_App::ADMIN_STORE_ID, 'RP', 'default-page.html');

        expect(($this->row)()['values']['redirect_target'])->toBeNull();
        expect(notFoundCount($this->path, false))->toBe(1);
    });

    it('offers a new redirect when only store 0 has a rewrite, and it does not redirect', function () {
        ($this->addRewrite)(Mage_Core_Model_App::ADMIN_STORE_ID, '', 'catalog/product/view/id/1');

        $row = ($this->row)();
        expect($row['values']['redirect_target'])->toBeNull();
        expect($row['routeQuery']['request_path'] ?? null)->toBe($this->path);
        expect((int) ($row['routeQuery']['store_id'] ?? 0))->toBe($this->storeId);
        expect(notFoundCount($this->path, false))->toBe(1);
        expect(notFoundCount($this->path, true))->toBe(0);
    });

    it('counts a rewrite with an external target as a redirect, as the storefront does', function () {
        ($this->addRewrite)($this->storeId, '', 'https://example.com/new-page');

        expect(($this->row)()['values']['redirect_target'])->toBe('https://example.com/new-page');
        expect(notFoundCount($this->path, true))->toBe(1);
    });

    it('counts an http target of store 0 as a redirect when the store view has none', function () {
        ($this->addRewrite)(Mage_Core_Model_App::ADMIN_STORE_ID, '', 'http://example.com/new-page');

        expect(($this->row)()['values']['redirect_target'])->toBe('http://example.com/new-page');
        expect(notFoundCount($this->path, true))->toBe(1);
    });

    it('prefers the redirect of the store view over the redirect of store 0', function () {
        ($this->addRewrite)($this->storeId, 'RP', 'view-page.html');
        ($this->addRewrite)(Mage_Core_Model_App::ADMIN_STORE_ID, 'RP', 'default-page.html');

        $records = notFoundReportRows($this->path);
        expect($records['totalRows'])->toBe(1);
        expect($records['rows'][0]['values']['redirect_target'])->toBe('view-page.html');
    });

    it('shows the target of a rewrite of store 0 when the store view has none', function () {
        ($this->addRewrite)(Mage_Core_Model_App::ADMIN_STORE_ID, 'RP', 'default-page.html');

        $records = notFoundReportRows($this->path);
        expect($records['totalRows'])->toBe(1);
        expect($records['rows'][0]['values']['redirect_target'])->toBe('default-page.html');
        expect(notFoundCount($this->path, true))->toBe(1);
    });
});

describe('404 Errors report links', function () {
    beforeEach(function () {
        Mage::app()->getRequest()->setRouteName('adminhtml');
        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $this->path = 'missing-page-' . uniqid() . '/a b&c';
        Mage::getResourceSingleton('reports/notFound')->recordHit($this->storeId, $this->path, null);
        $this->row = fn(): array => Mage::helper('reports')->addRecordUrls(notFoundReportRows($this->path))['rows'][0];
    });

    afterEach(function () {
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
            Mage::getSingleton('core/resource')->getTableName('reports/not_found'),
            ['request_path = ?' => $this->path],
        );
    });

    it('links an address without a rewrite to a new redirect, for an admin who may edit URL rewrites', function () {
        $user = Tests\Helpers\AdminRoleFixture::login(['admin/catalog/urlrewrite']);
        try {
            $row = ($this->row)();
            parse_str((string) parse_url(html_entity_decode($row['url'] ?? ''), PHP_URL_QUERY), $query);
            expect($query['request_path'] ?? null)->toBe($this->path);
            expect($query['store_id'] ?? null)->toBe((string) $this->storeId);
            expect($row)->not->toHaveKey('routeQuery');
        } finally {
            Tests\Helpers\AdminRoleFixture::delete($user);
        }
    });

    it('gives no link to an admin who may not edit URL rewrites', function () {
        $user = Tests\Helpers\AdminRoleFixture::login(['admin/catalog/products']);
        try {
            expect(($this->row)())->not->toHaveKey('url');
        } finally {
            Tests\Helpers\AdminRoleFixture::delete($user);
        }
    });
});

describe('URL rewrite edit page prefill', function () {
    it('fills a new custom rewrite from the request, and opens the custom form', function () {
        $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/index.php/admin/urlrewrite/edit', 'GET', [
            'request_path' => 'old-page.html',
            'store_id' => (string) $storeId,
        ]));
        $request->setRouteName('adminhtml')->setControllerName('urlrewrite')->setActionName('edit')->setDispatched(true);
        Mage::app()->setRequest($request);

        try {
            new Mage_Adminhtml_UrlrewriteController($request, new Mage_Core_Controller_Response_Http())->editAction();
        } catch (Throwable) {
            // editAction() fails in a test when it builds the layout. It sets the rewrite before that step.
        }

        $rewrite = Mage::registry('current_urlrewrite');
        expect($rewrite->getId())->toBeNull();
        expect($rewrite->getRequestPath())->toBe('old-page.html');
        expect($rewrite->getIdPath())->toBe('old-page.html');
        expect($rewrite->getOptions())->toBe('RP');
        expect($rewrite->getStoreId())->toBe($storeId);
        expect($request->has('id'))->toBeTrue();
    });
});
