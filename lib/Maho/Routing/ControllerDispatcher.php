<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Routing;

use Mage;
use Mage_Core_Controller_Request_Http;
use Mage_Core_Controller_Response_Http;

/**
 * Dispatches controllers from Symfony UrlMatcher results.
 *
 * Handles attribute routes compiled from #[Route] attributes: resolves the
 * controller class (respecting third-party module chain overrides), then
 * dispatches the action. Unmatched requests fall back to the legacy router loop.
 */
class ControllerDispatcher
{
    /**
     * Dispatch an internally-forwarded request.
     *
     * Called when another router (CMS, Default, URL Rewrite) has set module/controller/action
     * on the request but hasn't dispatched it. First tries the reverse lookup map from
     * compiled #[Route] attributes, then falls back to direct class resolution.
     */
    public function dispatchForward(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response,
    ): bool {
        $moduleName = $request->getModuleName();
        $controllerName = $request->getControllerName();
        $actionName = $request->getActionName();

        if (!$moduleName || !$controllerName || !$actionName) {
            return false;
        }

        $controllerClass = null;
        $action = $actionName; // bare name without 'Action' suffix — hasAction/dispatch expect this
        $controllerModule = '';

        // Try reverse lookup first (finds attributed routes)
        $routeInfo = RouteCollectionBuilder::resolveRoute($moduleName, $controllerName, $actionName);
        if ($routeInfo) {
            $compiled = \Maho::getCompiledAttributes();
            $routes = $compiled['routes'] ?? [];
            $routeData = $routes[$routeInfo['name']] ?? null;

            if ($routeData) {
                $controllerClass = $routeData['class'];
                // Compiled action is 'indexAction' — strip suffix for hasAction/dispatch
                $action = preg_replace('/Action$/', '', $routeData['action']);
                $controllerModule = $routeData['module'] ?? '';
            }
        }

        // Fall back to direct class resolution for non-attributed actions (e.g. norouteAction)
        if (!$controllerClass) {
            $controllerClass = $this->resolveControllerClassByFrontName($moduleName, $controllerName);
        }

        if (!$controllerClass || !class_exists($controllerClass)) {
            return false;
        }

        $controllerInstance = Mage::getControllerInstance($controllerClass, $request, $response);

        if (!$controllerInstance->hasAction($action)) {
            return false;
        }

        if ($controllerModule) {
            $request->setControllerModule($controllerModule);
        }
        // Set routeName for event dispatching (controller_action_predispatch_<routeName>)
        if (!$request->getRouteName()) {
            $request->setRouteName($moduleName);
        }
        $request->setDispatched(true);
        $controllerInstance->dispatch($action);

        return true;
    }

    /**
     * Parse and dispatch a legacy internal path (frontName/controller/action/key/value).
     *
     * These paths come from URL rewrites which store target paths in legacy format
     * (e.g. "catalog/category/view/id/14"). Parses the path into module/controller/action
     * and key/value params, then dispatches via the reverse lookup map.
     */
    public function dispatchLegacyPath(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response,
    ): bool {
        $path = trim($request->getPathInfo(), '/');
        if (!$path) {
            return false;
        }

        $parts = explode('/', $path);
        if (count($parts) < 1) {
            return false;
        }

        $frontName = $parts[0];
        $controllerName = $parts[1] ?? 'index';
        $actionName = $parts[2] ?? 'index';

        // Parse remaining segments as key/value params
        for ($i = 3, $l = count($parts); $i < $l; $i += 2) {
            $request->setParam($parts[$i], isset($parts[$i + 1]) ? urldecode($parts[$i + 1]) : '');
        }

        $request->setModuleName($frontName);
        $request->setControllerName($controllerName);
        $request->setActionName($actionName);

        return $this->dispatchForward($request, $response);
    }

