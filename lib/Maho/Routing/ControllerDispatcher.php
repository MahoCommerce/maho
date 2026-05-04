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
 * Route/controller resolution uses the compiled reverse-lookup maps, so each
 * dispatch is an O(1) hash lookup plus a `class_exists` check. The only scan
 * that remains is the admin module override chain, which is unavoidable for
 * third-party admin extensions without `#[Route]` attributes.
 */
class ControllerDispatcher
{
    /**
     * Cached admin module chain for this dispatcher instance.
     * The chain is invariant per dispatcher, so subsequent resolveControllerClass /
     * resolveAttributeControllerClass calls within one dispatch flow reuse it.
     *
     * @var string[]|null
     */
    private ?array $adminModuleChain = null;

    /**
     * Cache of frontend module chains keyed by lowercase frontName.
     * Each chain is the ordered list of override module class prefixes from
     * <frontend><routers><X><args><modules> for that frontName.
     *
     * @var array<string, string[]>
     */
    private array $frontendModuleChains = [];

    /**
     * Dispatch an internally-forwarded request.
     *
     * Called when another router (CMS, URL rewrite, _forward()) has set
     * module/controller/action on the request but hasn't dispatched it.
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

        $controllerClass = $this->resolveControllerClass($moduleName, $controllerName);
        if (!$controllerClass || !class_exists($controllerClass)) {
            return false;
        }

        $controllerInstance = Mage::getControllerInstance($controllerClass, $request, $response);
        if (!$controllerInstance->hasAction($actionName)) {
            return false;
        }

        // Set routeName for event dispatching (controller_action_predispatch_<routeName>)
        if (!$request->getRouteName()) {
            $request->setRouteName($moduleName);
        }
        $request->setDispatched(true);
        $controllerInstance->dispatch($actionName);

        return true;
    }

    /**
     * Parse and dispatch a legacy internal path (frontName/controller/action/key/value).
     *
     * Used as a fallback when the Symfony matcher misses — catches URL rewrites
     * whose targets are stored in legacy format (e.g. "catalog/category/view/id/14")
     * and third-party modules without `#[Route]` attributes.
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
        $frontName = $parts[0];
        $controllerName = $parts[1] ?? 'index';
        $actionName = $parts[2] ?? 'index';

        $controllerClass = $this->resolveControllerClass($frontName, $controllerName);
        if (!$controllerClass || !class_exists($controllerClass)) {
            return false;
        }

        for ($i = 3, $l = count($parts); $i < $l; $i += 2) {
            $key = urldecode($parts[$i]);
            $request->setParam($key, isset($parts[$i + 1]) ? urldecode($parts[$i + 1]) : '');
        }

        $request->setModuleName($frontName);
        $request->setControllerName($controllerName);
        $request->setActionName($actionName);

        return $this->dispatchForward($request, $response);
    }

    /**
     * Resolve a controller class from frontName + controllerName via the compiled
     * controllerLookup map, with the frontend/admin module chain as override.
     *
     * The frontend chain is consulted *before* the compiled lookup so that
     * <frontend><routers><X><args><modules> overrides win over the core module
     * (M1 parity: "module chain entries override the base module").
     */
    protected function resolveControllerClass(string $frontName, string $controllerName): ?string
    {
        // Frontend module chain — third-party overrides win over the compiled base.
        foreach ($this->buildFrontendModuleChain($frontName) as $chainModule) {
            $className = $chainModule . '_' . uc_words($controllerName) . 'Controller';
            if (class_exists($className)) {
                return $className;
            }
        }

        $module = RouteCollectionBuilder::resolveControllerModule($frontName, $controllerName);
        if ($module !== null) {
            $className = $module . '_' . uc_words($controllerName) . 'Controller';
            if (class_exists($className)) {
                return $className;
            }
        }

        // Fall back to admin module chain for third-party admin extensions without #[Route]
        if (strtolower($frontName) === strtolower(RouteCollectionBuilder::getAdminFrontName())) {
            foreach ($this->buildAdminModuleChain() as $chainModule) {
                $className = $chainModule . '_' . uc_words($controllerName) . 'Controller';
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
     */
    public function dispatch(
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

        // Admin area: verify the matched frontName matches the runtime admin frontName.
        // Without this, a request like /notadmin/... would match admin routes with any
        // segment for `{_adminFrontName}` and dispatch as admin — rejecting at this point
        // simply falls through to the noroute handler.
        if ($area === 'adminhtml') {
            $matchedAdminFrontName = $params['_adminFrontName'] ?? '';
            if (strtolower($matchedAdminFrontName) !== strtolower(RouteCollectionBuilder::getAdminFrontName())) {
                return false;
            }
        }

        $frontName = $params['_maho_front_name'] ?? '';
        $controllerClass = $this->resolveAttributeControllerClass($controllerName, $area, $frontName) ?? $defaultClass;

        $actionName = preg_replace('/Action$/', '', $action);

        if (!class_exists($controllerClass)) {
            return false;
        }

        $controllerInstance = Mage::getControllerInstance($controllerClass, $request, $response);
        if (!$controllerInstance->hasAction($actionName)) {
            return false;
        }

        $this->setRequestParams($params, $request);

        if ($area === 'adminhtml' && !empty($params['_catchall'])) {
            $this->parseUrlParams($params['_catchall'], $request);
        }

        match ($area) {
            'adminhtml' => $this->setAdminRequestNames($controllerName, $actionName, $module, $request),
            'install' => $this->setInstallRequestNames($controllerName, $actionName, $module, $request),
            default => $this->setFrontendRequestNames(
                $params['_maho_front_name'] ?? '',
                $controllerName,
                $actionName,
                $module,
                $request,
            ),
        };

        $request->setDispatched(true);
        $controllerInstance->dispatch($actionName);

        return true;
    }

    /**
     * Walk the area's module override chain. Both admin and frontend honor
     * <args><modules> declarations from third-party config.xml — admin via
     * `admin/routers/adminhtml`, frontend via `frontend/routers/<frontName>`.
     */
    protected function resolveAttributeControllerClass(
        string $controllerName,
        string $area,
        string $frontName,
    ): ?string {
        if (!$controllerName) {
            return null;
        }

        if ($area === 'adminhtml') {
            foreach ($this->buildAdminModuleChain() as $realModule) {
                $className = $realModule . '_' . uc_words($controllerName) . 'Controller';
                if (class_exists($className)) {
                    return $className;
                }
            }
            return null;
        }

        if ($area === 'frontend') {
            foreach ($this->buildFrontendModuleChain($frontName) as $chainModule) {
                $className = $chainModule . '_' . uc_words($controllerName) . 'Controller';
                if (class_exists($className)) {
                    return $className;
                }
            }
        }

        return null;
    }

    /**
     * Build the admin module chain from config.xml, respecting before/after ordering.
     *
     * @return string[]
     */
    private function buildAdminModuleChain(): array
    {
        if ($this->adminModuleChain !== null) {
            return $this->adminModuleChain;
        }

        $modules = ['Mage_Adminhtml'];
        $modulesNode = Mage::getConfig()->getNode('admin/routers/adminhtml/args/modules');
        if (!$modulesNode) {
            return $this->adminModuleChain = $modules;
        }

        foreach ($modulesNode->children() as $customModule) {
            $moduleName = (string) $customModule;
            if (!$moduleName) {
                continue;
            }

            if ($before = $customModule->getAttribute('before')) {
                $position = array_search($before, $modules, true);
                $position = ($position === false) ? 0 : $position;
                array_splice($modules, $position, 0, $moduleName);
            } elseif ($after = $customModule->getAttribute('after')) {
                $position = array_search($after, $modules, true);
                $position = ($position === false) ? count($modules) : $position + 1;
                array_splice($modules, $position, 0, $moduleName);
            } else {
                $modules[] = $moduleName;
            }
        }

        return $this->adminModuleChain = $modules;
    }

    /**
     * Build the frontend module override chain for a given frontName.
     *
     * Mirrors the admin chain logic for M1 BC: third-party modules can register a
     * controller override on a core route by declaring `<frontend><routers><X><args><modules>`
     * in their config.xml. Without this, a third-party `extends Mage_Customer_AccountController`
     * subclass piggybacking on the `customer` frontName via `<modules>` would silently
     * never dispatch (the compiled controllerLookup always points at the core module).
     *
     * Routers are matched by `<args><frontName>` if present, falling back to the router
     * code element name (M1 convention is for them to be equal). Returns only the
     * override modules — the base module from the compiled lookup is the caller's job.
     *
     * @return string[]
     */
    private function buildFrontendModuleChain(string $frontName): array
    {
        if ($frontName === '') {
            return [];
        }

        $cacheKey = strtolower($frontName);
        if (isset($this->frontendModuleChains[$cacheKey])) {
            return $this->frontendModuleChains[$cacheKey];
        }

        $routersNode = Mage::getConfig()->getNode('frontend/routers');
        if (!$routersNode) {
            return $this->frontendModuleChains[$cacheKey] = [];
        }

        $modules = [];
        foreach ($routersNode->children() as $routerCode => $router) {
            $declaredFrontName = trim((string) ($router->args->frontName ?? ''));
            $effectiveFrontName = $declaredFrontName !== '' ? $declaredFrontName : (string) $routerCode;
            if (strtolower($effectiveFrontName) !== $cacheKey) {
                continue;
            }

            $modulesNode = $router->args->modules ?? null;
            if (!$modulesNode) {
                continue;
            }

            foreach ($modulesNode->children() as $customModule) {
                $moduleName = (string) $customModule;
                if (!$moduleName) {
                    continue;
                }

                if ($before = $customModule->getAttribute('before')) {
                    $position = array_search($before, $modules, true);
                    $position = ($position === false) ? 0 : $position;
                    array_splice($modules, $position, 0, $moduleName);
                } elseif ($after = $customModule->getAttribute('after')) {
                    $position = array_search($after, $modules, true);
                    $position = ($position === false) ? count($modules) : $position + 1;
                    array_splice($modules, $position, 0, $moduleName);
                } else {
                    $modules[] = $moduleName;
                }
            }
        }

        return $this->frontendModuleChains[$cacheKey] = $modules;
    }

    protected function parseUrlParams(string $paramsString, Mage_Core_Controller_Request_Http $request): void
    {
        if ($paramsString === '') {
            return;
        }

        $parts = explode('/', $paramsString);
        for ($i = 0, $l = count($parts); $i < $l; $i += 2) {
            $key = urldecode($parts[$i]);
            $request->setParam($key, isset($parts[$i + 1]) ? urldecode($parts[$i + 1]) : '');
        }
    }

    protected function setRequestParams(array $params, Mage_Core_Controller_Request_Http $request): void
    {
        foreach ($params as $key => $value) {
            if (!str_starts_with($key, '_')) {
                $request->setParam($key, $value);
            }
        }
    }

    protected function setAdminRequestNames(
        string $controllerName,
        string $actionName,
        string $controllerModule,
        Mage_Core_Controller_Request_Http $request,
    ): void {
        $request->setModuleName(RouteCollectionBuilder::getAdminFrontName() ?: 'admin');
        $request->setControllerName($controllerName);
        $request->setActionName($actionName);
        $request->setRouteName('adminhtml');
        $request->setControllerModule($controllerModule);
    }

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
     * Frontend routes set module/route name from the #[Route] path's first segment
     * rather than the class name, because modules like PaypalUk have a frontName
     * ('payflow') distinct from their module key ('paypaluk').
     */
    protected function setFrontendRequestNames(
        string $frontName,
        string $controllerName,
        string $actionName,
        string $controllerModule,
        Mage_Core_Controller_Request_Http $request,
    ): void {
        $request->setModuleName($frontName);
        $request->setControllerName($controllerName);
        $request->setActionName($actionName);
        $request->setRouteName($frontName);
        $request->setControllerModule($controllerModule);
    }
}
