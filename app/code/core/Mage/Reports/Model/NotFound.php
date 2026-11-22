<?php

/**
 * A storefront path that answered 404, with its hit count and its last referer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_NotFound extends Mage_Core_Model_Abstract
{
    public const string XML_PATH_ENABLED = 'reports/not_found/enabled';
    public const string XML_PATH_LOG_RATE_LIMIT = 'reports/not_found/log_rate_limit';
    public const string XML_PATH_DAYS_TO_KEEP = 'reports/not_found/days_to_keep';
    public const string XML_PATH_MAX_ROWS = 'reports/not_found/max_rows';

    public const string RATE_LIMIT_NAMESPACE = 'report_not_found';
    public const int RATE_LIMIT_WINDOW = 3600;

    /** The length of core_url_rewrite.request_path. A longer path cannot get a redirect. */
    public const int MAX_PATH_LENGTH = 255;

    public const int MAX_REFERER_LENGTH = 2048;

    /** Paths that are not storefront pages. */
    private const array IGNORED_PREFIXES = ['api/', '.well-known/'];

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/notFound');
    }

    /**
     * Count a hit for the path of $request when $response answers 404 in a storefront store view. The path is
     * stored as the URL rewrite lookup reads it: lower case, without the slashes at the ends and without the query
     * string. Return true when the hit is counted.
     */
    public function recordRequest(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response,
    ): bool {
        if ($response->getHttpResponseCode() !== 404) {
            return false;
        }

        $store = Mage::app()->getStore();
        if ($store->isAdmin() || !Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store)) {
            return false;
        }

        $path = $this->normalizePath((string) $request->getOriginalPathInfo());
        if ($path === null) {
            return false;
        }

        $limit = Mage::getStoreConfigAsInt(self::XML_PATH_LOG_RATE_LIMIT, $store);
        if ($limit > 0 && !Mage::helper('core')->rateLimiter(self::RATE_LIMIT_NAMESPACE, $limit, self::RATE_LIMIT_WINDOW)->attempt()) {
            return false;
        }

        try {
            Mage::getResourceSingleton('reports/notFound')->recordHit((int) $store->getId(), $path, $this->normalizeReferer($request));
        } catch (\Throwable $e) {
            Mage::logException($e);
            return false;
        }
        return true;
    }

    private function normalizePath(string $pathInfo): ?string
    {
        $path = strtolower(trim($pathInfo, '/'));
        if ($path === '' || !mb_check_encoding($path, 'UTF-8') || mb_strlen($path, 'UTF-8') > self::MAX_PATH_LENGTH) {
            return null;
        }
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path . '/', $prefix)) {
                return null;
            }
        }
        return $path;
    }

    private function normalizeReferer(Mage_Core_Controller_Request_Http $request): ?string
    {
        $referer = trim((string) $request->getHeader('Referer'));
        if ($referer === '' || !mb_check_encoding($referer, 'UTF-8')) {
            return null;
        }
        return mb_substr($referer, 0, self::MAX_REFERER_LENGTH);
    }
}
