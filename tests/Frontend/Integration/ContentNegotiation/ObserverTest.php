<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

function cnHelper(): Maho_ContentNegotiation_Helper_Data
{
    /** @var Maho_ContentNegotiation_Helper_Data $helper */
    $helper = Mage::helper('contentnegotiation');
    return $helper;
}

function cnObserver(): Maho_ContentNegotiation_Model_Observer
{
    return new Maho_ContentNegotiation_Model_Observer();
}

function cnRequest(string $uri, string $accept = '', string $method = 'GET'): Mage_Core_Controller_Request_Http
{
    $server = $accept === '' ? [] : ['HTTP_ACCEPT' => $accept];
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create($uri, $method, [], [], [], $server));
    Mage::app()->setRequest($request);
    return $request;
}

function cnRoute(Mage_Core_Controller_Request_Http $request, string $route): Mage_Core_Controller_Request_Http
{
    [$module, $controller, $action] = explode('/', $route);
    return $request->setPathInfo()->setModuleName($module)->setControllerName($controller)->setActionName($action);
}

function cnResponse(string $body = '<html><body>page</body></html>'): Mage_Core_Controller_Response_Http
{
    $response = new Mage_Core_Controller_Response_Http();
    $response->setBody($body);
    Mage::app()->setResponse($response);
    return $response;
}

/**
 * @return array<string, string[]>
 */
function cnHeaders(Mage_Core_Controller_Response_Http $response): array
{
    $headers = [];
    foreach ($response->getHeaders() as $header) {
        $headers[strtolower($header['name'])][] = $header['value'];
    }
    return $headers;
}

function cnRegisterProduct(): Mage_Catalog_Model_Product
{
    $product = loadSimplePricedProduct();
    Mage::register('current_product', $product);
    Mage::register('product', $product);
    return $product;
}

beforeEach(function () {
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register('symfony_session', $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());

    $store = Mage::app()->getStore();
    $store->setConfig(Maho_ContentNegotiation_Helper_Data::XML_PATH_ENABLED, '1');
    $store->setConfig('web/url/trailing_slash_behavior', Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash::ADD_TRAILING_SLASH);
    $store->setConfig('web/url/use_store', '0');
});

describe('suffix strip', function () {
    test('removes .md and makes the path canonical', function () {
        $request = cnRequest('/some-category.md');
        cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());

        expect($request->getRequestUri())->toBe('/some-category/');
        expect(cnHelper()->wasSuffixStripped())->toBeTrue();
        expect(cnHelper()->isMarkdownRequest($request))->toBeTrue();
    });

    test('keeps a file extension and the query string', function () {
        $request = cnRequest('/blue-shirt.html.md?p=2');
        cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());

        expect($request->getRequestUri())->toBe('/blue-shirt.html?p=2');
        expect($request->setPathInfo()->getOriginalPathInfo())->toBe('/blue-shirt.html');
    });

    test('maps index.md to the root page', function () {
        foreach (['/index.md' => '/', '/index.md?p=2' => '/?p=2', '/index.php/index.md' => '/index.php/'] as $uri => $expected) {
            $request = cnRequest($uri);
            cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());

            expect($request->getRequestUri())->toBe($expected);
            expect(cnHelper()->wasSuffixStripped())->toBeTrue();
        }
    });

    test('keeps a page whose URL key is index', function () {
        foreach (['/help/index.md' => '/help/index/', '/index.php/help/index.md' => '/index.php/help/index/'] as $uri => $expected) {
            $request = cnRequest($uri);
            cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());

            expect($request->getRequestUri())->toBe($expected);
        }
    });

    test('ignores a POST, a hidden file name and a URL without the suffix', function () {
        foreach ([['/foo.md', 'POST'], ['/.md', 'GET'], ['/foo', 'GET']] as [$uri, $method]) {
            $request = cnRequest($uri, '', $method);
            cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());

            expect($request->getRequestUri())->toBe($uri);
            expect(cnHelper()->wasSuffixStripped())->toBeFalse();
        }
    });

    test('does nothing when the feature is disabled', function () {
        Mage::app()->getStore()->setConfig(Maho_ContentNegotiation_Helper_Data::XML_PATH_ENABLED, '0');
        $request = cnRequest('/foo.md');
        cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());

        expect($request->getRequestUri())->toBe('/foo.md');
    });
});

