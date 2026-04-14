<?php

/**
 * Maho
 *
 * @package    Maho_Http
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Http\Middleware;

use Mage;
use Mage_Core_Model_Store;
use Mage_Core_Model_Url_Rewrite;
use Maho\Http\MiddlewareInterface;

class UrlRewriteMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        callable $next,
    ): void {
        if (!Mage::isInstalled() || Mage::app()->getStore()->isAdmin() || $request->isStraight()) {
            $next();
            return;
        }

        \Maho\Profiler::start('mage::dispatch::db_url_rewrite');
        $redirected = $this->rewriteDb($request, $response);
        \Maho\Profiler::stop('mage::dispatch::db_url_rewrite');

        if ($redirected) {
            return;
        }

        $next();
    }

    private function rewriteDb(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
    ): bool {
        $app = Mage::app();

        /** @var Mage_Core_Model_Url_Rewrite $rewrite */
        $rewrite = Mage::getModel('core/url_rewrite');
        $rewrite->setStoreId($app->getStore()->getId());

        $requestCases = $this->getRequestCases($request);
        $rewrite->loadByRequestPath($requestCases);

        $fromStore = $request->getQuery('___from_store');
        if (!$rewrite->getId() && $fromStore) {
            return $this->handleCrossStoreRedirect($rewrite, $request, $response, $fromStore, $requestCases);
        }

        if (!$rewrite->getId()) {
            return false;
        }

        $request->setAlias(
            Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
            $rewrite->getRequestPath(),
        );

        return $this->processRedirectOptions($rewrite, $request, $response);
    }

    private function handleCrossStoreRedirect(
        Mage_Core_Model_Url_Rewrite $rewrite,
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        string $fromStore,
        array $requestCases,
    ): bool {
        $app = Mage::app();
        $stores = $app->getStores(false, true);

        if (empty($stores[$fromStore])) {
            return false;
        }

        $store = $stores[$fromStore];
        $fromStoreId = $store->getId();

        $rewrite->setStoreId($fromStoreId)->loadByRequestPath($requestCases);
        if (!$rewrite->getId()) {
            return false;
        }

        $currentStore = $app->getStore();
        $rewrite->setStoreId($currentStore->getId())->loadByIdPath($rewrite->getIdPath());

        $this->setStoreCodeCookie($currentStore->getCode());

        $targetUrl = $currentStore->getBaseUrl() . $rewrite->getRequestPath();
        $response->setRedirect($targetUrl, 301);
        return true;
    }

    private function processRedirectOptions(
        Mage_Core_Model_Url_Rewrite $rewrite,
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
    ): bool {
        $isPermanentRedirect = $rewrite->hasOption('RP');

        $external = substr($rewrite->getTargetPath(), 0, 6);
        if ($external === 'http:/' || $external === 'https:') {
            $destinationStoreCode = Mage::app()->getStore($rewrite->getStoreId())->getCode();
            $this->setStoreCodeCookie($destinationStoreCode);
            $response->setRedirect($rewrite->getTargetPath(), $isPermanentRedirect ? 301 : 302);
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $response->setHeader('Pragma', 'no-cache');
            return true;
        }

        $targetUrl = $request->getBaseUrl() . '/' . $rewrite->getTargetPath();

        $storeCode = Mage::app()->getStore()->getCode();
        if (Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL) && !empty($storeCode)) {
            $targetUrl = $request->getBaseUrl() . '/' . $storeCode . '/' . $rewrite->getTargetPath();
        }

        if ($rewrite->hasOption('R') || $isPermanentRedirect) {
            $response->setRedirect($targetUrl, $isPermanentRedirect ? 301 : 302);
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $response->setHeader('Pragma', 'no-cache');
            return true;
        }

        $queryString = $this->getQueryString();
        if ($queryString) {
            $targetUrl .= '?' . $queryString;
        }

        $request->setRequestUri($targetUrl);
        $request->setPathInfo($rewrite->getTargetPath());

        return false;
    }

    /**
     * @return array<string>
     */
    private function getRequestCases(\Mage_Core_Controller_Request_Http $request): array
    {
        $pathInfo = $request->getPathInfo();
        $requestPath = trim($pathInfo, '/');
        $origSlash = str_ends_with($pathInfo, '/') ? '/' : '';
        $altSlash = $origSlash ? '' : '/';

        $requestCases = [];
        $queryString = $this->getQueryString();
        if ($queryString) {
            $requestCases[] = $requestPath . $origSlash . '?' . $queryString;
            $requestCases[] = $requestPath . $altSlash . '?' . $queryString;
        }
        $requestCases[] = $requestPath . $origSlash;
        $requestCases[] = $requestPath . $altSlash;
        return $requestCases;
    }

    private function getQueryString(): string|false
    {
        if (!empty($_SERVER['QUERY_STRING'])) {
            $queryParams = [];
            parse_str($_SERVER['QUERY_STRING'], $queryParams);
            $hasChanges = false;
            foreach (array_keys($queryParams) as $key) {
                if (str_starts_with($key, '___')) {
                    unset($queryParams[$key]);
                    $hasChanges = true;
                }
            }
            if ($hasChanges) {
                return http_build_query($queryParams);
            }
            return $_SERVER['QUERY_STRING'];
        }
        return false;
    }

    private function setStoreCodeCookie(string $storeCode): void
    {
        $app = Mage::app();
        $store = $app->getStore($storeCode);
        if ($store->getWebsite()->getDefaultStore()->getId() == $store->getId()) {
            $app->getCookie()->delete(Mage_Core_Model_Store::COOKIE_NAME);
        } else {
            $app->getCookie()->set(Mage_Core_Model_Store::COOKIE_NAME, $storeCode, true);
        }
    }
}
