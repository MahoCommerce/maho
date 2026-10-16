<?php

/**
 * The documents under /.well-known that tell an agent which APIs this install serves.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Discovery
{
    public const PATH_API_CATALOG = '.well-known/api-catalog';
    public const PATH_AI_CATALOG = '.well-known/ai-catalog.json';
    public const PATH_SERVER_CARD = '.well-known/mcp.json';
    public const PATH_PROTECTED_RESOURCE = '.well-known/oauth-protected-resource';
    public const PATH_AUTHORIZATION_SERVER = '.well-known/oauth-authorization-server';

    public const TYPE_LINKSET = 'application/linkset+json';
    public const TYPE_JSON = 'application/json';
    public const TYPE_MCP_SERVER_CARD = 'application/mcp-server-card+json';

    public const AI_CATALOG_SPEC_VERSION = '1.0';

    /** The registry schema the server card is written against. */
    public const SERVER_CARD_SCHEMA = 'https://static.modelcontextprotocol.io/schemas/2025-10-17/server.schema.json';

    /** ServerDetail.description and ServerDetail.title are capped at 100 characters. */
    protected const CARD_TEXT_LIMIT = 100;

    /**
     * RFC 9727: one linkset context per API, anchored at the entry point a client would call,
     * carrying the links that describe it. Only enabled protocols appear.
     *
     * @return array<string, mixed>
     */
    public function getApiCatalog(): array
    {
        $helper = $this->helper();
        $root = $helper->getRequestRoot() . '/';
        $contexts = [];

        if ($helper->isProtocolEnabled(Maho_ApiPlatform_Helper_Data::PROTOCOL_REST_V2)) {
            $contexts[] = [
                'anchor' => $root . 'api/rest/v2',
                'service-desc' => [
                    [
                        'href' => $root . 'api/docs.json',
                        'type' => 'application/vnd.oai.openapi+json',
                        'title' => 'OpenAPI description',
                    ],
                ],
                'service-doc' => [
                    [
                        'href' => $root . 'api/docs',
                        'type' => 'text/html',
                        'title' => 'REST API documentation',
                    ],
                ],
            ];
        }

        if ($helper->isProtocolEnabled(Maho_ApiPlatform_Helper_Data::PROTOCOL_GRAPHQL)) {
            $contexts[] = [
                'anchor' => $root . 'api/graphql',
                'service-doc' => [
                    [
                        'href' => $root . 'api/docs',
                        'type' => 'text/html',
                        'title' => 'GraphQL API documentation',
                    ],
                ],
            ];
        }

        if ($helper->isMcpEnabled()) {
            $contexts[] = [
                'anchor' => $root . 'api/mcp',
                'service-desc' => [
                    [
                        'href' => $root . self::PATH_SERVER_CARD,
                        'type' => self::TYPE_JSON,
                        'title' => 'MCP server card',
                    ],
                ],
            ];
        }

        return ['linkset' => $contexts];
    }

    /**
     * The MCP server card, in the shape of the registry's server.json. The well-known path it is
     * served at is still a draft (SEP-2127), the document itself is not.
     *
     * @return array<string, mixed>
     */
    public function getServerCard(): array
    {
        $helper = $this->helper();
        $root = $helper->getRequestRoot() . '/';
        $storeName = $this->getStoreName();

        return [
            '$schema' => self::SERVER_CARD_SCHEMA,
            'name' => $this->getServerName($root),
            'title' => $this->truncate($storeName),
            'description' => $this->truncate($helper->__('Catalog, cart and order tools for %s', $storeName)),
            'version' => Mage::getVersion(),
            'websiteUrl' => $root,
            'remotes' => [
                [
                    'type' => 'streamable-http',
                    'url' => $root . 'api/mcp',
                ],
            ],
        ];
    }

    /**
     * Agentic Resource Discovery: the AI catalog that lists the MCP server card. Lighthouse checks
     * it with the ARD conformance test, which requires `displayName` on each entry and expects
     * 2 to 5 `representativeQueries`.
     *
     * @return array<string, mixed>
     */
    public function getAiCatalog(): array
    {
        $helper = $this->helper();
        $root = $helper->getRequestRoot() . '/';
        $storeName = $this->getStoreName();
        $entries = [];

        if ($helper->isMcpEnabled()) {
            $entries[] = [
                'identifier' => 'urn:air:' . $this->getPublisher($root) . ':mcp:maho',
                'displayName' => $storeName,
                'type' => self::TYPE_MCP_SERVER_CARD,
                'url' => $root . self::PATH_SERVER_CARD,
                'description' => $helper->__('Catalog, cart and order tools for %s', $storeName),
                'tags' => ['ecommerce', 'catalog', 'cart', 'orders'],
                'representativeQueries' => [
                    $helper->__('Find a product in %s', $storeName),
                    $helper->__('Check the price and the stock of a product'),
                    $helper->__('Add a product to my cart'),
                    $helper->__('Check the status of my order'),
                ],
                'version' => Mage::getVersion(),
            ];
        }

        return [
            'specVersion' => self::AI_CATALOG_SPEC_VERSION,
            'host' => ['displayName' => $storeName],
            'entries' => $entries,
        ];
    }

    /**
     * RFC 9728. `authorization_servers` appears only when the authorization server is enabled:
     * naming one that does not answer sends a conformant client down a path that cannot finish.
     * With it off, tokens still come from the JSON endpoint at /auth/token, which is not an
     * RFC 6749 authorization server and must not be advertised as one.
     *
     * `$resource` names the resource this document describes, and defaults to the host root. It
     * must be one of the canonical identifiers a token can be bound to, because a client copies
     * it into the `resource` parameter of the authorization request.
     *
     * @return array<string, mixed>
     */
    public function getProtectedResourceMetadata(?string $resource = null): array
    {
        $helper = $this->helper();
        $root = $helper->getRequestRoot();

        $metadata = [
            'resource' => $resource ?? $root,
            'resource_name' => $this->getStoreName(),
            'bearer_methods_supported' => ['header'],
        ];

        if ($helper->isAuthorizationServerEnabled()) {
            $metadata['authorization_servers'] = [$root];
            $metadata['scopes_supported'] = Maho_ApiPlatform_Model_Oauth_Server::SUPPORTED_SCOPES;
        }

        if ($helper->isProtocolEnabled(Maho_ApiPlatform_Helper_Data::PROTOCOL_REST_V2)) {
            $metadata['resource_documentation'] = $root . '/api/docs';
        }

        return $metadata;
    }

    /**
     * RFC 8414. The issuer is the site root, so the metadata path is exactly
     * /.well-known/oauth-authorization-server, which is where a client looks first.
     *
     * `authorization_endpoint` is the neutral /api/oauth/authorize alias, never the admin URL:
     * this document is public, and the admin path is not ours to publish.
     *
     * @return array<string, mixed>
     */
    public function getAuthorizationServerMetadata(): array
    {
        $helper = $this->helper();
        $root = $helper->getRequestRoot();

        $metadata = [
            'issuer' => $root,
            'authorization_endpoint' => $root . '/api/oauth/authorize',
            'token_endpoint' => $root . '/api/oauth/token',
            'response_types_supported' => [Maho_ApiPlatform_Model_Oauth_Server::RESPONSE_TYPE_CODE],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => [
                Maho_ApiPlatform_Model_Oauth_Client::GRANT_AUTHORIZATION_CODE,
                Maho_ApiPlatform_Model_Oauth_Client::GRANT_REFRESH_TOKEN,
            ],
            // S256 only: `plain` gives an intercepted code no protection at all.
            'code_challenge_methods_supported' => [Maho_ApiPlatform_Model_Oauth_Token::CHALLENGE_METHOD_S256],
            'token_endpoint_auth_methods_supported' => [
                Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_NONE,
                Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_CLIENT_SECRET_POST,
                Maho_ApiPlatform_Model_Oauth_Client::AUTH_METHOD_CLIENT_SECRET_BASIC,
            ],
            'scopes_supported' => Maho_ApiPlatform_Model_Oauth_Server::SUPPORTED_SCOPES,
            'resource_indicators_supported' => true,
        ];

        if ($helper->isDynamicRegistrationEnabled()) {
            $metadata['registration_endpoint'] = $root . '/api/oauth/register';
        }

        return $metadata;
    }

    /**
     * Reverse-DNS of the domain, the format the registry requires: shop.example.com becomes
     * com.example.shop/maho.
     */
    public function getServerName(string $root): string
    {
        $host = (string) parse_url($root, PHP_URL_HOST);
        $labels = array_reverse(array_filter(explode('.', $host)));
        $namespace = (string) preg_replace('/[^a-zA-Z0-9.-]/', '', implode('.', $labels));

        return ($namespace === '' ? 'localhost' : $namespace) . '/maho';
    }

    /**
     * The publisher segment of an `urn:air:` identifier: the host name, without the port.
     */
    public function getPublisher(string $root): string
    {
        $host = (string) preg_replace('/[^a-zA-Z0-9.-]/', '', (string) parse_url($root, PHP_URL_HOST));

        return trim($host, '.') === '' ? 'localhost' : $host;
    }

    protected function getStoreName(): string
    {
        $store = Mage::app()->getStore();
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $store->getFrontendName()));

        return $name === '' ? (string) $store->getName() : $name;
    }

    protected function truncate(string $text): string
    {
        return mb_substr($text, 0, self::CARD_TEXT_LIMIT);
    }

    protected function helper(): Maho_ApiPlatform_Helper_Data
    {
        /** @var Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('apiplatform');
        return $helper;
    }
}
