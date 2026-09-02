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

    protected $_moduleName = 'Maho_ContentNegotiation';

    private bool $suffixStripped = false;
    private bool $served = false;

    public function isEnabled(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    /**
     * True when text/markdown is listed explicitly and outranks text/html. A wildcard never selects markdown.
     */
    public function acceptsMarkdown(string $accept): bool
    {
        if ($accept === '') {
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
        $route = $this->getRoute($request);

        return array_any($this->getAllowedRoutes(), fn(string $prefix): bool => str_starts_with($route, $prefix));
    }

    /**
     * @return string[]
     */
    public function getAllowedRoutes(): array
    {
        $lines = explode("\n", (string) Mage::getStoreConfig(self::XML_PATH_ALLOWED_ROUTES));

        return array_values(array_filter(array_map(trim(...), $lines)));
    }

    /**
     * Null for the root page: the web server rejects "/.md" as a hidden file.
     */
    public function getMarkdownUrl(Mage_Core_Controller_Request_Http $request): ?string
    {
        $path = trim($request->getOriginalPathInfo(), '/');
        if ($path === '') {
            return null;
        }

        return Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK) . $path . self::SUFFIX;
    }

    public function toMarkdownUrl(string $url): string
    {
        $query = '';
        $pos = strpos($url, '?');
        if ($pos !== false) {
            $query = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        return rtrim($url, '/') . self::SUFFIX . $query;
    }

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

    public function markServed(): void
    {
        $this->served = true;
    }

    public function wasServed(): bool
    {
        return $this->served;
    }
}
