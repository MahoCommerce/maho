<?php

/**
 * Detects markdown requests and resolves their route, URL and cache key.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\AcceptHeader;

class Maho_ContentNegotiation_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'crawlers/markdown/enabled';
    public const XML_PATH_ALLOWED_ROUTES = 'crawlers/markdown/allowed_routes';
    public const XML_PATH_CACHE_LIFETIME = 'crawlers/markdown/cache_lifetime';

    public const MIME_TYPE = 'text/markdown';
    public const SUFFIX = '.md';

    /** The root page cannot take the suffix: the web server rejects "/.md" as a hidden file */
    public const ROOT_FILE = 'index.md';

    protected $_moduleName = 'Maho_ContentNegotiation';

    private bool $suffixStripped = false;

    /** @var array<string, bool> Accept header => verdict, the header is parsed once per request */
    private array $accepts = [];

    public function isEnabled(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    /**
     * True when text/markdown is listed explicitly and outranks text/html. A wildcard never selects markdown.
     */
    public function acceptsMarkdown(string $accept): bool
    {
        return $this->accepts[$accept] ??= $this->parseAccept($accept);
    }

    private function parseAccept(string $accept): bool
    {
        $header = AcceptHeader::fromString(strtolower($accept));
        if (!$header->has(self::MIME_TYPE)) {
            return false;
        }

        $markdown = $header->get(self::MIME_TYPE)?->getQuality() ?? 0.0;
        $html = $header->get('text/html')?->getQuality() ?? 0.0;

        return $markdown > $html;
    }

    /**
     * HEAD answers with the headers of the GET, so both methods negotiate.
     */
    public function isReadRequest(Mage_Core_Controller_Request_Http $request): bool
    {
        return $request->isGet() || $request->isHead();
    }

    public function isMarkdownRequest(Mage_Core_Controller_Request_Http $request): bool
    {
        if (!$this->isEnabled() || !$this->isReadRequest($request)) {
            return false;
        }

        return $this->suffixStripped || $this->acceptsMarkdown((string) $request->getHeader('Accept'));
    }

    public function getRoute(Mage_Core_Controller_Request_Http $request): string
    {
        return $request->getModuleName() . '/' . $request->getControllerName() . '/' . $request->getActionName();
    }

    /**
     * The route of the request when it asks for markdown on an allowed route, else null.
     */
    public function markdownRoute(Mage_Core_Controller_Request_Http $request): ?string
    {
        if (!$this->isMarkdownRequest($request)) {
            return null;
        }
        $route = $this->getRoute($request);

        return $this->isRouteAllowed($route) ? $route : null;
    }

    public function isRouteAllowed(string $route, int|string|null $store = null): bool
    {
        return array_any($this->getAllowedRoutes($store), fn(string $prefix): bool => str_starts_with($route, $prefix));
    }

    public function hasMarkdown(string $route, int|string|null $store = null): bool
    {
        return $this->isEnabled($store)
            && $this->isRouteAllowed($route, $store)
            && Mage::getSingleton('contentnegotiation/resolver')->hasRenderer($route);
    }

    /**
     * @return string[]
     */
    public function getAllowedRoutes(int|string|null $store = null): array
    {
        $lines = explode("\n", (string) Mage::getStoreConfig(self::XML_PATH_ALLOWED_ROUTES, $store));

        return array_values(array_filter(array_map(trim(...), $lines)));
    }

    public function getMarkdownUrl(Mage_Core_Controller_Request_Http $request): string
    {
        $baseUrl = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, Mage::app()->isCurrentlySecure());

        return $this->toMarkdownUrl($baseUrl . ltrim($request->getRequestString(), '/'));
    }

    /**
     * The root page of the store becomes /index.md, every other URL takes the suffix in place of
     * its trailing slash. The query string is dropped unless asked for: a markdown document has
     * no page, sort or filter form, so one URL names it, but a redirect target keeps its query.
     * A path without a host is resolved against the store base path.
     */
    public function toMarkdownUrl(string $url, bool $keepQuery = false): string
    {
        [$path, $query] = $this->splitQuery($url);
        $path = rtrim($path, '/');
        $query = $keepQuery ? $query : '';
        if ($this->isRootPath($path)) {
            return $path . '/' . self::ROOT_FILE . $query;
        }

        return $path . self::SUFFIX . $query;
    }

    public function hasMarkdownSuffix(string $url): bool
    {
        return str_ends_with($this->splitQuery($url)[0], self::SUFFIX);
    }

    /**
     * Null when the URL has no suffix or is "/.md". The path gets the configured trailing slash
     * style, so URL rewrites match it as they match the HTML URL. "index.md" names the root page
     * only directly under the store root, so a page with the URL key "index" keeps its own
     * markdown URL.
     */
    public function fromMarkdownUrl(string $url, string $basePath = ''): ?string
    {
        [$path, $query] = $this->splitQuery($url);
        if (!str_ends_with($path, self::SUFFIX)) {
            return null;
        }

        $path = substr($path, 0, -strlen(self::SUFFIX));
        if (trim($path, '/') === '') {
            return null;
        }

        $root = substr(self::ROOT_FILE, 0, -strlen(self::SUFFIX));
        if (basename($path) === $root) {
            $parent = substr($path, 0, -strlen($root));
            if ($this->isRootDir(rtrim($parent, '/'), $basePath)) {
                return $parent . $query;
            }
        }

        return Mage::helper('core/url')->addOrRemoveTrailingSlash($path) . $query;
    }

    /**
     * The store root is the base path or the front script, alone or followed by a store code
     * while store codes are part of the URL. The request strips the code from the path later.
     */
    private function isRootDir(string $dir, string $basePath): bool
    {
        $base = rtrim($basePath, '/');
        if ($base !== '' && ($dir === $base || str_starts_with($dir, $base . '/'))) {
            $dir = substr($dir, strlen($base));
        } elseif (($pos = strpos($dir . '/', '/index.php/')) !== false) {
            $dir = substr($dir, $pos + strlen('/index.php'));
        }
        if ($dir === '') {
            return true;
        }
        if (!Mage::isInstalled() || !Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL)) {
            return false;
        }

        $code = ltrim($dir, '/');

        return !str_contains($code, '/') && isset(Mage::app()->getStores(true, true)[$code]);
    }

    /**
     * @return array{string, string}
     */
    private function splitQuery(string $url): array
    {
        $pos = strpos($url, '?');

        return $pos === false ? [$url, ''] : [substr($url, 0, $pos), substr($url, $pos)];
    }

    /**
     * Compared without its trailing slash against the secure and the unsecure base URL of the
     * current store, and against their paths for a Location without a host.
     */
    private function isRootPath(string $path): bool
    {
        if ($path === '' || (string) parse_url($path, PHP_URL_PATH) === '') {
            return true;
        }

        $store = Mage::app()->getStore();
        foreach ([false, true] as $secure) {
            $baseUrl = rtrim($store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, $secure), '/');
            if ($path === $baseUrl || $path === rtrim((string) parse_url($baseUrl, PHP_URL_PATH), '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keyed by the request URI with its query: a page named by a parameter (catalog/product/view?id=2)
     * must not answer with the document of another id. The suffix is already stripped, so the
     * ".md" form and the Accept form of a page share one entry.
     */
    public function getCacheId(Mage_Core_Controller_Request_Http $request): string
    {
        $store = Mage::app()->getStore();

        return 'contentnegotiation_' . md5(implode('|', [
            (string) $store->getId(),
            (string) $store->getCurrentCurrencyCode(),
            (string) Mage::getSingleton('customer/session')->getCustomerGroupId(),
            (string) $request->getRequestUri(),
        ]));
    }

    /**
     * Zero disables the cache.
     */
    public function getCacheLifetime(int|string|null $store = null): int
    {
        return (int) Mage::getStoreConfig(self::XML_PATH_CACHE_LIFETIME, $store);
    }

    public function usesCache(): bool
    {
        return $this->getCacheLifetime() > 0 && Mage::app()->useCache(Mage_Core_Block_Abstract::CACHE_GROUP);
    }

    public function markSuffixStripped(): void
    {
        $this->suffixStripped = true;
    }

    public function wasSuffixStripped(): bool
    {
        return $this->suffixStripped;
    }
}
