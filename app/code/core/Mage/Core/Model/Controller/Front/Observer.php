<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Controller_Front_Observer
{
    /**
     * Run pre-dispatch checks in order: base URL, trailing slash, store resolution,
     * URL rewrite, config rewrite, HTTPS enforcement.
     *
     * Each step short-circuits the chain if a redirect has been set.
     */
    #[Maho\Config\Observer('controller_front_dispatch_before')]
    public function onDispatchBefore(\Maho\Event\Observer $event): void
    {
        /** @var Mage_Core_Controller_Varien_Front $front */
        $front = $event->getData('front');
        $request = $front->getRequest();
        $response = $front->getResponse();

        $this->checkBaseUrl($request, $response);
        if ($response->isRedirect()) {
            return;
        }

        $this->checkTrailingSlash($request, $response);
        if ($response->isRedirect()) {
            return;
        }

        $this->resolveStore($request);

        $this->rewriteDb($request, $response);
        if ($response->isRedirect()) {
            return;
        }

        $this->rewriteConfig($request);
        $this->enforceHttps($request, $response);
    }

    private function checkBaseUrl(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
    {
        if (!Mage::isInstalled() || $request->getPost() || strtolower($request->getMethod()) === 'post') {
            return;
        }

        $redirectCode = Mage::getStoreConfigAsInt('web/url/redirect_to_base');
        if (!$redirectCode) {
            return;
        }
        if ($redirectCode !== 301) {
            $redirectCode = 302;
        }

        if (Mage::helper('adminhtml')->isAdminFrontNameMatched($request->getPathInfo())) {
            return;
        }

        $baseUrl = Mage::getBaseUrl(
            Mage_Core_Model_Store::URL_TYPE_WEB,
            Mage::app()->isCurrentlySecure(),
        );
        if (!$baseUrl) {
            return;
        }

        $uri = @parse_url($baseUrl);
        $requestUri = $request->getRequestUri() ?: '/';

        if (
            (isset($uri['scheme']) && $uri['scheme'] !== $request->getScheme())
            || (isset($uri['host']) && $uri['host'] !== $request->getHttpHost())
            || (isset($uri['path']) && !str_contains($requestUri, $uri['path']))
        ) {
            $response->setRedirect($baseUrl, $redirectCode);
        }
    }

    private function checkTrailingSlash(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
    {
        if (!Mage::isInstalled() || $request->getPost() || strtolower($request->getMethod()) === 'post') {
            return;
        }

        if (Mage::helper('adminhtml')->isAdminFrontNameMatched($request->getPathInfo())) {
            return;
        }

        $requestUri = $request->getRequestUri();
        $canonicalUri = preg_replace('#/{2,}#', '/', $requestUri);
        $canonicalUri = Mage::helper('core/url')->addOrRemoveTrailingSlash($canonicalUri);

        if ($canonicalUri !== $requestUri) {
            $response->setRedirect($canonicalUri, 301);
        }
    }

    private function resolveStore(Mage_Core_Controller_Request_Http $request): void
    {
        if (!Mage::isInstalled()) {
            return;
        }

        if (!Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL)) {
            return;
        }

        $pathInfo = $request->getPathInfo();
        $pathParts = explode('/', ltrim($pathInfo, '/'), 2);
        $storeCode = $pathParts[0];

        if ($request->isDirectAccessFrontendName($storeCode)) {
            return;
        }

        $stores = Mage::app()->getStores(true, true);
        if ($storeCode !== '' && isset($stores[$storeCode])) {
            Mage::app()->setCurrentStore($storeCode);
            $request->setPathInfo('/' . ($pathParts[1] ?? ''));
        } elseif ($storeCode !== '') {
            $request->setActionName('noRoute');
        }
    }

    private function rewriteDb(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
    {
        if (!Mage::isInstalled() || Mage::app()->getStore()->isAdmin() || $request->isStraight()) {
            return;
        }

        \Maho\Profiler::start('mage::dispatch::db_url_rewrite');

        /** @var Mage_Core_Model_Url_Rewrite $rewrite */
        $rewrite = Mage::getModel('core/url_rewrite');
        $rewrite->setStoreId(Mage::app()->getStore()->getId());

        $requestCases = $this->getRequestCases($request);
        $rewrite->loadByRequestPath($requestCases);

        $fromStore = $request->getQuery('___from_store');
        if (!$rewrite->getId() && $fromStore) {
            $this->handleCrossStoreRedirect($rewrite, $response, $fromStore, $requestCases);
            \Maho\Profiler::stop('mage::dispatch::db_url_rewrite');
            return;
        }

        if (!$rewrite->getId()) {
            \Maho\Profiler::stop('mage::dispatch::db_url_rewrite');
            return;
        }

        $request->setAlias(Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS, $rewrite->getRequestPath());
        $this->processRedirectOptions($rewrite, $request, $response);

        \Maho\Profiler::stop('mage::dispatch::db_url_rewrite');
    }

    private function handleCrossStoreRedirect(
        Mage_Core_Model_Url_Rewrite $rewrite,
        Mage_Core_Controller_Response_Http $response,
        string $fromStore,
        array $requestCases,
    ): void {
        $stores = Mage::app()->getStores(false, true);
        if (empty($stores[$fromStore])) {
            return;
        }

        $store = $stores[$fromStore];
        $rewrite->setStoreId($store->getId())->loadByRequestPath($requestCases);
        if (!$rewrite->getId()) {
            return;
        }

        $currentStore = Mage::app()->getStore();
        $rewrite->setStoreId($currentStore->getId())->loadByIdPath($rewrite->getIdPath());

        $this->setStoreCodeCookie($currentStore->getCode());
        $response->setRedirect($currentStore->getBaseUrl() . $rewrite->getRequestPath(), 301);
    }

    private function processRedirectOptions(
        Mage_Core_Model_Url_Rewrite $rewrite,
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response,
    ): void {
        $isPermanentRedirect = $rewrite->hasOption('RP');

        $external = substr($rewrite->getTargetPath(), 0, 6);
        if ($external === 'http:/' || $external === 'https:') {
            $this->setStoreCodeCookie(Mage::app()->getStore($rewrite->getStoreId())->getCode());
            $response->setRedirect($rewrite->getTargetPath(), $isPermanentRedirect ? 301 : 302);
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $response->setHeader('Pragma', 'no-cache');
            return;
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
            return;
        }

        $queryString = $this->getQueryString();
        if ($queryString) {
            $targetUrl .= '?' . $queryString;
        }

        $request->setRequestUri($targetUrl);
        $request->setPathInfo($rewrite->getTargetPath());
    }

    /**
     * @return array<string>
     */
    private function getRequestCases(Mage_Core_Controller_Request_Http $request): array
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
        $store = Mage::app()->getStore($storeCode);
        if ($store->getWebsite()->getDefaultStore()->getId() == $store->getId()) {
            Mage::app()->getCookie()->delete(Mage_Core_Model_Store::COOKIE_NAME);
        } else {
            Mage::app()->getCookie()->set(Mage_Core_Model_Store::COOKIE_NAME, $storeCode, true);
        }
    }

    // -------------------------------------------------------------------------
    // Config rewrite
    // -------------------------------------------------------------------------

    private function rewriteConfig(Mage_Core_Controller_Request_Http $request): void
    {
        $config = Mage::getConfig()->getNode('global/rewrite');
        if (!$config) {
            return;
        }

        foreach ($config->children() as $rewrite) {
            $from = (string) $rewrite->from;
            $to = (string) $rewrite->to;
            if ($from === '' || $to === '') {
                continue;
            }
            $from = $this->processRewriteUrl($from);
            $to = $this->processRewriteUrl($to);

            $pathInfo = preg_replace($from, $to, $request->getPathInfo());
            if (isset($rewrite->complete)) {
                $request->setPathInfo($pathInfo);
            } else {
                $request->rewritePathInfo($pathInfo);
            }
        }
    }

    private function processRewriteUrl(string $url): string
    {
        $startPos = strpos($url, '{');
        if ($startPos !== false) {
            $endPos = strpos($url, '}');
            $routeName = substr($url, $startPos + 1, $endPos - $startPos - 1);
            $frontName = \Maho\Routing\RouteCollectionBuilder::getFrontNameByRoute($routeName);
            if ($frontName) {
                $url = str_replace('{' . $routeName . '}', $frontName, $url);
            }
        }
        return $url;
    }

    private function enforceHttps(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
    {
        if (!Mage::isInstalled() || $request->getPost() || $request->isSecure()) {
            return;
        }

        $path = $this->getRoutePath($request);
        if (!$this->shouldBeSecure($path)) {
            return;
        }

        $url = $this->getSecureUrl($request);
        if ($request->getRouteName() !== 'adminhtml' && Mage::app()->getUseSessionInUrl()) {
            $url = Mage::getSingleton('core/url')->getRedirectUrl($url);
        }
        $response->setRedirect($url);
    }

    private function getRoutePath(Mage_Core_Controller_Request_Http $request): string
    {
        $path = trim($request->getPathInfo(), '/');
        $p = $path ? explode('/', $path) : [];

        return '/' . ($p[0] ?? 'core') . '/' . ($p[1] ?? 'index') . '/' . ($p[2] ?? 'index');
    }

    private function shouldBeSecure(string $path): bool
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return str_starts_with((string) Mage::getConfig()->getNode('default/web/unsecure/base_url'), 'https')
                || Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_SECURE_IN_ADMINHTML, Mage_Core_Model_App::ADMIN_STORE_ID)
                    && str_starts_with((string) Mage::getConfig()->getNode('default/web/secure/base_url'), 'https');
        }

        return str_starts_with(Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_UNSECURE_BASE_URL), 'https')
            || Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_SECURE_IN_FRONTEND)
                && str_starts_with(Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL), 'https')
                && Mage::getConfig()->shouldUrlBeSecure($path);
    }

    private function getSecureUrl(Mage_Core_Controller_Request_Http $request): string
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)
                ->getBaseUrl('link', true) . ltrim($request->getPathInfo(), '/');
        }

        if ($alias = $request->getAlias(Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS)) {
            return Mage::getBaseUrl('link', true) . ltrim($alias, '/');
        }

        return Mage::getBaseUrl('link', true) . ltrim($request->getPathInfo(), '/');
    }
}
