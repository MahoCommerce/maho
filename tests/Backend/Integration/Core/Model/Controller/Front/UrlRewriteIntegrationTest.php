<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * End-to-end test for DB-backed URL rewrite processing in the post-migration
 * pipeline: Mage_Core_Model_Controller_Front_Observer::rewriteDb() loads
 * a row from core_url_rewrite and rewrites the request's pathInfo in place,
 * which the Symfony matcher then picks up (via dispatchLegacyPath for legacy
 * target paths).
 *
 * The test writes a real row, drives the observer, then cleans up.
 */

function deleteUrlRewrite(int $id): void
{
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
    $write->delete($table, ['url_rewrite_id = ?' => $id]);
}

function callRewriteDb(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
{
    $observer = new Mage_Core_Model_Controller_Front_Observer();
    $ref = new ReflectionMethod($observer, 'rewriteDb');
    $ref->invoke($observer, $request, $response);
}

function makeFrontendRequest(string $pathInfo): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
    $request->setPathInfo($pathInfo);
    return $request;
}

describe('URL rewrite DB integration', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->storeId = (int) Mage::app()->getStore()->getId();
        $this->insertedIds = [];
    });

    afterEach(function () {
        foreach ($this->insertedIds ?? [] as $id) {
            deleteUrlRewrite($id);
        }
    });

    it('rewrites request pathInfo when a core_url_rewrite row matches', function () {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $write->insert($table, [
            'store_id'     => $this->storeId,
            'id_path'      => 'test_rewrite_' . uniqid(),
            'request_path' => 'my-cool-product.html',
            'target_path'  => 'catalog/product/view/id/123',
            'is_system'    => 0,
        ]);
        $this->insertedIds[] = (int) $write->lastInsertId();

        $request = makeFrontendRequest('/my-cool-product.html');
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteDb($request, $response);

        expect($request->getPathInfo())->toBe('catalog/product/view/id/123');
        expect($response->isRedirect())->toBeFalse();
    });

    it('issues an HTTP redirect when the rewrite has option RP (permanent)', function () {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $write->insert($table, [
            'store_id'     => $this->storeId,
            'id_path'      => 'test_rewrite_perm_' . uniqid(),
            'request_path' => 'old-url.html',
            'target_path'  => 'new-url.html',
            'is_system'    => 0,
            'options'      => 'RP',
        ]);
        $this->insertedIds[] = (int) $write->lastInsertId();

        $request = makeFrontendRequest('/old-url.html');
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteDb($request, $response);

        expect($response->isRedirect())->toBeTrue();
        expect($response->getHttpResponseCode())->toBe(301);
    });

    it('issues a redirect to an external URL when target_path is absolute http(s)', function () {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $write->insert($table, [
            'store_id'     => $this->storeId,
            'id_path'      => 'test_rewrite_ext_' . uniqid(),
            'request_path' => 'external-link.html',
            'target_path'  => 'https://external.example.com/landing',
            'is_system'    => 0,
            'options'      => 'R',
        ]);
        $this->insertedIds[] = (int) $write->lastInsertId();

        $request = makeFrontendRequest('/external-link.html');
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteDb($request, $response);

        expect($response->isRedirect())->toBeTrue();
    });

    it('leaves pathInfo untouched when no matching rewrite exists', function () {
        $request = makeFrontendRequest('/no-such-rewrite-' . uniqid() . '.html');
        $original = $request->getPathInfo();
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteDb($request, $response);

        expect($request->getPathInfo())->toBe($original);
        expect($response->isRedirect())->toBeFalse();
    });

    it('skips rewrite processing entirely when request isStraight() is true', function () {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $write->insert($table, [
            'store_id'     => $this->storeId,
            'id_path'      => 'test_rewrite_straight_' . uniqid(),
            'request_path' => 'should-not-rewrite.html',
            'target_path'  => 'catalog/product/view/id/456',
            'is_system'    => 0,
        ]);
        $this->insertedIds[] = (int) $write->lastInsertId();

        $request = makeFrontendRequest('/should-not-rewrite.html');
        $request->isStraight(true);
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteDb($request, $response);

        expect($request->getPathInfo())->toBe('/should-not-rewrite.html');
    });

    it('preserves query string params across the rewrite', function () {
        // After a DB rewrite fires, the target_path becomes the new pathInfo
        // but the original query string must remain accessible via getParam().
        // Without this, code like getParam('utm_source') on a rewritten URL
        // would silently drop tracking data.
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $write->insert($table, [
            'store_id'     => $this->storeId,
            'id_path'      => 'test_rewrite_qs_' . uniqid(),
            'request_path' => 'promo-page.html',
            'target_path'  => 'catalog/product/view/id/999',
            'is_system'    => 0,
        ]);
        $this->insertedIds[] = (int) $write->lastInsertId();

        $symfonyRequest = SymfonyRequest::create('/promo-page.html?utm_source=newsletter&ref=42');
        $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
        $request->setPathInfo('/promo-page.html');
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteDb($request, $response);

        expect($request->getPathInfo())->toBe('catalog/product/view/id/999');
        expect($request->getParam('utm_source'))->toBe('newsletter');
        expect($request->getParam('ref'))->toBe('42');
    });

    it('loads the rewrite via loadByRequestPath exactly as the observer does', function () {
        // This asserts the Mage_Core_Model_Url_Rewrite lookup primitive used
        // by the observer is still functional post-migration.
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        $write->insert($table, [
            'store_id'     => $this->storeId,
            'id_path'      => 'test_rewrite_lookup_' . uniqid(),
            'request_path' => 'lookup-probe.html',
            'target_path'  => 'catalog/product/view/id/1',
            'is_system'    => 0,
        ]);
        $this->insertedIds[] = (int) $write->lastInsertId();

        /** @var Mage_Core_Model_Url_Rewrite $rewrite */
        $rewrite = Mage::getModel('core/url_rewrite');
        $rewrite->setStoreId($this->storeId)->loadByRequestPath(['lookup-probe.html']);

        expect($rewrite->getId())->toBeGreaterThan(0);
        expect($rewrite->getTargetPath())->toBe('catalog/product/view/id/1');
    });
});
