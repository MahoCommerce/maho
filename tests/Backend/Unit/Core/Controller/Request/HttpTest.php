<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

describe('Mage_Core_Controller_Request_Http', function () {
    beforeEach(function () {
        // Create a Symfony Request with test data
        $this->symfonyRequest = SymfonyRequest::create(
            '/test/path',
            'GET',
            ['query_param' => 'query_value'],
            ['COOKIE_NAME' => 'cookie_value'],
            [],
            [
                'SERVER_NAME' => 'test.example.com',
                'SERVER_PORT' => 80,
                'HTTP_HOST' => 'test.example.com',
                'REQUEST_URI' => '/test/path?query_param=query_value',
                'HTTP_X_FORWARDED_FOR' => '192.168.1.1',
            ],
            'request body content',
        );

        // Add POST data
        $this->symfonyRequest->request->set('post_param', 'post_value');

        // Create Maho Request wrapper
        $this->request = new Mage_Core_Controller_Request_Http($this->symfonyRequest);
    });

    describe('Parameter Handling', function () {
        it('retrieves parameters from internal params first', function () {
            $this->request->setParam('test_key', 'internal_value');
            $this->symfonyRequest->query->set('test_key', 'query_value');

            expect($this->request->getParam('test_key'))->toBe('internal_value');
        });

        it('falls back to POST parameters when internal param not found', function () {
            expect($this->request->getParam('post_param'))->toBe('post_value');
        });

        it('falls back to GET parameters when internal and POST params not found', function () {
            expect($this->request->getParam('query_param'))->toBe('query_value');
        });

        it('returns default value when parameter not found anywhere', function () {
            expect($this->request->getParam('nonexistent', 'default'))->toBe('default');
        });

        it('handles null keys gracefully', function () {
            expect($this->request->getParam(null, 'default'))->toBe('default');
        });

        it('handles integer keys properly', function () {
            $this->request->setParam(123, 'numeric_value');
            expect($this->request->getParam(123))->toBe('numeric_value');
        });

        it('getUserParam only returns internal params', function () {
            $this->request->setParam('internal', 'internal_value');
            $this->symfonyRequest->query->set('external', 'external_value');

            expect($this->request->getUserParam('internal'))->toBe('internal_value');
            expect($this->request->getUserParam('external', 'default'))->toBe('default');
        });

        it('getUserParams returns all internal params', function () {
            $this->request->setParam('param1', 'value1');
            $this->request->setParam('param2', 'value2');

            $params = $this->request->getUserParams();
            expect($params)->toHaveKey('param1');
            expect($params)->toHaveKey('param2');
            expect($params['param1'])->toBe('value1');
            expect($params['param2'])->toBe('value2');
        });

        it('setParams merges parameters correctly', function () {
            $this->request->setParam('existing', 'old');
            $this->request->setParams(['new' => 'value', 'existing' => 'updated']);

            expect($this->request->getUserParam('existing'))->toBe('old'); // existing not overwritten
            expect($this->request->getUserParam('new'))->toBe('value');
        });
    });

    describe('Symfony Request Compatibility', function () {
        it('wraps Symfony Request instance properly', function () {
            expect($this->request)->toBeInstanceOf(Mage_Core_Controller_Request_Http::class);
        });

        it('forwards method calls to Symfony Request', function () {
            expect($this->request->getMethod())->toBe('GET');
            expect($this->request->getRequestUri())->toBe('/test/path?query_param=query_value');
            expect($this->request->getHost())->toBe('test.example.com');
        });

        it('handles client IP retrieval', function () {
            $ip = $this->request->getClientIp();
            expect($ip)->toBeString();
            expect($ip)->not->toBeEmpty();
        });

        it('handles secure request detection', function () {
            expect($this->request->isSecure())->toBeFalse();

            // Test with HTTPS
            $httpsRequest = SymfonyRequest::create('https://test.com/');
            $secureRequest = new Mage_Core_Controller_Request_Http($httpsRequest);
            expect($secureRequest->isSecure())->toBeTrue();
        });

        it('handles request body retrieval', function () {
            expect($this->request->getRawBody())->toBe('request body content');
        });
    });

    describe('Path and URL Management', function () {
        it('handles path info correctly', function () {
            $this->request->setPathInfo('/custom/path');
            expect($this->request->getPathInfo())->toBe('/custom/path');
        });

        it('handles base URL correctly', function () {
            $this->request->setBaseUrl('/base');
            expect($this->request->getBaseUrl())->toContain('/base');
        });

        it('handles base path correctly', function () {
            $this->request->setBasePath('/base/path');
            expect($this->request->getBasePath())->toContain('/base/path');
        });

        it('handles request URI correctly', function () {
            $this->request->setRequestUri('/new/uri');
            expect($this->request->getRequestUri())->toBe('/new/uri');
        });

        it('handles original path info', function () {
            $this->request->setOriginalPathInfo('/original/path');
            expect($this->request->getOriginalPathInfo())->toBe('/original/path');
        });
    });

    describe('MVC Components', function () {
        it('handles module name', function () {
            $this->request->setModuleName('test_module');
            expect($this->request->getModuleName())->toBe('test_module');
        });

        it('handles controller name', function () {
            $this->request->setControllerName('test_controller');
            expect($this->request->getControllerName())->toBe('test_controller');
        });

        it('handles action name', function () {
            $this->request->setActionName('test_action');
            expect($this->request->getActionName())->toBe('test_action');
        });

        it('handles routing info', function () {
            $this->request->setRoutingInfo(['route' => 'info']);
            expect($this->request->getRoutingInfo())->toBe(['route' => 'info']);
        });

        it('handles route name', function () {
            $this->request->setRouteName('test_route');
            expect($this->request->getRouteName())->toBe('test_route');
        });
    });

    describe('Request State Management', function () {
        it('handles dispatched flag', function () {
            expect($this->request->isDispatched())->toBeFalse();
            $this->request->setDispatched(true);
            expect($this->request->isDispatched())->toBeTrue();
        });

        it('handles straight request flag', function () {
            expect($this->request->isStraight())->toBeFalse();
            $this->request->setIsStraight(true);
            expect($this->request->isStraight())->toBeTrue();
        });

        it('handles internal forwarding', function () {
            expect($this->request->getInternallyForwarded())->toBeFalse();
            $this->request->setInternallyForwarded(true);
            expect($this->request->getInternallyForwarded())->toBeTrue();
        });

        it('handles before forward info', function () {
            $info = ['module' => 'old', 'controller' => 'old'];
            $this->request->setBeforeForwardInfo($info);
            expect($this->request->getBeforeForwardInfo('module'))->toBe('old');
            expect($this->request->getBeforeForwardInfo())->toBe($info);
        });
    });

    describe('Header Management', function () {
        it('retrieves headers from Symfony Request', function () {
            $this->symfonyRequest->headers->set('X-Custom-Header', 'test-value');
            expect($this->request->getHeader('X-Custom-Header'))->toBe('test-value');
        });

        it('handles non-existent headers', function () {
            expect($this->request->getHeader('Non-Existent-Header'))->toBeFalse();
        });

        it('retrieves all headers', function () {
            $headers = $this->request->getHeaders();
            expect($headers)->toBeArray();
            expect($headers)->toHaveKey('host');
        });
    });

    describe('Cookie Management', function () {
        it('retrieves cookies from Symfony Request', function () {
            expect($this->request->getCookie('COOKIE_NAME'))->toBe('cookie_value');
        });

        it('returns default for non-existent cookies', function () {
            expect($this->request->getCookie('non_existent', 'default'))->toBe('default');
        });
    });

    describe('AJAX Detection', function () {
        it('detects AJAX requests correctly', function () {
            expect($this->request->isAjax())->toBeFalse();

            // Simulate AJAX request
            $ajaxRequest = SymfonyRequest::create('/test', 'GET', [], [], [], [
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $ajaxReq = new Mage_Core_Controller_Request_Http($ajaxRequest);
            expect($ajaxReq->isAjax())->toBeTrue();
        });

        it('handles isXmlHttpRequest alias', function () {
            expect($this->request->isXmlHttpRequest())->toBeFalse();
        });
    });

    describe('Aliases', function () {
        it('handles alias setting and retrieval', function () {
            $this->request->setAlias('myAlias', 'targetValue');
            expect($this->request->getAlias('myAlias'))->toBe('targetValue');
        });

        it('returns null for non-existent aliases', function () {
            expect($this->request->getAlias('nonExistent'))->toBeNull();
        });

        it('retrieves all aliases', function () {
            $this->request->setAlias('alias1', 'value1');
            $this->request->setAlias('alias2', 'value2');

            $aliases = $this->request->getAliases();
            expect($aliases)->toHaveKey('alias1');
            expect($aliases)->toHaveKey('alias2');
        });
    });

    describe('Store Code Management', function () {
        it('handles store code setting and retrieval', function () {
            $this->request->setStoreCodeFromPath('en_us');
            expect($this->request->getStoreCodeFromPath())->toBe('en_us');
        });
    });

    describe('Direct Front Names', function () {
        it('checks direct access frontend names', function () {
            // This would need proper Mage configuration to test fully
            expect($this->request->isDirectAccessFrontendName('test'))->toBeBool();
        });

        it('retrieves direct front names', function () {
            $names = $this->request->getDirectFrontNames();
            expect($names)->toBeArray();
        });
    });

    describe('HTTP Method Detection', function () {
        it('detects GET requests', function () {
            $getRequest = SymfonyRequest::create('/test', 'GET');
            $req = new Mage_Core_Controller_Request_Http($getRequest);
            expect($req->isGet())->toBeTrue();
            expect($req->isPost())->toBeFalse();
        });

        it('detects POST requests', function () {
            $postRequest = SymfonyRequest::create('/test', 'POST');
            $req = new Mage_Core_Controller_Request_Http($postRequest);
            expect($req->isPost())->toBeTrue();
            expect($req->isGet())->toBeFalse();
        });

        it('detects PUT requests', function () {
            $putRequest = SymfonyRequest::create('/test', 'PUT');
            $req = new Mage_Core_Controller_Request_Http($putRequest);
            expect($req->isPut())->toBeTrue();
        });

        it('detects DELETE requests', function () {
            $deleteRequest = SymfonyRequest::create('/test', 'DELETE');
            $req = new Mage_Core_Controller_Request_Http($deleteRequest);
            expect($req->isDelete())->toBeTrue();
        });

        it('detects HEAD requests', function () {
            $headRequest = SymfonyRequest::create('/test', 'HEAD');
            $req = new Mage_Core_Controller_Request_Http($headRequest);
            expect($req->isHead())->toBeTrue();
        });
    });
});
