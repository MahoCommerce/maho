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
     * Try to match using the opcached CompiledUrlMatcher, falling back in order:
     *  1. Forward dispatch when another router has already set module/controller/action
     *  2. Legacy XML routers (BC shim for frontend/routers config from unmigrated modules)
     *  3. Compiled matcher (dumped at composer dump-autoload)
     *  4. Legacy path parsing (URL rewrites stored as frontName/controller/action/key/value)
     */
    protected function _matchSymfony(Mage_Core_Controller_Request_Http $request): bool
    {
        \Maho\Profiler::start('mage::dispatch::symfony_match');

        $dispatcher = new \Maho\Routing\ControllerDispatcher();

        try {
            if ($request->getModuleName() && $request->getControllerName() && $request->getActionName()) {
                return $dispatcher->dispatchForward($request, Mage::app()->getResponse());
            }

            if ($this->_matchLegacyXmlRoute($request, $dispatcher)) {
                return true;
            }

            $context = new \Symfony\Component\Routing\RequestContext();
            $context->fromRequest($request->getSymfonyRequest());
            $matcher = \Maho\Routing\RouteCollectionBuilder::createMatcher($context);

            $pathInfo = $request->getPathInfo();
            $normalizedPath = (strlen($pathInfo) > 1) ? rtrim($pathInfo, '/') : $pathInfo;

            $params = $matcher->match($normalizedPath);

            return $dispatcher->dispatch($params, $request, Mage::app()->getResponse());
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
            return $dispatcher->dispatchLegacyPath($request, Mage::app()->getResponse());
        } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException $e) {
            $response = Mage::app()->getResponse();
            $response->setHttpResponseCode(405);
            $response->setHeader('Allow', implode(', ', $e->getAllowedMethods()));
            if ($response->getBody() === '') {
                $response->setBody('Method Not Allowed');
            }
            $request->setDispatched(true);
            return true;
        } finally {
            \Maho\Profiler::stop('mage::dispatch::symfony_match');
        }
    }

    /**
     * BC shim: modules declaring `<frontend><routers><code><args><frontName>` in
     * config.xml (M1/OpenMage standard-router pattern) match *before* the Symfony
     * matcher, preserving their original "first declared wins" precedence even when
     * their frontName collides with a Maho core route.
     */
    protected function _matchLegacyXmlRoute(
        Mage_Core_Controller_Request_Http $request,
        \Maho\Routing\ControllerDispatcher $dispatcher,
    ): bool {
        $legacyMap = \Maho\Routing\RouteCollectionBuilder::getLegacyFrontNames();
        if (!$legacyMap) {
            return false;
        }

        $pathInfo = trim((string) $request->getPathInfo(), '/');
        if ($pathInfo === '') {
            return false;
        }

        $frontName = strtolower(explode('/', $pathInfo, 2)[0]);
        if (!isset($legacyMap[$frontName])) {
            return false;
        }

        return $dispatcher->dispatchLegacyPath($request, Mage::app()->getResponse());
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
