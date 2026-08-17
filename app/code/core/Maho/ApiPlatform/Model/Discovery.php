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
    public const PATH_SERVER_CARD = '.well-known/mcp.json';
    public const PATH_PROTECTED_RESOURCE = '.well-known/oauth-protected-resource';

    public const TYPE_LINKSET = 'application/linkset+json';
    public const TYPE_JSON = 'application/json';

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
        $root = $helper->getRootUrl();
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
        $root = $helper->getRootUrl();
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
     * RFC 9728. No authorization_servers: tokens come from the API's own endpoint, which speaks
     * JSON rather than RFC 6749, so pointing a client at it as an OAuth server would mislead it.
     *
     * @return array<string, mixed>
     */
    public function getProtectedResourceMetadata(): array
    {
        $helper = $this->helper();
        $root = $helper->getRootUrl();

        $metadata = [
            'resource' => $root,
            'resource_name' => $this->getStoreName(),
            'bearer_methods_supported' => ['header'],
        ];

        if ($helper->isProtocolEnabled(Maho_ApiPlatform_Helper_Data::PROTOCOL_REST_V2)) {
            $metadata['resource_documentation'] = $root . 'api/docs';
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
