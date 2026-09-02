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

    public function isEnabled(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    /**
     * True when text/markdown is listed explicitly and outranks text/html. A wildcard never selects markdown.
     */
    public function acceptsMarkdown(string $accept): bool
    {
        $accept = strtolower($accept);
        if (!str_contains($accept, self::MIME_TYPE)) {
            return false;
        }

        $header = AcceptHeader::fromString($accept);
        if (!$header->has(self::MIME_TYPE)) {
            return false;
        }

        $markdown = $header->get(self::MIME_TYPE)?->getQuality() ?? 0.0;
        $html = $header->get('text/html')?->getQuality() ?? 0.0;

        return $markdown > $html;
    }

    public function isMarkdownRequest(Mage_Core_Controller_Request_Http $request): bool
    {
        if (!$this->isEnabled() || !$request->isGet()) {
            return false;
        }

        return $this->suffixStripped || $this->acceptsMarkdown((string) $request->getHeader('Accept'));
    }

    public function getRoute(Mage_Core_Controller_Request_Http $request): string
    {
        return $request->getModuleName() . '/' . $request->getControllerName() . '/' . $request->getActionName();
    }

    public function isAllowedRoute(Mage_Core_Controller_Request_Http $request): bool
    {
        return $this->isRouteAllowed($this->getRoute($request));
    }

    public function isRouteAllowed(string $route, int|string|null $store = null): bool
    {
        return array_any($this->getAllowedRoutes($store), fn(string $prefix): bool => str_starts_with($route, $prefix));
    }

    /**
     * True when a page on this route gets a markdown version: the feature is on, the route is allowed
     * and a renderer is registered for it.
     */
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
        $baseUrl = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);

        return $this->toMarkdownUrl($baseUrl . ltrim($request->getRequestString(), '/'));
    }

    /**
     * The root page of the store becomes /index.md, every other URL takes the suffix in place of
     * its trailing slash. The query string is dropped: a markdown document has no page, sort or
     * filter form, so one URL names it.
     */
    public function toMarkdownUrl(string $url): string
    {
        [$path] = $this->splitQuery($url);
        $path = rtrim($path, '/');
        if ($this->isRootPath($path)) {
            return $path . '/' . self::ROOT_FILE;
        }

        return $path . self::SUFFIX;
    }

    /**
     * Null when the URL has no suffix or is "/.md". The path gets the configured trailing slash
     * style, so URL rewrites match it as they match the HTML URL.
     */
    public function fromMarkdownUrl(string $url): ?string
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
            return substr($path, 0, -strlen($root)) . $query;
        }

        return Mage::helper('core/url')->addOrRemoveTrailingSlash($path) . $query;
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
     * A path without a trailing slash is the root when nothing follows the host, or when it is
     * the base URL of the current store.
     */
    private function isRootPath(string $path): bool
    {
        if ($path === '' || (string) parse_url($path, PHP_URL_PATH) === '') {
            return true;
        }

        $baseUrl = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);

        return $path === rtrim($baseUrl, '/');
    }

    /**
     * Keyed by path, not URI: the same document answers every query string of a page.
     */
    public function getCacheId(Mage_Core_Controller_Request_Http $request): string
    {
        $store = Mage::app()->getStore();
        [$path] = $this->splitQuery((string) $request->getRequestUri());

        return 'contentnegotiation_' . md5(implode('|', [
            (string) $store->getId(),
            (string) $store->getCurrentCurrencyCode(),
            (string) Mage::getSingleton('customer/session')->getCustomerGroupId(),
            $path,
        ]));
    }

    public function getCacheLifetime(int|string|null $store = null): int
    {
        return max(0, (int) Mage::getStoreConfig(self::XML_PATH_CACHE_LIFETIME, $store));
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