    /**
     * Resolve a controller class from frontName + controllerName.
     *
     * First checks compiled #[Route] attributes, then falls back to config.xml
     * module chain registrations via RouteRegistry (supports third-party modules
     * that haven't migrated to #[Route] attributes yet).
     */
    protected function resolveControllerClassByFrontName(string $frontName, string $controllerName): ?string
    {
        $compiled = \Maho::getCompiledAttributes();
        $routes = $compiled['routes'] ?? [];
        $adminFrontName = RouteCollectionBuilder::getAdminFrontNameStatic();

        foreach ($routes as $routeData) {
            $path = $routeData['path'] ?? '';
            $area = $routeData['area'] ?? 'frontend';

            if ($area === 'adminhtml') {
                if (strtolower($frontName) !== strtolower($adminFrontName)) {
                    continue;
                }
            } else {
                $segments = explode('/', ltrim($path, '/'));
                $routeFrontName = strtolower($segments[0] ?? '');
                if ($routeFrontName !== strtolower($frontName)) {
                    continue;
                }
            }

            $module = $routeData['module'] ?? '';
            if ($module) {
                $className = $module . '_' . uc_words($controllerName) . 'Controller';
                if (class_exists($className)) {
                    return $className;
                }
            }
        }

        // Fall back to config.xml module chain (third-party modules without #[Route] attributes)
        $modules = RouteRegistry::getModulesByFrontName($frontName);
        if ($modules) {
            foreach ($modules as $module) {
                $className = $module . '_' . uc_words($controllerName) . 'Controller';
                if (class_exists($className)) {
                    return $className;
                }
            }
        }

        return null;
    }

    /**
     * Dispatch a matched Symfony route.
     *
     * @param array<string, mixed> $params Route parameters from UrlMatcher::match()
     * @return bool True if dispatched, false if no matching controller found
     */
    public function dispatch(
        array $params,
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response,
    ): bool {
        $type = $params['_maho_type'] ?? '';

        return match ($type) {
            'attribute' => $this->dispatchAttribute($params, $request, $response),
            default => false,
        };
    }

    /**
     * Dispatch an attribute-routed controller.
     *
     * Walks the XML module chain (before/after ordering) so that controller
     * overrides from third-party modules are respected. The compiled class is
     * used as the default, but any module registered before it takes priority.
     */
    protected function dispatchAttribute(
        array $params,
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response,
    ): bool {
        $defaultClass = $params['_maho_controller'] ?? '';
        $action = $params['_maho_action'] ?? '';
        $module = $params['_maho_module'] ?? '';
        $controllerName = $params['_maho_controller_name'] ?? '';
        $area = $params['_maho_area'] ?? 'frontend';

        if (!$defaultClass || !$action) {
            return false;
        }

        $controllerClass = $this->resolveControllerClass($module, $controllerName, $area) ?? $defaultClass;

        // Strip 'Action' suffix — hasAction() and dispatch() expect bare name (e.g. 'index', not 'indexAction')
        $actionName = preg_replace('/Action$/', '', $action);

        if (!class_exists($controllerClass)) {
            return false;
        }

        $controllerInstance = Mage::getControllerInstance($controllerClass, $request, $response);

        if (!$controllerInstance->hasAction($actionName)) {
            return false;
        }

        $this->setRequestParams($params, $request);

        // Parse admin catch-all key/value params (e.g. /id/5/store/1)
        if ($area === 'adminhtml' && !empty($params['_catchall'])) {
            $this->parseUrlParams($params['_catchall'], $request);
        }

        if ($area === 'adminhtml') {
            $this->setAdminRequestNames($controllerName, $actionName, $module, $request);
        } elseif ($area === 'install') {
            $this->setInstallRequestNames($controllerName, $actionName, $module, $request);
        } else {
            $this->setRequestNamesFromController($controllerClass, $action, $request);
        }

        $request->setDispatched(true);
        $controllerInstance->dispatch($actionName);

        return true;
    }

    /**
     * Walk the module chain to find the highest-priority controller class.
     *
     * This supports third-party modules that register controller overrides via
     * config.xml frontend/routers module chains (before/after ordering).
     * Returns null if no module chain is found (falls back to compiled default).
     */
    protected function resolveControllerClass(
        string $module,
        string $controllerName,
        string $area = 'frontend',
    ): ?string {
        if (!$module || !$controllerName) {
            return null;
        }

        $frontName = $this->findFrontNameForModule($module, $area);
        if (!$frontName) {
            return null;
        }

        $modules = RouteRegistry::getModulesByFrontName($frontName);
        if (!$modules) {
            return null;
        }

        // Walk the chain — first module with a valid controller+action wins
        foreach ($modules as $realModule) {
            $className = $realModule . '_' . uc_words($controllerName) . 'Controller';
            if (!class_exists($className)) {
                continue;
            }

            return $className;
        }

        return null;
    }

