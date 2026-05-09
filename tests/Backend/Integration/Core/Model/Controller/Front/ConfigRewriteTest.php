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
 * `Mage_Core_Model_Controller_Front_Observer::rewriteConfig` reads
 * `<global><rewrite>` entries from merged config and mutates the request
 * pathInfo via regex. Historically this ran unconditionally, which meant a
 * legitimate frontend rewrite (e.g. remapping a product URL) could stomp on
 * admin or install URLs if the regex happened to match them.
 *
 * The guard now returns early when either:
 *   - Maho is not installed (install flow must be immune to user rewrites), or
 *   - The current store is admin (admin URLs must be immune too).
 *
 * Control path: on a frontend store with app installed, the rewrite DOES
 * apply — proving the guards aren't blocking the happy path.
 *
 * rewriteConfig is private, so we call it via reflection (matches the pattern
 * used by AdminModuleChainTest and UrlRewriteIntegrationTest).
 */

function callRewriteConfig(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
{
    $observer = new Mage_Core_Model_Controller_Front_Observer();
    $ref = new ReflectionMethod($observer, 'rewriteConfig');
    $ref->invoke($observer, $request, $response);
}

function makeRequestForConfigRewrite(string $pathInfo): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
    $request->setPathInfo($pathInfo);
    return $request;
}

function registerGlobalRewrite(string $nodeName, string $from, string $to, bool $complete = false): void
{
    Mage::getConfig()->setNode("global/rewrite/{$nodeName}/from", $from, true);
    Mage::getConfig()->setNode("global/rewrite/{$nodeName}/to", $to, true);
    if ($complete) {
        Mage::getConfig()->setNode("global/rewrite/{$nodeName}/complete", '1', true);
    }
}

function removeGlobalRewrite(string $nodeName): void
{
    $rewriteRoot = Mage::getConfig()->getNode('global/rewrite');
    if (!$rewriteRoot) {
        return;
    }
    // SimpleXML doesn't expose a "remove child by name" helper directly; do it
    // through the underlying DOM to guarantee teardown is clean.
    $dom = dom_import_simplexml($rewriteRoot);
    foreach ($dom->childNodes as $child) {
        if ($child->nodeName === $nodeName) {
            $dom->removeChild($child);
            break;
        }
    }
}

const REWRITE_NODE_NAME = 'maho_test_config_rewrite_probe';

describe('Mage_Core_Model_Controller_Front_Observer::rewriteConfig guards', function () {
    beforeEach(function () {
        // A regex that matches the literal path '/configrewrite-probe' and
        // rewrites it to '/rewritten-target'. Deliberately distinctive so it
        // won't collide with any real config or route. afterEach removes the
        // node between tests, so a fixed name is fine.
        registerGlobalRewrite(
            REWRITE_NODE_NAME,
            '#^/configrewrite-probe$#',
            '/rewritten-target',
        );
    });

    afterEach(function () {
        removeGlobalRewrite(REWRITE_NODE_NAME);
        // Restore default store so later tests aren't accidentally run in admin.
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });

    it('leaves pathInfo untouched when current store is admin', function () {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        expect(Mage::app()->getStore()->isAdmin())->toBeTrue();

        $request  = makeRequestForConfigRewrite('/configrewrite-probe');
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteConfig($request, $response);

        // Without the admin guard, the regex would rewrite this to
        // '/rewritten-target'. With the guard, pathInfo must be preserved.
        expect($request->getPathInfo())->toBe('/configrewrite-probe');
    });

    it('applies the rewrite on a frontend store with the app installed (control)', function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        expect(Mage::app()->getStore()->isAdmin())->toBeFalse();
        expect(Mage::isInstalled())->toBeTrue();

        $request  = makeRequestForConfigRewrite('/configrewrite-probe');
        $response = new Mage_Core_Controller_Response_Http();

        callRewriteConfig($request, $response);

        // Proves the guards aren't blocking the happy path: the rewrite DOES
        // fire on a legit frontend request. Without this control, a broken
        // implementation that always short-circuits would still pass the
        // admin test above.
        expect($request->getPathInfo())->toBe('/rewritten-target');
    });

    // TODO: cover the !Mage::isInstalled() branch. Toggling install state at
    // runtime requires tearing down local.xml or stubbing Mage::isInstalled(),
    // which isn't cleanly expressible in an integration test against the
    // already-installed fixture DB. The guard's boolean shape is symmetric
    // with the admin check, so the admin coverage above + the control case
    // are sufficient to pin the early-return logic itself.
});