describe('html response', function () {
    test('announces the markdown version on an allowed route', function () {
        $request = cnRoute(cnRequest('/blue-shirt.html'), 'catalog/product/view');
        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        $headers = cnHeaders($response);
        expect($response->getBody())->toBe('<html><body>page</body></html>');
        expect($headers['vary'])->toBe(['Accept']);
        expect($headers['link'][0])->toEndWith('/blue-shirt.html.md>; rel="alternate"; type="text/markdown"');
        expect(cnHelper()->getMarkdownUrl($request))->not->toContain('/.md');
    });

    test('drops the trailing slash before the suffix', function () {
        $request = cnRoute(cnRequest('/some-category/'), 'catalog/category/view');

        expect(cnHelper()->getMarkdownUrl($request))->toEndWith('/some-category.md');
    });

    test('drops the query string from the link', function () {
        cnRoute(cnRequest('/some-category/?p=2&order=name'), 'catalog/category/view');
        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect(cnHeaders($response)['link'][0])->toStartWith('<')
            ->toContain('/some-category.md>; rel="alternate"');
    });

    test('adds no link on a route without a renderer', function () {
        Mage::app()->getStore()->setConfig(Maho_ContentNegotiation_Helper_Data::XML_PATH_ALLOWED_ROUTES, 'customer/account/index');
        cnRoute(cnRequest('/customer/account/'), 'customer/account/index');
        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect(cnHeaders($response))->not->toHaveKey('link')->not->toHaveKey('vary');
    });

    test('keeps the suffix on a redirect of a suffixed request', function () {
        $base = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        cnRequest('/index.php/about-us.md');
        cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());
        $response = cnResponse()->setRedirectUrl($base . 'about-us/')->setHttpResponseCode(301);
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect(cnHeaders($response)['location'])->toBe([$base . 'about-us.md']);

        $response = cnResponse()->setRedirectUrl('https://elsewhere.test/about-us/')->setHttpResponseCode(301);
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect(cnHeaders($response)['location'])->toBe(['https://elsewhere.test/about-us/']);
    });

    test('keeps the suffix on the relative redirect of the canonical URL check', function () {
        cnRequest('/index.php/about-us.md');
        cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());
        foreach (['/about-us/' => '/about-us.md', '/' => '/index.md', '//evil.test/' => '//evil.test/'] as $location => $expected) {
            $response = cnResponse()->setRedirectUrl($location)->setHttpResponseCode(301);
            cnObserver()->negotiateResponse(new \Maho\Event\Observer());

            expect(cnHeaders($response)['location'])->toBe([$expected]);
        }
    });

    test('links index.md on the root page', function () {
        $base = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        cnRoute(cnRequest('/'), 'cms/index/index');
        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        $headers = cnHeaders($response);
        expect($headers['vary'])->toBe(['Accept']);
        expect($headers['link'][0])->toStartWith('<' . $base . 'index.md>');
        expect(cnHelper()->toMarkdownUrl($base))->toBe($base . 'index.md');
        expect(cnHelper()->toMarkdownUrl(rtrim($base, '/') . '?p=2'))->toBe($base . 'index.md');
    });

    test('leaves other routes alone', function () {
        cnRoute(cnRequest('/customer/account/', 'text/markdown'), 'customer/account/index');
        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect($response->getBody())->toBe('<html><body>page</body></html>');
        expect(cnHeaders($response))->not->toHaveKey('vary');
    });

    test('leaves a non-200 response alone', function () {
        cnRoute(cnRequest('/blue-shirt.html', 'text/markdown'), 'catalog/product/view');
        $response = cnResponse()->setHttpResponseCode(404);
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect($response->getBody())->toBe('<html><body>page</body></html>');
    });
});

