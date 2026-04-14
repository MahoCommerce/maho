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
use Mage_Core_Model_App;
use Mage_Core_Model_Store;
use Mage_Core_Model_Url_Rewrite;
use Maho\Http\MiddlewareInterface;

class HttpsEnforcementMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        callable $next,
    ): void {
        if (!Mage::isInstalled() || $request->getPost() || $request->isSecure()) {
            $next();
            return;
        }

        $path = $this->getRoutePath($request);

        if ($this->shouldBeSecure($path)) {
            $url = $this->getSecureUrl($request);
            if ($request->getRouteName() !== 'adminhtml' && Mage::app()->getUseSessionInUrl()) {
                $url = Mage::getSingleton('core/url')->getRedirectUrl($url);
            }
            $response->setRedirect($url);
            return;
        }

        $next();
    }

    private function getRoutePath(\Mage_Core_Controller_Request_Http $request): string
    {
        $path = trim($request->getPathInfo(), '/');
        $p = $path ? explode('/', $path) : [];

        $module = $p[0] ?? 'core';
        $controller = $p[1] ?? 'index';
        $action = $p[2] ?? 'index';

        return '/' . $module . '/' . $controller . '/' . $action;
    }

    private function shouldBeSecure(string $path): bool
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return $this->shouldBeSecureAdmin();
        }
        return $this->shouldBeSecureFrontend($path);
    }

    private function shouldBeSecureFrontend(string $path): bool
    {
        return str_starts_with(Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_UNSECURE_BASE_URL), 'https')
            || Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_SECURE_IN_FRONTEND)
                && str_starts_with(Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL), 'https')
                && Mage::getConfig()->shouldUrlBeSecure($path);
    }

    private function shouldBeSecureAdmin(): bool
    {
        return str_starts_with((string) Mage::getConfig()->getNode('default/web/unsecure/base_url'), 'https')
            || Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_SECURE_IN_ADMINHTML, Mage_Core_Model_App::ADMIN_STORE_ID)
                && str_starts_with((string) Mage::getConfig()->getNode('default/web/secure/base_url'), 'https');
    }

    private function getSecureUrl(\Mage_Core_Controller_Request_Http $request): string
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