    /**
     * Find the frontName for a given module by searching the route registry.
     */
    protected function findFrontNameForModule(string $module, string $area = 'frontend'): ?string
    {
        $configArea = match ($area) {
            'adminhtml' => 'admin',
            default => 'frontend',
        };

        $routers = Mage::getConfig()->getNode($configArea . '/routers');
        if (!$routers) {
            return null;
        }

        foreach ($routers->children() as $routerConfig) {
            $frontName = (string) ($routerConfig->args->frontName ?? '');
            if (!$frontName) {
                continue;
            }

            $modules = RouteRegistry::getModulesByFrontName($frontName);
            if ($modules && in_array($module, $modules, true)) {
                return $frontName;
            }
        }

        return null;
    }

    /**
     * Parse URL path params (key/value pairs after module/controller/action).
     */
    protected function parseUrlParams(string $paramsString, Mage_Core_Controller_Request_Http $request): void
    {
        if ($paramsString === '') {
            return;
        }

        $parts = explode('/', $paramsString);
        for ($i = 0, $l = count($parts); $i < $l; $i += 2) {
            $request->setParam($parts[$i], isset($parts[$i + 1]) ? urldecode($parts[$i + 1]) : '');
        }
    }

    /**
     * Set route parameters on the request, excluding internal Maho/Symfony keys.
     */
    protected function setRequestParams(array $params, Mage_Core_Controller_Request_Http $request): void
    {
        foreach ($params as $key => $value) {
            if (!str_starts_with($key, '_')) {
                $request->setParam($key, $value);
            }
        }
    }

    /**
     * Set request names for admin attribute routes.
     *
     * Admin controllers all share the 'adminhtml' route name and the configured
     * admin frontName as the module name. The controller name and module come
     * from the compiled route metadata.
     */
    protected function setAdminRequestNames(
        string $controllerName,
        string $actionName,
        string $controllerModule,
        Mage_Core_Controller_Request_Http $request,
    ): void {
        $adminFrontName = RouteRegistry::getAdminFrontName();

        $request->setModuleName($adminFrontName ?: 'admin');
        $request->setControllerName($controllerName);
        $request->setActionName($actionName);
        $request->setRouteName('adminhtml');
        $request->setControllerModule($controllerModule);
    }

    /**
     * Set request names for install attribute routes.
     *
     * Install controllers use 'install' as both the frontName and route name.
     */
    protected function setInstallRequestNames(
        string $controllerName,
        string $actionName,
        string $controllerModule,
        Mage_Core_Controller_Request_Http $request,
    ): void {
        $request->setModuleName('install');
        $request->setControllerName($controllerName);
        $request->setActionName($actionName);
        $request->setRouteName('install');
        $request->setControllerModule($controllerModule);
    }

    /**
     * Derive module/controller/action names from the controller class for attribute routes.
     *
     * e.g. Mage_Contacts_IndexController::postAction → contacts/index/post
     */
    protected function setRequestNamesFromController(
        string $controllerClass,
        string $action,
        Mage_Core_Controller_Request_Http $request,
    ): void {
        $actionName = preg_replace('/Action$/', '', $action);

        // Remove 'Controller' suffix: Mage_Catalog_Seo_SitemapController → Mage_Catalog_Seo_Sitemap
        $name = preg_replace('/Controller$/', '', $controllerClass);
        $parts = explode('_', $name);

        // First two segments are the module (e.g. Mage_Catalog)
        $moduleVendor = $parts[0] ?? '';
        $moduleShort = $parts[1] ?? '';
        $moduleName = strtolower($moduleShort ?: $moduleVendor);

        // Everything after the module prefix is the controller name
        // e.g. Mage_Catalog_Seo_Sitemap → seo_sitemap
        // e.g. Mage_Contacts_Index → index
        $controllerParts = array_slice($parts, 2);
        $controllerName = strtolower(implode('_', $controllerParts));

        $request->setModuleName($moduleName);
        $request->setControllerName($controllerName);
        $request->setActionName($actionName);
        $request->setRouteName($moduleName);
        $request->setControllerModule($moduleVendor . '_' . $moduleShort);
    }
}