describe('markdown response', function () {
    test('replaces the body and sets the headers', function () {
        $product = cnRegisterProduct();
        cnRoute(cnRequest('/blue-shirt.html', 'text/markdown'), 'catalog/product/view');
        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        $headers = cnHeaders($response);
        expect($headers['content-type'])->toBe(['text/markdown; charset=UTF-8']);
        expect($headers['vary'])->toBe(['Accept']);
        expect($headers['x-robots-tag'])->toBe(['noindex']);
        expect($response->getBody())->toStartWith('# ' . $product->getName());
        expect($response->getBody())->toContain('- SKU: ' . $product->getSku());
    });

    test('serves the cached markdown before the action runs', function () {
        if (!Mage::app()->useCache(Mage_Core_Block_Abstract::CACHE_GROUP)) {
            $this->markTestSkipped('The block_html cache is disabled.');
        }

        cnRegisterProduct();
        $request = cnRoute(cnRequest('/blue-shirt.html', 'text/markdown'), 'catalog/product/view');
        $cacheId = cnHelper()->getCacheId($request);
        Mage::app()->removeCache($cacheId);

        try {
            $response = cnResponse();
            cnObserver()->negotiateResponse(new \Maho\Event\Observer());
            $markdown = $response->getBody();
            expect(Mage::app()->loadCache($cacheId))->toBe($markdown);

            $cached = new Mage_Core_Controller_Response_Http();
            $controller = new Mage_Catalog_ProductController($request, $cached);
            cnObserver()->serveCachedMarkdown(new \Maho\Event\Observer(['controller_action' => $controller]));

            expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeTrue();
            expect($cached->getBody())->toBe($markdown);
            expect(cnHeaders($cached)['content-type'])->toBe(['text/markdown; charset=UTF-8']);
        } finally {
            Mage::app()->removeCache($cacheId);
        }
    });

    test('does not cache when the lifetime is zero', function () {
        if (!Mage::app()->useCache(Mage_Core_Block_Abstract::CACHE_GROUP)) {
            $this->markTestSkipped('The block_html cache is disabled.');
        }

        Mage::app()->getStore()->setConfig(Maho_ContentNegotiation_Helper_Data::XML_PATH_CACHE_LIFETIME, '0');
        cnRegisterProduct();
        $request = cnRoute(cnRequest('/blue-shirt.html', 'text/markdown'), 'catalog/product/view');
        $cacheId = cnHelper()->getCacheId($request);
        Mage::app()->removeCache($cacheId);

        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect(cnHeaders($response)['content-type'])->toBe(['text/markdown; charset=UTF-8']);
        expect(Mage::app()->loadCache($cacheId))->toBeFalse();
    });

    test('keys the cache by path, whatever the query string or the request form', function () {
        $plain = cnHelper()->getCacheId(cnRequest('/some-category/'));
        expect(cnHelper()->getCacheId(cnRequest('/some-category/?p=2&utm_source=x')))->toBe($plain);
        expect(cnHelper()->getCacheId(cnRequest('/other-category/')))->not->toBe($plain);

        cnRequest('/some-category.md');
        cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());
        expect(cnHelper()->getCacheId(Mage::app()->getRequest()))->toBe($plain);
    });

    test('answers 404 to a suffixed request on a route without markdown', function () {
        foreach (['customer/account/index', 'catalog/product/view'] as $route) {
            cnRequest('/some-page.md');
            cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());
            cnRoute(Mage::app()->getRequest(), $route);
            $response = cnResponse();
            cnObserver()->negotiateResponse(new \Maho\Event\Observer());

            expect($response->getHttpResponseCode())->toBe(404);
            expect(cnHeaders($response)['content-type'])->toBe(['text/markdown; charset=UTF-8']);
            expect($response->getBody())->toStartWith('# Not Found');
        }
    });

    test('leaves a suffixed request alone when the page is not 200', function () {
        cnRequest('/missing.md');
        cnObserver()->stripMarkdownSuffix(new \Maho\Event\Observer());
        cnRoute(Mage::app()->getRequest(), 'cms/index/noRoute');
        $response = cnResponse()->setHttpResponseCode(404);
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect($response->getBody())->toBe('<html><body>page</body></html>');
    });

    test('keeps the html when the page has nothing to render', function () {
        cnRoute(cnRequest('/blue-shirt.html', 'text/markdown'), 'catalog/product/view');
        $response = cnResponse();
        cnObserver()->negotiateResponse(new \Maho\Event\Observer());

        expect($response->getBody())->toBe('<html><body>page</body></html>');
        expect(cnHeaders($response))->not->toHaveKey('content-type');
    });
});

describe('resolver', function () {
    test('reads the renderers from config', function () {
        /** @var Maho_ContentNegotiation_Model_Resolver $resolver */
        $resolver = Mage::getSingleton('contentnegotiation/resolver');

        expect($resolver->getRenderers())->toHaveKey('catalog/product/view', 'contentnegotiation/renderer_product');
        expect($resolver->resolve('catalog/product/view'))->toBeInstanceOf(Maho_ContentNegotiation_Model_Renderer_Product::class);
        expect($resolver->resolve('cms/index/index'))->toBeInstanceOf(Maho_ContentNegotiation_Model_Renderer_Page::class);
        expect($resolver->resolve('customer/account/index'))->toBeNull();
        expect($resolver->hasRenderer('blog/index/category'))->toBeTrue();
    });

    test('tells whether a route has a markdown version', function () {
        expect(cnHelper()->hasMarkdown('cms/page/view'))->toBeTrue();
        expect(cnHelper()->hasMarkdown('customer/account/index'))->toBeFalse();

        Mage::app()->getStore()->setConfig(Maho_ContentNegotiation_Helper_Data::XML_PATH_ALLOWED_ROUTES, 'catalog/product/view');
        expect(cnHelper()->hasMarkdown('cms/page/view'))->toBeFalse();
    });
});
