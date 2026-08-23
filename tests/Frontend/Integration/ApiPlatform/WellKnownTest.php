<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * Coverage for the /.well-known documents.
 *
 * Every protocol is off by default and its entry point answers 404, so a document that named a
 * disabled protocol would send an agent to a dead URL.
 */
function discoveryModel(): Maho_ApiPlatform_Model_Discovery
{
    /** @var Maho_ApiPlatform_Model_Discovery $model */
    $model = Mage::getSingleton('apiplatform/discovery');
    return $model;
}

function apiHelper(): Maho_ApiPlatform_Helper_Data
{
    /** @var Maho_ApiPlatform_Helper_Data $helper */
    $helper = Mage::helper('apiplatform');
    return $helper;
}

function configureProtocols(array $values = []): void
{
    $store = Mage::app()->getStore();
    $defaults = [
        'apiplatform/protocols/rest_v2' => '0',
        'apiplatform/protocols/graphql' => '0',
        'apiplatform/protocols/mcp' => '0',
    ];
    foreach ([...$defaults, ...$values] as $path => $value) {
        $store->setConfig($path, $value);
    }
}

function wellKnownResponse(string $action): Mage_Core_Controller_Response_Http
{
    $response = new Mage_Core_Controller_Response_Http();
    $controller = new Maho_ApiPlatform_WellKnownController(Mage::app()->getRequest(), $response);
    $controller->$action();

    return $response;
}

function decodedBody(Mage_Core_Controller_Response_Http $response): array
{
    return (array) Mage::helper('core')->jsonDecode($response->getBody());
}

beforeEach(function () {
    configureProtocols();
});

describe('api catalog', function () {
    test('answers 404 while no API is served', function () {
        $response = wellKnownResponse('apiCatalogAction');

        expect($response->getHttpResponseCode())->toBe(404);
        expect($response->getBody())->toBe('');
    });

    test('anchors each enabled protocol at the URL a client calls', function () {
        configureProtocols(['apiplatform/protocols/rest_v2' => '1', 'apiplatform/protocols/graphql' => '1']);
        $root = apiHelper()->getRequestRoot() . '/';

        $anchors = array_column(discoveryModel()->getApiCatalog()['linkset'], 'anchor');

        expect($anchors)->toBe([$root . 'api/rest/v2', $root . 'api/graphql']);
    });

    test('a disabled protocol is left out', function () {
        configureProtocols(['apiplatform/protocols/graphql' => '1']);

        $anchors = array_column(discoveryModel()->getApiCatalog()['linkset'], 'anchor');

        expect($anchors)->toHaveCount(1);
        expect($anchors[0])->toEndWith('api/graphql');
    });

    test('REST points at both the OpenAPI description and the human docs', function () {
        configureProtocols(['apiplatform/protocols/rest_v2' => '1']);
        $context = discoveryModel()->getApiCatalog()['linkset'][0];

        expect($context['service-desc'][0]['href'])->toEndWith('api/docs.json');
        expect($context['service-desc'][0]['type'])->toBe('application/vnd.oai.openapi+json');
        expect($context['service-doc'][0]['href'])->toEndWith('api/docs');
    });

    test('is served as a linkset', function () {
        configureProtocols(['apiplatform/protocols/rest_v2' => '1']);
        $response = wellKnownResponse('apiCatalogAction');

        expect($response->getHttpResponseCode())->toBe(200);
        expect(decodedBody($response))->toHaveKey('linkset');

        $headers = [];
        foreach ($response->getHeaders() as $header) {
            $headers[strtolower($header['name'])] = $header['value'];
        }
        expect($headers['content-type'])->toBe('application/linkset+json; charset=UTF-8');
    });
});

describe('mcp server card', function () {
    test('answers 404 while MCP is off', function () {
        expect(wellKnownResponse('mcpAction')->getHttpResponseCode())->toBe(404);
    });

    test('carries what the registry schema requires', function () {
        $card = discoveryModel()->getServerCard();

        expect($card['name'])->toMatch('#^[a-zA-Z0-9.-]+/[a-zA-Z0-9._-]+$#');
        expect(mb_strlen($card['description']))->toBeGreaterThan(0)->toBeLessThanOrEqual(100);
        expect(mb_strlen($card['title']))->toBeLessThanOrEqual(100);
        expect($card['version'])->toBe(Mage::getVersion());
        expect($card['remotes'][0])->toBe([
            'type' => 'streamable-http',
            'url' => apiHelper()->getRequestRoot() . '/api/mcp',
        ]);
    });

    test('the name is the domain read backwards', function () {
        expect(discoveryModel()->getServerName('https://shop.example.com/'))->toBe('com.example.shop/maho');
    });

    test('a URL with no host still yields a valid name', function () {
        expect(discoveryModel()->getServerName('/'))->toBe('localhost/maho');
    });
});

