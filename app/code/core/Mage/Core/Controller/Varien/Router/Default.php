<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Controller_Varien_Router_Default extends Mage_Core_Controller_Varien_Router_Abstract
{
    /**
     * Try Symfony routing first (attributed routes, forward dispatch, legacy path rewrites),
     * then fall back to the configured no-route action.
     */
    #[\Override]
    public function match(Mage_Core_Controller_Request_Http $request): bool
    {
        if ($this->_matchSymfony($request)) {
            return true;
        }

        $noRoute        = explode('/', $this->_getNoRouteConfig());
        $moduleName     = isset($noRoute[0]) && $noRoute[0] ? $noRoute[0] : 'core';
        $controllerName = isset($noRoute[1]) && $noRoute[1] ? $noRoute[1] : 'index';
        $actionName     = isset($noRoute[2]) && $noRoute[2] ? $noRoute[2] : 'index';

        if ($this->_isAdmin()) {
            $adminFrontName = (string) Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME);
            if ($adminFrontName != $moduleName) {
                $moduleName     = 'core';
                $controllerName = 'index';
                $actionName     = 'noRoute';
                Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
            }
        }

        $request->setModuleName($moduleName)
            ->setControllerName($controllerName)
            ->setActionName($actionName);

        return true;
    }

    /**
     * Try to match using Symfony's UrlMatcher against compiled #[Route] attributes.
     *
     * Strategy 1 — Forward dispatch: module/controller/action already set by another router
     *   (CMS, URL rewrite, _forward()); resolve via reverse lookup and dispatch.
     *
     * Strategy 2 — URL matching: match path against compiled #[Route] attributes.
     *
     * Strategy 3 — Legacy path: parse path as frontName/controller/action/key/value
     *   (used by DB URL rewrites stored in legacy format).
     */
    protected function _matchSymfony(Mage_Core_Controller_Request_Http $request): bool
    {
        \Maho\Profiler::start('mage::dispatch::symfony_match');

        $dispatcher = new \Maho\Routing\ControllerDispatcher();

        try {
            if ($request->getModuleName() && $request->getControllerName() && $request->getActionName()) {
                return $dispatcher->dispatchForward($request, Mage::app()->getResponse());
            }

            $collection = (new \Maho\Routing\RouteCollectionBuilder())->build();
            $context = new \Symfony\Component\Routing\RequestContext();
            $context->fromRequest($request->getSymfonyRequest());
            $matcher = new \Symfony\Component\Routing\Matcher\UrlMatcher($collection, $context);

            $pathInfo = $request->getPathInfo();
            $normalizedPath = (strlen($pathInfo) > 1) ? rtrim($pathInfo, '/') : $pathInfo;

            $params = $matcher->match($normalizedPath);

            return $dispatcher->dispatch($params, $request, Mage::app()->getResponse());
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
            return $dispatcher->dispatchLegacyPath($request, Mage::app()->getResponse());
        } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException) {
            return false;
        } finally {
            \Maho\Profiler::stop('mage::dispatch::symfony_match');
        }
    }

    protected function _getNoRouteConfig(): string
    {
        return Mage::app()->getStore()->getConfig('web/default/no_route');
    }

    protected function _isAdmin(): bool
    {
        return Mage::app()->getStore()->isAdmin();
    }
}