describe('protected resource metadata', function () {
    test('answers 404 while no API is served', function () {
        expect(wellKnownResponse('oauthProtectedResourceAction')->getHttpResponseCode())->toBe(404);
    });

    test('names the resource and how to authenticate against it', function () {
        configureProtocols(['apiplatform/protocols/graphql' => '1']);
        $metadata = discoveryModel()->getProtectedResourceMetadata();

        // Canonical form, no trailing slash: a client copies this string into the `resource`
        // parameter, and the token audience it gets back must be the same string.
        expect($metadata['resource'])->toBe(apiHelper()->getRequestRoot());
        expect($metadata['resource'])->toBeIn(apiHelper()->getCanonicalResources());
        expect($metadata['bearer_methods_supported'])->toBe(['header']);
        // /api/docs is served by the REST protocol, so it is only mentioned when that is on.
        expect($metadata)->not->toHaveKey('resource_documentation');

        configureProtocols(['apiplatform/protocols/rest_v2' => '1']);
        expect(discoveryModel()->getProtectedResourceMetadata()['resource_documentation'])->toEndWith('api/docs');
    });

    test('claims no authorization server, since the token endpoint is not one', function () {
        configureProtocols(['apiplatform/protocols/rest_v2' => '1']);

        expect(discoveryModel()->getProtectedResourceMetadata())->not->toHaveKey('authorization_servers');
    });

    test('the 401 challenge points at it', function () {
        expect(apiHelper()->getBearerChallenge())->toBe(
            'Bearer resource_metadata="' . apiHelper()->getRequestRoot() . '/.well-known/oauth-protected-resource"',
        );
    });

    test('answers 404 for the MCP resource while MCP is off', function () {
        expect(wellKnownResponse('oauthProtectedResourceMcpAction')->getHttpResponseCode())->toBe(404);
    });

    test('describes /api/mcp as a resource of its own', function () {
        configureProtocols(['apiplatform/protocols/mcp' => '1']);
        $metadata = decodedBody(wellKnownResponse('oauthProtectedResourceMcpAction'));

        // RFC 9728 puts this document at the resource path below the well-known prefix, so a
        // client that holds only the MCP identifier finds it without being told where it is.
        $expected = apiHelper()->getRequestRoot() . Maho_ApiPlatform_Helper_Data::MCP_PATH;
        expect($metadata['resource'])->toBe($expected);
        expect($metadata['resource'])->toBeIn(apiHelper()->getCanonicalResources());
    });

    test('a challenge raised at /api/mcp points at the MCP document', function () {
        // The root document names the host, so a client that read it would ask for a token whose
        // audience does not cover the endpoint that refused it.
        expect(apiHelper()->getBearerChallenge(Maho_ApiPlatform_Helper_Data::MCP_PATH))->toBe(
            'Bearer resource_metadata="' . apiHelper()->getRequestRoot() . '/'
                . '.well-known/oauth-protected-resource/api/mcp"',
        );
    });
});

describe('authorization server metadata', function () {
    test('the token issuer is the string it publishes', function () {
        $published = discoveryModel()->getAuthorizationServerMetadata()['issuer'];

        // A client compares the `iss` claim against this string character by character, so a
        // trailing slash on either side rejects every token.
        expect((new \Maho\ApiPlatform\Service\JwtService())->getIssuer())->toBe($published);
        expect($published)->not->toEndWith('/');
    });

    /**
     * Regression: the issuer read the default store's secure base URL while discovery and the
     * resource identifiers read the current store over the current scheme. On any install whose
     * base URLs are not all the same string, the published issuer disagreed with the signed
     * `iss`, and a token minted over one scheme was refused over the other.
     *
     * RFC 8414 s3.3 and RFC 9728 s3.3 make the client reject a document whose `issuer` or
     * `resource` is not the origin it fetched the document from, so the published identity has
     * to follow the request. Verification is what widens instead.
     */
    test('what a document publishes is the origin it was served from', function () {
        $helper = apiHelper();
        $root = $helper->getRequestRoot();

        expect($root)->not->toEndWith('/');
        expect(discoveryModel()->getAuthorizationServerMetadata()['issuer'])->toBe($root);
        expect(discoveryModel()->getProtectedResourceMetadata()['resource'])->toBe($root);
        expect((new \Maho\ApiPlatform\Service\JwtService())->getIssuer())->toBe($root);
        expect((new \Maho\ApiPlatform\Service\JwtService())->getApiAudience())->toBe($root);
    });

    test('verification accepts every origin the install answers on', function () {
        $stores = Mage::app()->getStores(true);
        $saved = [];
        foreach ($stores as $store) {
            $saved[$store->getId()] = [
                $store->getConfig('web/secure/base_url'),
                $store->getConfig('web/unsecure/base_url'),
            ];
            $store->setConfig('web/secure/base_url', 'https://shop.example/');
            $store->setConfig('web/unsecure/base_url', 'http://shop.example/');
        }

        try {
            $jwt = new \Maho\ApiPlatform\Service\JwtService();

            // A token minted over either scheme has to validate on a request that arrives over
            // the other one. Pinning issuance to one scheme is what broke this before.
            expect($jwt->getPermittedIssuers())
                ->toContain('https://shop.example')
                ->toContain('http://shop.example');

            foreach (['https://shop.example', 'http://shop.example'] as $origin) {
                expect($jwt->getPermittedAudiences())->toContain($origin);
            }

            expect($jwt->getIssuer())->toBeIn($jwt->getPermittedIssuers());
            expect($jwt->getApiAudience())->toBeIn($jwt->getPermittedAudiences());
        } finally {
            foreach ($stores as $store) {
                [$secure, $unsecure] = $saved[$store->getId()];
                $store->setConfig('web/secure/base_url', $secure);
                $store->setConfig('web/unsecure/base_url', $unsecure);
            }
        }
    });
});
